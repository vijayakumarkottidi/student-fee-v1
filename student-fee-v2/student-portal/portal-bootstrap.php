<?php
/**
 * Shared bootstrap helpers for the HITICX student portal front-end.
 */

if (!function_exists('hiticx_portal_bootstrap')) {
    /**
     * Prepare the runtime for the standalone student portal endpoints.
     */
    function hiticx_portal_bootstrap(): void
    {
        hiticx_portal_enable_error_reporting();
        hiticx_portal_load_wordpress();
        hiticx_portal_ensure_session();
    }

    /**
     * Ensure PHP errors are surfaced while the portal runs independently.
     */
    function hiticx_portal_enable_error_reporting(): void
    {
        if (function_exists('ini_set')) {
            ini_set('display_errors', '1');
        }

        error_reporting(E_ALL);
    }

    /**
     * Attempt to load the WordPress bootstrap so the portal can use WP APIs.
     */
    function hiticx_portal_load_wordpress(): void
    {
        if (defined('ABSPATH')) {
            return;
        }

        $candidates = [];
        $baseDir = __DIR__;

        for ($depth = 0; $depth <= 6; $depth++) {
            $candidate = rtrim(str_replace('\\', '/', dirname($baseDir, $depth)), '/');
            if ($candidate !== '') {
                $candidates[] = $candidate . '/wp-load.php';
            }
        }

        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $documentRoot = rtrim(str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']), '/');
            if ($documentRoot !== '') {
                $candidates[] = $documentRoot . '/wp-load.php';
            }
        }

        $candidates = array_unique(array_filter($candidates));

        foreach ($candidates as $file) {
            if (file_exists($file)) {
                require_once $file;
                if (defined('ABSPATH')) {
                    return;
                }
            }
        }

        $message = '<div style="text-align:center;padding:40px;font-family:Arial,sans-serif">'
            . '<h1>⚠️ WordPress Bootstrap Missing</h1>'
            . '<p>The system could not locate <code>wp-load.php</code>. Searched paths:</p>'
            . '<ul style="list-style:disc;display:inline-block;text-align:left">';

        foreach ($candidates as $file) {
            $message .= '<li>' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '</li>';
        }

        $message .= '</ul>'
            . '<p>Please verify the portal is installed inside a valid WordPress environment.</p>'
            . '</div>';

        if (function_exists('wp_die')) {
            wp_die($message);
        }

        exit($message);
    }

    /**
     * Start a PHP session if one has not already been initialised.
     */
    function hiticx_portal_ensure_session(): void
    {
        if (!session_id()) {
            session_start();
        }
    }
}
