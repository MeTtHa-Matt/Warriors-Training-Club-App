<?php
/**
 * Validateur sécurisé pour les en-têtes email
 * Prévient les injections d'en-têtes SMTP
 */
class EmailHeaderValidator {
    const SUBJECT_MAX_LENGTH = 200;
    const SIGNATURE_MAX_LENGTH = 1000;

    /**
     * Valide un en-tête email
     */
    public static function validateHeader($value, $maxLength = 255) {
        // Vérifier les caractères dangereux: \r, \n, \0
        if (preg_match('/[\r\n\0]/', $value)) {
            throw new InvalidArgumentException("L'en-tête contient des caractères invalides");
        }

        // Limiter la longueur
        if (strlen($value) > $maxLength) {
            throw new InvalidArgumentException("L'en-tête est trop long (max: $maxLength caractères)");
        }

        return true;
    }

    /**
     * Nettoie une chaîne pour une utilisation en en-tête email
     */
    public static function sanitizeHeader($value, $maxLength = 255) {
        $value = preg_replace('/[\r\n\0]/', '', $value);
        $value = substr($value, 0, $maxLength);
        return trim($value);
    }

    /**
     * Valide le sujet d'un email
     */
    public static function validateSubject($subject) {
        if (preg_match('/[\r\n\0]/', $subject)) {
            throw new InvalidArgumentException("L'objet contient des caractères invalides");
        }

        if (strlen($subject) < 3) {
            throw new InvalidArgumentException("L'objet doit contenir au moins 3 caractères");
        }

        if (strlen($subject) > self::SUBJECT_MAX_LENGTH) {
            throw new InvalidArgumentException("L'objet ne doit pas dépasser " . self::SUBJECT_MAX_LENGTH . " caractères");
        }

        return htmlspecialchars(trim($subject), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Valide la signature d'un email
     */
    public static function validateSignature($signature) {
        if (preg_match('/[\r\n\0%0A%0D]/i', $signature)) {
            throw new InvalidArgumentException("La signature contient des caractères invalides");
        }

        if (strlen($signature) > self::SIGNATURE_MAX_LENGTH) {
            throw new InvalidArgumentException("La signature est trop longue");
        }

        return htmlspecialchars(trim($signature), ENT_QUOTES, 'UTF-8');
    }
}
?>
