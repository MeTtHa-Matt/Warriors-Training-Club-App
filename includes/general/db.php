<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/../../');
$dotenv->load();

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_DATABASE');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Nettoyage automatique des séances trop anciennes (plus de 3 mois).
    $pdo->prepare('DELETE FROM seances WHERE date_seance < DATE_SUB(CURDATE(), INTERVAL 3 MONTH)')->execute();
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
