<?php
if (defined('WTC_ERROR_HANDLER_LOADED')) {
    return;
}
define('WTC_ERROR_HANDLER_LOADED', true);

function wtc_error_reference(): string
{
    if (empty($_SERVER['WTC_ERROR_REFERENCE'])) {
        $_SERVER['WTC_ERROR_REFERENCE'] = strtoupper(bin2hex(random_bytes(4)));
    }

    return $_SERVER['WTC_ERROR_REFERENCE'];
}

function wtc_render_error_page(?string $reference = null)
{
    $errorPage = dirname(__DIR__, 2) . '/error.php';
    $reference = $reference ?? wtc_error_reference();

    if (php_sapi_name() === 'cli') {
        echo "Une erreur technique est survenue. Référence : {$reference}\n";
        exit(1);
    }

    $_SERVER['WTC_ERROR_REFERENCE'] = $reference;

    // If request expects JSON (AJAX/API), return JSON instead of redirect
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

    if (!headers_sent()) {
        http_response_code(500);
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'Un problème technique est survenu. Réessaie dans quelques instants.',
                'reference' => $reference,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (file_exists($errorPage)) {
            require $errorPage;
        } else {
            echo "Un problème technique est survenu. Référence : {$reference}";
        }
        exit;
    }

    if (file_exists($errorPage)) {
        require $errorPage;
    } else {
        echo "Un problème technique est survenu. Référence : {$reference}";
    }
    exit;
}

// Log PHP diagnostics without allowing server details to leak into responses.
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return true;
    }

    $reference = wtc_error_reference();
    error_log("[PHP ERROR {$reference}] {$message} in {$file}:{$line}");

    if (in_array($severity, [E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
        wtc_render_error_page($reference);
    }

    return true;
});

set_exception_handler(function ($e) {
    $reference = wtc_error_reference();
    error_log("[UNCAUGHT EXCEPTION {$reference}] " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    wtc_render_error_page($reference);
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_USER_ERROR], true)) {
        $reference = wtc_error_reference();
        error_log("[FATAL SHUTDOWN {$reference}] " . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
        wtc_render_error_page($reference);
    }
});

?>
