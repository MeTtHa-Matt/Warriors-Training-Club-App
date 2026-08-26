<?php
/**
 * Validateur et gestionnaire sécurisé d'adresses IP
 * Valide les proxies de confiance avant d'utiliser les en-têtes de proxy
 */
class IpAddressValidator {
    // Liste blanche des proxies de confiance
    private const TRUSTED_PROXIES = [
        '127.0.0.1',
        '::1',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16'
    ];

    /**
     * Obtient l'adresse IP du client de manière sécurisée
     */
    public static function getClientIp() {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Vérifier si la connexion vient d'un proxy de confiance
        if (self::isIpInRange($remoteAddr, self::TRUSTED_PROXIES)) {
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
                $clientIp = $ips[0];

                if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
                    return $clientIp;
                }
            }

            if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && 
                filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
                return $_SERVER['HTTP_CF_CONNECTING_IP'];
            }
        }

        return $remoteAddr;
    }

    /**
     * Vérifie si une IP est dans une plage (CIDR)
     */
    private static function isIpInRange($ip, $ranges) {
        foreach ($ranges as $range) {
            if (strpos($range, '/') === false) {
                // IP simple
                if (ip2long($ip) === ip2long($range)) {
                    return true;
                }
            } else {
                // Plage CIDR
                list($subnet, $bits) = explode('/', $range);
                $ipLong = ip2long($ip);
                $subnetLong = ip2long($subnet);
                $mask = -1 << (32 - $bits);

                if (($ipLong & $mask) === ($subnetLong & $mask)) {
                    return true;
                }
            }
        }
        return false;
    }
}
?>
