<?php
if (defined('WTC_ERROR_HANDLER_LOADED')) {
    return;
}
define('WTC_ERROR_HANDLER_LOADED', true);

function wtc_get_base_path()
{
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    if ($base === '' || $base === '.') {
        return '';
    }
    return $base;
}

function wtc_render_error_page()
{
    $base = wtc_get_base_path();
    $location = ($base === '') ? '/error.php' : ($base . '/error.php');

    if (php_sapi_name() === 'cli') {
        echo "Une erreur est survenue.\n";
        exit(1);
    }

    // If request expects JSON (AJAX/API), return JSON instead of redirect
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

    if (!headers_sent()) {
        http_response_code(500);
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Une erreur est survenue.']);
            exit;
        }
        header('Location: ' . $location);
        exit;
    }

    $errorPage = dirname(__DIR__, 2) . '/error.php';
    if (file_exists($errorPage)) {
        readfile($errorPage);
    } else {
        echo "Une erreur est survenue.";
    }
    exit;
}

// Non-fatal errors are logged but allowed to proceed to PHP's default handler.
set_error_handler(function ($severity, $message, $file, $line) {
    error_log("[PHP ERROR] $message in $file on line $line");
    return false; // Let PHP handle non-fatal errors normally
});

set_exception_handler(function ($e) {
    error_log("[UNCAUGHT EXCEPTION] " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    wtc_render_error_page();
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_USER_ERROR], true)) {
        error_log("[FATAL SHUTDOWN] " . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
        wtc_render_error_page();
    }
});

?>
