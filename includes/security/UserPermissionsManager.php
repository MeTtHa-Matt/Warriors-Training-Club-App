<?php
/**
 * Gestionnaire sécurisé des permissions utilisateur
 * Prévient les injections SQL via les colonnes dynamiques
 */
class UserPermissionsManager {
    private const ALLOWED_PERMISSIONS = [
        'admin' => 'admin',
        'gerer_seances' => 'gerer_seances'
    ];

    /**
     * Met à jour une permission utilisateur de manière sécurisée
     */
    public static function updateUserPermission($pdo, $userId, $action, $value) {
        if (!isset(self::ALLOWED_PERMISSIONS[$action])) {
            throw new InvalidArgumentException("Action non autorisée: $action");
        }

        $column = self::ALLOWED_PERMISSIONS[$action];
        $stmt = $pdo->prepare(
            "UPDATE account_wtc SET `" . $column . "` = ? WHERE id = ?"
        );

        return $stmt->execute([$value, $userId]);
    }

    /**
     * Récupère la valeur actuelle d'une permission
     */
    public static function getUserPermission($pdo, $userId, $action) {
        if (!isset(self::ALLOWED_PERMISSIONS[$action])) {
            throw new InvalidArgumentException("Action non autorisée: $action");
        }

        $column = self::ALLOWED_PERMISSIONS[$action];
        $stmt = $pdo->prepare("SELECT `" . $column . "` FROM account_wtc WHERE id = ?");
        $stmt->execute([$userId]);

        $result = $stmt->fetch();
        return $result ? $result[$column] : null;
    }
}
?>
