<?php
/**
 * Gestionnaire sécurisé pour les uploads de fichiers
 * Valide strictement les fichiers uploadés
 */
class SecureFileUploadHandler {
    const SAFE_DIR_PERMISSIONS = 0755;
    const SAFE_FILE_PERMISSIONS = 0644;
    const MAX_FILE_SIZE = 5242880; // 5 MB

    /**
     * Crée un répertoire de manière sécurisée
     */
    public static function createSecureDirectory($path) {
        if (is_dir($path)) {
            chmod($path, self::SAFE_DIR_PERMISSIONS);
            return true;
        }

        if (!mkdir($path, self::SAFE_DIR_PERMISSIONS, true)) {
            return false;
        }

        if (decoct(fileperms($path) & 0777) !== decoct(self::SAFE_DIR_PERMISSIONS)) {
            chmod($path, self::SAFE_DIR_PERMISSIONS);
        }

        return true;
    }

    /**
     * Valide un fichier uploadé de manière extrêmement stricte
     */
    public static function validateUploadedFile($fileArray) {
        if (!is_uploaded_file($fileArray['tmp_name'])) {
            throw new InvalidArgumentException("Fichier non uploadé correctement");
        }

        if ($fileArray['size'] > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException("Fichier trop volumineux");
        }

        // Valider le type MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileArray['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new InvalidArgumentException("Type de fichier non autorisé: $mimeType");
        }

        // Valider la structure du fichier (magic bytes)
        $handle = fopen($fileArray['tmp_name'], 'rb');
        $magicBytes = fread($handle, 16);
        fclose($handle);

        $validHeaders = [
            "\xFF\xD8\xFF" => 'jpeg',
            "\x89PNG" => 'png',
            "RIFF" => 'webp',
            "\x47\x49\x46" => 'gif'
        ];

        $validHeader = false;
        $detectedFormat = null;
        foreach ($validHeaders as $header => $format) {
            if (strpos($magicBytes, $header) === 0) {
                $validHeader = true;
                $detectedFormat = $format;
                break;
            }
        }

        if (!$validHeader) {
            throw new InvalidArgumentException("Structure de fichier image invalide");
        }

        // Vérifier la cohérence format/MIME
        $mimeFormatMap = [
            'image/jpeg' => 'jpeg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif'
        ];

        if ($mimeFormatMap[$mimeType] !== $detectedFormat) {
            throw new InvalidArgumentException("Format de fichier incohérent");
        }

        // Valider les dimensions
        $imageInfo = @getimagesize($fileArray['tmp_name']);
        if ($imageInfo === false) {
            throw new InvalidArgumentException("Impossible de lire les propriétés de l'image");
        }

        if ($imageInfo[0] < 100 || $imageInfo[1] < 100 || 
            $imageInfo[0] > 4000 || $imageInfo[1] > 4000) {
            throw new InvalidArgumentException("Dimensions d'image invalides");
        }

        return [
            'mime' => $mimeType,
            'format' => $detectedFormat,
            'width' => $imageInfo[0],
            'height' => $imageInfo[1]
        ];
    }

    /**
     * Réencode l'image pour éliminer tout code malveillant
     */
    public static function sanitizeImage($filePath) {
        $image = imagecreatefromstring(file_get_contents($filePath));
        if ($image === false) {
            throw new RuntimeException("Impossible de traiter l'image");
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $clean = imagecreatetruecolor($width, $height);
        imagecopy($clean, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);

        imagejpeg($clean, $filePath, 85);
        imagedestroy($clean);

        return true;
    }

    /**
     * Obtient l'extension sécurisée basée sur le MIME type
     */
    public static function getExtensionFromMime($mimeType) {
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif'
        ];

        return $mimeToExt[$mimeType] ?? 'bin';
    }

    /**
     * Valide et sauvegarde un fichier uploadé
     */
    public static function saveUploadedFile($uploadedFile, $destinationDir, $userId) {
        self::createSecureDirectory($destinationDir);

        // Valider le fichier
        $imageInfo = self::validateUploadedFile($uploadedFile);

        $tempFile = $uploadedFile['tmp_name'];
        self::sanitizeImage($tempFile);

        $fileHash = bin2hex(random_bytes(16));
        $extension = self::getExtensionFromMime($imageInfo['mime']);
        $filename = $fileHash . '.' . $extension;
        $filepath = $destinationDir . '/' . $filename;

        if (!move_uploaded_file($tempFile, $filepath)) {
            throw new RuntimeException("Erreur lors de la sauvegarde du fichier");
        }

        chmod($filepath, self::SAFE_FILE_PERMISSIONS);

        return $filename;
    }
}
?>
