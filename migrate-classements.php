<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function migrationUsage(): void
{
    echo "Usage: php migrate-classements.php [--dry-run] [--replace] [--file=/path/to/classements.json]\n";
}

$dryRun = false;
$replaceExisting = false;
$jsonPath = __DIR__ . '/data/classements.json';

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--dry-run') {
        $dryRun = true;
    } elseif ($argument === '--replace') {
        $replaceExisting = true;
    } elseif ($argument === '--help' || $argument === '-h') {
        migrationUsage();
        exit(0);
    } elseif (str_starts_with($argument, '--file=')) {
        $jsonPath = substr($argument, 7);
    } else {
        fwrite(STDERR, "Option inconnue : {$argument}\n");
        migrationUsage();
        exit(2);
    }
}

define('WTC_SKIP_SEANCE_CLEANUP', true);
require_once __DIR__ . '/includes/general/db.php';
require_once __DIR__ . '/includes/general/classement-storage.php';

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Connexion à la base de données impossible. Vérifie le fichier .env.\n");
    exit(1);
}

$jsonPath = realpath($jsonPath) ?: '';
if ($jsonPath === '' || !is_file($jsonPath) || !is_readable($jsonPath)) {
    fwrite(STDERR, "Fichier JSON absent ou illisible.\n");
    exit(1);
}

try {
    $contents = file_get_contents($jsonPath);
    if ($contents === false) {
        throw new RuntimeException('Impossible de lire le fichier JSON.');
    }
    $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data) || !isset($data['categories'], $data['records'])
        || !is_array($data['categories']) || !is_array($data['records'])) {
        throw new RuntimeException('Le JSON doit contenir des tableaux categories et records.');
    }

    rankingStorageEnsureSchema($pdo);
    $checksum = hash_file('sha256', $jsonPath);
    if ($checksum === false) {
        throw new RuntimeException('Impossible de calculer l’empreinte du JSON.');
    }
    $sourceName = basename($jsonPath);

    $existingImport = $pdo->prepare('SELECT checksum FROM ranking_imports WHERE source_name = ?');
    $existingImport->execute([$sourceName]);
    $importedChecksum = $existingImport->fetchColumn();
    if (!$replaceExisting && is_string($importedChecksum) && hash_equals($importedChecksum, $checksum)) {
        echo "Ce fichier a déjà été importé (SHA-256 identique). Aucune modification effectuée.\n";
        exit(0);
    }

    $existingCategories = (int) $pdo->query('SELECT COUNT(*) FROM ranking_categories')->fetchColumn();
    $existingRecords = (int) $pdo->query('SELECT COUNT(*) FROM ranking_records')->fetchColumn();
    if (($existingCategories > 0 || $existingRecords > 0) && !$replaceExisting) {
        throw new RuntimeException('La base contient déjà des classements. Rien n’a été modifié. Utilise --replace seulement si tu veux remplacer ces données.');
    }

    $categoryCount = count($data['categories']);
    $recordCount = count($data['records']);
    $participantCount = 0;
    $photoCount = 0;
    foreach ($data['records'] as $record) {
        if (!is_array($record)) {
            throw new RuntimeException('Le JSON contient une performance invalide.');
        }
        $participantCount += count(rankingStorageParticipants($record));
        $photoCount += count($record['photos'] ?? []);
    }

    echo "Source : {$jsonPath}\n";
    echo "Catégories : {$categoryCount}; performances : {$recordCount}; participants : {$participantCount}; références photo : {$photoCount}\n";
    if ($dryRun) {
        echo "Simulation terminée : aucune donnée de classement n’a été importée.\n";
        exit(0);
    }

    $pdo->beginTransaction();
    rankingStorageReplace($pdo, $data);
    $saveImport = $pdo->prepare(
        'INSERT INTO ranking_imports (source_name, checksum, imported_at, categories_count, records_count)
         VALUES (?, ?, UTC_TIMESTAMP(), ?, ?)
         ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), imported_at = VALUES(imported_at),
             categories_count = VALUES(categories_count), records_count = VALUES(records_count)'
    );
    $saveImport->execute([$sourceName, $checksum, $categoryCount, $recordCount]);
    $pdo->commit();

    echo "Import terminé. Le fichier JSON n’a pas été supprimé.\n";
    echo "Les fichiers photo doivent aussi être copiés dans data/classement_photos/.\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Échec de l’import : " . $error->getMessage() . "\n");
    exit(1);
}