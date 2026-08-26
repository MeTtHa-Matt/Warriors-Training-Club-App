<?php
/**
 * Gestionnaire du contrôle d'accès aux séances
 * Prévient les attaques IDOR (Insecure Direct Object References)
 */
class SeanceAccessControl {
    /**
     * Vérifie si l'utilisateur peut modifier une séance
     */
    public static function canEditSeance($userId, $seanceId, $pdo) {
        $stmt = $pdo->prepare("SELECT created_by FROM seances WHERE id = ?");
        $stmt->execute([$seanceId]);
        $seance = $stmt->fetch();

        if (!$seance) {
            return false;
        }

        $userStmt = $pdo->prepare("SELECT admin FROM account_wtc WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();

        return ($seance['created_by'] == $userId) || ($user && $user['admin']);
    }

    /**
     * Vérifie si l'utilisateur peut s'inscrire à une séance
     */
    public static function canRegisterForSeance($userId, $seanceId, $pdo) {
        $stmt = $pdo->prepare("SELECT id FROM seances WHERE id = ?");
        $stmt->execute([$seanceId]);

        return (bool)$stmt->fetch();
    }

    /**
     * Vérifie si l'utilisateur peut supprimer une séance
     */
    public static function canDeleteSeance($userId, $seanceId, $pdo) {
        return self::canEditSeance($userId, $seanceId, $pdo);
    }
}
?>
