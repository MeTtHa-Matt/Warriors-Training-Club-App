<?php

$pageTitle = "Warriors Training Club - Accueil";

$heroInscriptionLink = 'https://www.helloasso.com/associations/warriors-training-club/adhesions/formulaire-d-inscription-2024-2025';
$cardAdhesionLink = 'https://www.helloasso.com/associations/warriors-training-club/adhesions/formulaire-d-inscription-2024-2025';
$cardBoutiqueBarresLink = 'https://www.helloasso.com/associations/warriors-training-club/boutiques/barres-de-cereales-artisanales';
$cardBoutiqueJudoLink = 'https://www.helloasso.com/associations/judo-club-mormant/boutiques/kimonos-club-adidas-dossards-2024-2025';
$cardBoutiqueVetementsLink = 'https://market-factory.fr/warriors-training-club/';
$cardMapLink = 'https://www.google.com/maps/search/?api=1&query=48.6113915%2C2.8807517';

try {
    $stmt = $pdo->query("SELECT link_key, url FROM index_links WHERE link_key IN ('hero_inscription', 'card_adhesion', 'card_boutique_barres', 'card_boutique_judo', 'card_boutique_vetements', 'card_map')");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        switch ($row['link_key']) {
            case 'hero_inscription':
                $heroInscriptionLink = $row['url'];
                break;
            case 'card_adhesion':
                $cardAdhesionLink = $row['url'];
                break;
            case 'card_boutique_barres':
                $cardBoutiqueBarresLink = $row['url'];
                break;
            case 'card_boutique_judo':
                $cardBoutiqueJudoLink = $row['url'];
                break;
            case 'card_boutique_vetements':
                $cardBoutiqueVetementsLink = $row['url'];
                break;
            case 'card_map':
                $cardMapLink = $row['url'];
                break;
        }
    }
} catch (PDOException $e) {
}

$heroInscriptionLink = htmlspecialchars($heroInscriptionLink);
$cardAdhesionLink = htmlspecialchars($cardAdhesionLink);
$cardBoutiqueBarresLink = htmlspecialchars($cardBoutiqueBarresLink);
$cardBoutiqueJudoLink = htmlspecialchars($cardBoutiqueJudoLink);
$cardBoutiqueVetementsLink = htmlspecialchars($cardBoutiqueVetementsLink);
$cardMapLink = htmlspecialchars($cardMapLink);