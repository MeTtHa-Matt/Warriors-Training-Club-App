<?php

if (empty($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$currentId = (int) $_SESSION['user_id'];
$adminCheckStmt = $pdo->prepare('SELECT admin FROM account_wtc WHERE id = ?');
$adminCheckStmt->execute([$currentId]);
$isAdmin = (bool) $adminCheckStmt->fetchColumn();

if (!$isAdmin) {
    header('Location: index.php');
    exit;
}

$errors = $_SESSION['errors'] ?? [];
$success = $_SESSION['success'] ?? null;
unset($_SESSION['errors'], $_SESSION['success']);

$defaultLinks = [
    'hero_inscription' => [
        'label' => 'Inscription en ligne',
        'title' => 'Bouton principal d’inscription',
        'url' => 'https://www.helloasso.com/associations/warriors-training-club/adhesions/formulaire-d-inscription-2024-2025',
        'order' => 1,
    ],
    'card_adhesion' => [
        'label' => 'Adhésion',
        'title' => 'Carte d’adhésion',
        'url' => 'https://www.helloasso.com/associations/warriors-training-club/adhesions/formulaire-d-inscription-2024-2025',
        'order' => 2,
    ],
    'card_boutique_barres' => [
        'label' => 'Boutique',
        'title' => 'Barres de céréales Les Craq\'s',
        'url' => 'https://www.helloasso.com/associations/warriors-training-club/boutiques/barres-de-cereales-artisanales',
        'order' => 3,
    ],
    'card_boutique_judo' => [
        'label' => 'Boutique',
        'title' => 'Judo : kimonos, dossards',
        'url' => 'https://www.helloasso.com/associations/judo-club-mormant/boutiques/kimonos-club-adidas-dossards-2024-2025',
        'order' => 4,
    ],
    'card_boutique_vetements' => [
        'label' => 'Boutique',
        'title' => 'Vêtements Warriors',
        'url' => 'https://market-factory.fr/warriors-training-club/',
        'order' => 5,
    ],
    'card_map' => [
        'label' => 'Carte',
        'title' => 'Localisation du Dojo Teddy Riner',
        'url' => 'https://www.google.com/maps/search/?api=1&query=48.6113915%2C2.8807517',
        'order' => 6,
    ],
];

$validKeys = array_keys($defaultLinks);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $linksToSave = [];

    foreach ($validKeys as $key) {
        if (!isset($_POST[$key])) {
            $errors[] = "Le champ \"{$defaultLinks[$key]['title']}\" est manquant.";
            continue;
        }

        $url = trim($_POST[$key]);
        if ($url === '') {
            $errors[] = "L'URL pour \"{$defaultLinks[$key]['title']}\" ne peut pas être vide.";
            continue;
        }

        $url = filter_var($url, FILTER_SANITIZE_URL);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = "L'URL pour \"{$defaultLinks[$key]['title']}\" n'est pas valide.";
            continue;
        }

        $linksToSave[$key] = $url;
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $upsertStmt = $pdo->prepare(
                'INSERT INTO index_links (link_key, label, title, url, display_order)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                     url = VALUES(url),
                     label = VALUES(label),
                     title = VALUES(title),
                     display_order = VALUES(display_order)'
            );

            foreach ($linksToSave as $key => $url) {
                $entry = $defaultLinks[$key];
                $upsertStmt->execute([
                    $key,
                    $entry['label'],
                    $entry['title'],
                    $url,
                    $entry['order'],
                ]);

                $defaultLinks[$key]['url'] = $url;
            }

            $pdo->commit();
            $success = 'Les liens ont bien été enregistrés.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Impossible d’enregistrer les liens. Vérifie que la table index_links existe bien dans la base de données.';
        }
    }
} else {
    try {
        $stmt = $pdo->query("SELECT link_key, url FROM index_links WHERE link_key IN ('hero_inscription', 'card_adhesion', 'card_boutique_barres', 'card_boutique_judo', 'card_boutique_vetements', 'card_map')");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            if (isset($defaultLinks[$row['link_key']])) {
                $defaultLinks[$row['link_key']]['url'] = $row['url'];
            }
        }
    } catch (PDOException $e) {
    }
}

$pageTitle = 'Warriors Training Club - Modifier les liens index';