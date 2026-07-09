<?php
// Supprime les caches applicatifs/résidus côté serveur et force des headers no-cache.
if (!defined('WTC_CLEAR_CACHE_INCLUDED')) {
    define('WTC_CLEAR_CACHE_INCLUDED', true);

    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
        header('Pragma: no-cache');
        header('Expires: -1');
        header('Surrogate-Control: no-store');
        header('X-Accel-Expires: 0');
    }

    clearstatcache(true);

    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }

    if (function_exists('apc_clear_cache')) {
        @apc_clear_cache();
        @apc_clear_cache('user');
    }

    if (function_exists('apcu_clear_cache')) {
        @apcu_clear_cache();
    }
}
