<?php

function rankingStorageEnsureSchema(PDO $pdo): void
{
    $statements = [
        <<<'SQL'
CREATE TABLE IF NOT EXISTS ranking_categories (
    id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_ranking_categories_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS ranking_subcategories (
    category_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(80) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (category_id, id),
    INDEX idx_ranking_subcategories_created (created_at),
    CONSTRAINT fk_ranking_subcategories_category FOREIGN KEY (category_id)
        REFERENCES ranking_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS ranking_records (
    id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    category_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    subcategory_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    owner_id INT DEFAULT NULL,
    competition VARCHAR(120) NOT NULL,
    event_date DATE NOT NULL,
    time_seconds INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_ranking_records_category_time (category_id, time_seconds, created_at),
    INDEX idx_ranking_records_subcategory_time (category_id, subcategory_id, time_seconds),
    INDEX idx_ranking_records_owner (owner_id),
    CONSTRAINT fk_ranking_records_category FOREIGN KEY (category_id)
        REFERENCES ranking_categories(id) ON DELETE CASCADE,
    CONSTRAINT fk_ranking_records_subcategory FOREIGN KEY (category_id, subcategory_id)
        REFERENCES ranking_subcategories(category_id, id) ON DELETE CASCADE,
    CONSTRAINT fk_ranking_records_owner FOREIGN KEY (owner_id)
        REFERENCES account_wtc(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS ranking_record_participants (
    record_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ordinal SMALLINT UNSIGNED NOT NULL,
    account_id INT DEFAULT NULL,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(150) NOT NULL DEFAULT '',
    last_initial VARCHAR(20) NOT NULL DEFAULT '',
    is_external TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (record_id, ordinal),
    INDEX idx_ranking_participants_account (account_id),
    CONSTRAINT fk_ranking_participants_record FOREIGN KEY (record_id)
        REFERENCES ranking_records(id) ON DELETE CASCADE,
    CONSTRAINT fk_ranking_participants_account FOREIGN KEY (account_id)
        REFERENCES account_wtc(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS ranking_record_photos (
    record_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ordinal SMALLINT UNSIGNED NOT NULL,
    filename VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PRIMARY KEY (record_id, ordinal),
    CONSTRAINT fk_ranking_photos_record FOREIGN KEY (record_id)
        REFERENCES ranking_records(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS ranking_imports (
    source_name VARCHAR(255) NOT NULL PRIMARY KEY,
    checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    imported_at DATETIME NOT NULL,
    categories_count INT UNSIGNED NOT NULL,
    records_count INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
}

function rankingStorageRead(PDO $pdo): array
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $data = ['categories' => [], 'records' => []];
        $categories = [];

        foreach ($pdo->query('SELECT id, name, created_at FROM ranking_categories ORDER BY created_at, id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (string) $row['id'];
            $categories[$id] = [
                'id' => $id,
                'name' => (string) $row['name'],
                'subcategories' => [],
                'created_at' => rankingStorageIsoDate($row['created_at']),
            ];
        }

        foreach ($pdo->query('SELECT category_id, id, name, created_at FROM ranking_subcategories ORDER BY created_at, id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $categoryId = (string) $row['category_id'];
            if (!isset($categories[$categoryId])) {
                continue;
            }
            $categories[$categoryId]['subcategories'][] = [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'created_at' => rankingStorageIsoDate($row['created_at']),
            ];
        }
        $data['categories'] = array_values($categories);

        $records = [];
        foreach ($pdo->query('SELECT id, category_id, subcategory_id, owner_id, competition, event_date, time_seconds, created_at FROM ranking_records ORDER BY created_at, id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (string) $row['id'];
            $records[$id] = [
                'id' => $id,
                'category_id' => (string) $row['category_id'],
                'subcategory_id' => $row['subcategory_id'] === null ? null : (string) $row['subcategory_id'],
                'owner_id' => $row['owner_id'] === null ? 0 : (int) $row['owner_id'],
                'competition' => (string) $row['competition'],
                'event_date' => (string) $row['event_date'],
                'time_seconds' => (int) $row['time_seconds'],
                'created_at' => rankingStorageIsoDate($row['created_at']),
                'participants' => [],
                'photos' => [],
            ];
        }

        foreach ($pdo->query('SELECT record_id, account_id, firstname, lastname, last_initial, is_external FROM ranking_record_participants ORDER BY record_id, ordinal')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $recordId = (string) $row['record_id'];
            if (!isset($records[$recordId])) {
                continue;
            }
            $records[$recordId]['participants'][] = [
                'user_id' => $row['account_id'] === null ? 0 : (int) $row['account_id'],
                'firstname' => (string) $row['firstname'],
                'lastname' => (string) $row['lastname'],
                'last_initial' => (string) $row['last_initial'],
            ] + ((int) $row['is_external'] === 1 ? ['external' => true] : []);
        }

        foreach ($pdo->query('SELECT record_id, filename FROM ranking_record_photos ORDER BY record_id, ordinal')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $recordId = (string) $row['record_id'];
            if (isset($records[$recordId])) {
                $records[$recordId]['photos'][] = (string) $row['filename'];
            }
        }

        $data['records'] = array_values($records);
        if ($ownsTransaction) {
            $pdo->commit();
        }
        return $data;
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function rankingStorageReplace(PDO $pdo, array $data): void
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $pdo->exec('DELETE FROM ranking_categories');

        $insertCategory = $pdo->prepare('INSERT INTO ranking_categories (id, name, created_at) VALUES (?, ?, ?)');
        $insertSubcategory = $pdo->prepare('INSERT INTO ranking_subcategories (category_id, id, name, created_at) VALUES (?, ?, ?, ?)');
        foreach (($data['categories'] ?? []) as $category) {
            $categoryId = rankingStorageId($category['id'] ?? null, 'category');
            $insertCategory->execute([
                $categoryId,
                rankingStorageText($category['name'] ?? '', 80, 'category name'),
                rankingStorageDate($category['created_at'] ?? null),
            ]);
            foreach (($category['subcategories'] ?? []) as $subcategory) {
                $subcategoryId = rankingStorageId($subcategory['id'] ?? null, 'subcategory');
                $insertSubcategory->execute([
                    $categoryId,
                    $subcategoryId,
                    rankingStorageText($subcategory['name'] ?? '', 80, 'subcategory name'),
                    rankingStorageDate($subcategory['created_at'] ?? null),
                ]);
            }
        }

        $accountExists = $pdo->prepare('SELECT id FROM account_wtc WHERE id = ?');
        $insertRecord = $pdo->prepare(
            'INSERT INTO ranking_records (id, category_id, subcategory_id, owner_id, competition, event_date, time_seconds, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insertParticipant = $pdo->prepare(
            'INSERT INTO ranking_record_participants (record_id, ordinal, account_id, firstname, lastname, last_initial, is_external)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $insertPhoto = $pdo->prepare('INSERT INTO ranking_record_photos (record_id, ordinal, filename) VALUES (?, ?, ?)');

        foreach (($data['records'] ?? []) as $record) {
            $recordId = rankingStorageId($record['id'] ?? null, 'record');
            $categoryId = rankingStorageId($record['category_id'] ?? null, 'record category');
            $subcategoryId = empty($record['subcategory_id']) ? null : rankingStorageId($record['subcategory_id'], 'record subcategory');
            $ownerId = rankingStorageAccountId($pdo, $accountExists, $record['owner_id'] ?? null);
            $eventDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($record['event_date'] ?? ''));
            if (!$eventDate || $eventDate->format('Y-m-d') !== (string) $record['event_date']) {
                throw new RuntimeException('Invalid event date in ranking record ' . $recordId);
            }
            $timeSeconds = filter_var($record['time_seconds'] ?? null, FILTER_VALIDATE_INT);
            if ($timeSeconds === false || $timeSeconds < 1) {
                throw new RuntimeException('Invalid time in ranking record ' . $recordId);
            }

            $insertRecord->execute([
                $recordId,
                $categoryId,
                $subcategoryId,
                $ownerId,
                rankingStorageText($record['competition'] ?? '', 120, 'competition'),
                $eventDate->format('Y-m-d'),
                $timeSeconds,
                rankingStorageDate($record['created_at'] ?? null),
            ]);

            $participants = rankingStorageParticipants($record);
            foreach ($participants as $ordinal => $participant) {
                $participantAccountId = rankingStorageAccountId($pdo, $accountExists, $participant['user_id'] ?? null);
                $insertParticipant->execute([
                    $recordId,
                    $ordinal,
                    $participantAccountId,
                    rankingStorageText($participant['firstname'] ?? 'Membre', 100, 'participant firstname'),
                    rankingStorageText($participant['lastname'] ?? '', 150, 'participant lastname', true),
                    rankingStorageText($participant['last_initial'] ?? '', 20, 'participant initial', true),
                    !empty($participant['external']) ? 1 : 0,
                ]);
            }

            foreach (array_values($record['photos'] ?? []) as $ordinal => $filename) {
                $filename = basename((string) $filename);
                if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)) {
                    throw new RuntimeException('Invalid photo filename in ranking record ' . $recordId);
                }
                $insertPhoto->execute([$recordId, $ordinal, $filename]);
            }
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function rankingStorageParticipants(array $record): array
{
    if (isset($record['participants']) && is_array($record['participants'])) {
        return array_values(array_filter($record['participants'], static function ($participant): bool {
            if (!is_array($participant)) {
                return false;
            }
            if ((int) ($participant['user_id'] ?? 0) > 0) {
                return true;
            }
            return !empty($participant['external'])
                && trim((string) ($participant['firstname'] ?? '')) !== ''
                && trim((string) ($participant['lastname'] ?? '')) !== '';
        }));
    }

    $userId = (int) ($record['user_id'] ?? 0);
    if ($userId <= 0) {
        return [];
    }

    return [[
        'user_id' => $userId,
        'firstname' => (string) ($record['firstname'] ?? 'Membre'),
        'lastname' => (string) ($record['lastname'] ?? ''),
        'last_initial' => (string) ($record['last_initial'] ?? ''),
    ]];
}

function rankingStorageId(mixed $value, string $label): string
{
    $id = (string) $value;
    if (!preg_match('/^[a-f0-9]{1,32}$/i', $id)) {
        throw new RuntimeException('Invalid ' . $label . ' ID.');
    }
    return $id;
}

function rankingStorageText(mixed $value, int $maxLength, string $label, bool $allowEmpty = false): string
{
    if (!is_string($value) && !is_numeric($value)) {
        throw new RuntimeException('Invalid ' . $label . '.');
    }
    $text = trim((string) $value);
    if ((!$allowEmpty && $text === '') || mb_strlen($text) > $maxLength) {
        throw new RuntimeException('Invalid ' . $label . '.');
    }
    return $text;
}

function rankingStorageDate(mixed $value): string
{
    try {
        $date = $value === null || $value === ''
            ? new DateTimeImmutable('now', new DateTimeZone('UTC'))
            : new DateTimeImmutable((string) $value);
    } catch (Throwable $error) {
        throw new RuntimeException('Invalid ranking timestamp.', 0, $error);
    }
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function rankingStorageIsoDate(string $value): string
{
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone('UTC'))
        ->format(DATE_ATOM);
}

function rankingStorageAccountId(PDO $pdo, PDOStatement $accountExists, mixed $value): ?int
{
    $accountId = filter_var($value, FILTER_VALIDATE_INT);
    if ($accountId === false || $accountId === null || $accountId <= 0) {
        return null;
    }
    $accountExists->execute([$accountId]);
    return $accountExists->fetchColumn() === false ? null : $accountId;
}