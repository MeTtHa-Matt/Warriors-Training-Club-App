<?php
function ensureSeanceTemplateSchema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS seance_templates (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            sessions JSON NOT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES account_wtc(id) ON DELETE CASCADE,
            INDEX (created_by)
        )'
    );

    $columnCheck = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seances' AND COLUMN_NAME = 'template_id'");
    $columnCheck->execute();
    if (!$columnCheck->fetchColumn()) {
        $pdo->exec('ALTER TABLE seances ADD COLUMN template_id INT NULL');
        $pdo->exec('ALTER TABLE seances ADD INDEX idx_seances_template_id (template_id)');
    }

    $isModifiedCheck = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seances' AND COLUMN_NAME = 'is_modified'");
    $isModifiedCheck->execute();
    if (!$isModifiedCheck->fetchColumn()) {
        $pdo->exec('ALTER TABLE seances ADD COLUMN is_modified TINYINT(1) NOT NULL DEFAULT 0');
    }
}

function firstDateForWeekday(DateTimeImmutable $date, int $weekday): DateTimeImmutable
{
    // weekday: 1=Monday, 7=Sunday (ISO-8601)
    $currentWeekday = (int) $date->format('N');
    $daysToAdd = ($weekday - $currentWeekday) % 7;
    if ($daysToAdd < 0) {
        $daysToAdd += 7;
    }
    return $date->modify('+' . $daysToAdd . ' day');
}

function startOfMonth(DateTimeImmutable $date): DateTimeImmutable
{
    return (new DateTimeImmutable($date->format('Y-m-01')))->setTime(0, 0);
}

function buildTemplateDates(DateTimeImmutable $startDate, DateTimeImmutable $endDate, array $sessions): array
{
    $dates = [];

    foreach ($sessions as $session) {
        $weekday = isset($session['weekday']) ? (int) $session['weekday'] : null;
        if ($weekday === null || $weekday < 1 || $weekday > 7) {
            continue;
        }

        $candidate = firstDateForWeekday($startDate, $weekday);
        while ($candidate <= $endDate) {
            $dates[] = [
                'date' => $candidate->format('Y-m-d'),
                'heure_debut' => $session['heure_debut'] ?? null,
                'heure_fin' => $session['heure_fin'] ?? null,
                'type_seance' => $session['type_seance'] ?? '',
                'coach' => $session['coach'] ?? '',
                'lieu_seance' => $session['lieu_seance'] ?? '',
                'lieu_rdv' => $session['lieu_rdv'] ?? '',
                'description' => $session['description'] ?? null,
            ];
            $candidate = $candidate->modify('+7 days');
        }
    }

    usort($dates, static fn($a, $b) => strcmp($a['date'], $b['date']));
    return $dates;
}
