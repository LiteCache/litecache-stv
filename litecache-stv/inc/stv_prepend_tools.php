<?php

defined('ABSPATH') || exit;

if (!function_exists('lc_stv_get_server_or_env_text')) {

    function lc_stv_get_server_or_env_text(string $StvKey): string {
        $StvValue = filter_input(INPUT_SERVER, $StvKey, FILTER_UNSAFE_RAW, FILTER_NULL_ON_FAILURE);

        if (is_string($StvValue) && $StvValue !== '') {
            return sanitize_text_field(wp_unslash($StvValue));
        }

        $StvValue = filter_input(INPUT_ENV, $StvKey, FILTER_UNSAFE_RAW, FILTER_NULL_ON_FAILURE);

        if (is_string($StvValue) && $StvValue !== '') {
            return sanitize_text_field(wp_unslash($StvValue));
        }

        return '';
    }

}

if (!function_exists('lc_stv_get_prepend_file')) {

    function lc_stv_get_prepend_file(): string {
        return LC_STV_DIR . 'stv_prepend.php';
    }

}

if (!function_exists('lc_stv_get_existing_auto_prepend_file')) {

    function lc_stv_get_existing_auto_prepend_file(): string {
        $StvValue = (string) ini_get('auto_prepend_file');
        $StvValue = trim($StvValue);

        if ($StvValue === '') {
            return '';
        }

        return trim($StvValue, " \"'");
    }

}

if (!function_exists('lc_stv_normalize_file_path')) {

    function lc_stv_normalize_file_path(string $path): string {
        $StvPath = trim($path);

        if ($StvPath === '') {
            return '';
        }

        $StvRealPath = realpath($StvPath);

        if (is_string($StvRealPath) && $StvRealPath !== '') {
            $StvPath = $StvRealPath;
        }

        return str_replace('\\', '/', $StvPath);
    }

}

if (!function_exists('lc_stv_is_wordfence_prepend')) {

    function lc_stv_is_wordfence_prepend(string $path): bool {
        $StvPath = lc_stv_normalize_file_path($path);

        if ($StvPath === '') {
            return false;
        }

        return (strpos(strtolower($StvPath), 'wordfence') !== false);
    }

}

if (!function_exists('lc_stv_is_own_prepend_active')) {

    function lc_stv_is_own_prepend_active(string $path = ''): bool {
        $StvCurrent = ($path === '') ? lc_stv_get_existing_auto_prepend_file() : $path;
        $StvExpected = lc_stv_normalize_file_path(lc_stv_get_prepend_file());
        $StvCurrent = lc_stv_normalize_file_path($StvCurrent);

        return ($StvExpected !== '' && $StvCurrent !== '' && $StvExpected === $StvCurrent);
    }

}

function lc_stv_get_prepend_mode(): string {
    $StvPrependFile = lc_stv_get_existing_auto_prepend_file();

    if ($StvPrependFile === '') {
        return 'none';
    }

    if (lc_stv_is_wordfence_prepend($StvPrependFile)) {
        return 'wordfence';
    }

    return 'custom';
}

function lc_stv_can_prepare_prepend(): bool {
    $StvErrors = lc_stv_get_requirement_errors();

    return empty($StvErrors);
}

function lc_stv_get_prepend_status(): array {
    $StvPrependMode = lc_stv_get_prepend_mode();
    $StvPrependFile = lc_stv_get_prepend_file();

    return [
        'mode' => $StvPrependMode,
        'target_file' => $StvPrependFile,
        'target_file_exists' => lc_stv_has_prepend_file(),
        'can_prepare_prepend' => lc_stv_can_prepare_prepend(),
    ];
}

function lc_stv_get_plugin_htaccess_file(): string {
    return LC_STV_DIR . '.htaccess';
}

function lc_stv_get_rewrite_probe_file(): string {
    return LC_STV_DIR . 'stv-rewrite-probe.txt';
}

function lc_stv_get_rewrite_probe_token(): string {
    $StvBasePath = str_replace('\\', '/', (string) ABSPATH);

    return substr(md5($StvBasePath), 0, 12);
}

function lc_stv_get_rewrite_probe_route(): string {
    return 'stv-probe-' . lc_stv_get_rewrite_probe_token() . '.txt';
}

function lc_stv_get_rewrite_probe_url(): string {
    return LC_STV_URL . lc_stv_get_rewrite_probe_route();
}

function lc_stv_get_rewrite_probe_body(): string {
    return "LiteCache STV rewrite probe\n";
}

function lc_stv_get_plugin_htaccess_content(): string {
    $StvRoute = str_replace('.', '\\.', lc_stv_get_rewrite_probe_route());
    $StvProbeFile = basename(lc_stv_get_rewrite_probe_file());

    $StvLines = [
        'RewriteEngine On',
        'RewriteRule ^stv_prepend\.php$ - [F,L]',
        'RewriteRule ^' . $StvRoute . '$ ' . $StvProbeFile . ' [L]',
    ];

    return implode(PHP_EOL, $StvLines) . PHP_EOL;
}

function lc_stv_prepare_rewrite_probe_configuration(): array {
    $StvResult = [
        'status' => 'prepared',
        'errors' => [],
        'probe_file' => lc_stv_get_rewrite_probe_file(),
        'plugin_htaccess_file' => lc_stv_get_plugin_htaccess_file(),
        'probe_url' => lc_stv_get_rewrite_probe_url(),
    ];

    if (@file_put_contents(lc_stv_get_rewrite_probe_file(), lc_stv_get_rewrite_probe_body(), LOCK_EX) === false) {
        $StvResult['errors'][] = 'The STV rewrite probe file could not be written.';
    }

    if (@file_put_contents(lc_stv_get_plugin_htaccess_file(), lc_stv_get_plugin_htaccess_content(), LOCK_EX) === false) {
        $StvResult['errors'][] = 'The STV plugin .htaccess file could not be written.';
    }

    if (!empty($StvResult['errors'])) {
        $StvResult['status'] = 'failed';
    }

    return $StvResult;
}

function lc_stv_get_rewrite_probe_http_status(): int {
    if (!function_exists('wp_remote_get')) {
        return 0;
    }

    $StvResponse = wp_remote_get(lc_stv_get_rewrite_probe_url(), [
        'timeout' => 5,
        'redirection' => 0,
        'headers' => [
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
        ],
    ]);

    if (is_wp_error($StvResponse)) {
        return 0;
    }

    return (int) wp_remote_retrieve_response_code($StvResponse);
}

function lc_stv_get_rewrite_probe_status(): array {
    $StvPrepared = lc_stv_prepare_rewrite_probe_configuration();

    if (($StvPrepared['status'] ?? '') === 'failed') {
        return [
            'status' => 'prepare_failed',
            'http_status' => 0,
            'probe_url' => lc_stv_get_rewrite_probe_url(),
            'errors' => $StvPrepared['errors'] ?? [],
        ];
    }

    $StvHttpStatus = lc_stv_get_rewrite_probe_http_status();

    return [
        'status' => ($StvHttpStatus === 200) ? 'ok' : 'http_error',
        'http_status' => $StvHttpStatus,
        'probe_url' => lc_stv_get_rewrite_probe_url(),
        'errors' => [],
    ];
}

function lc_stv_get_rewrite_probe_warning(): string {
    $StvStatus = lc_stv_get_rewrite_probe_status();

    if (($StvStatus['status'] ?? '') === 'ok') {
        return '';
    }

    if (($StvStatus['status'] ?? '') === 'prepare_failed') {
        return 'The STV plugin-local rewrite probe could not be prepared. Please check write permissions for the STV plugin directory.';
    }

    $StvHttpStatus = (int) ($StvStatus['http_status'] ?? 0);

    if ($StvHttpStatus > 0) {
        return 'The STV plugin-local rewrite probe did not return HTTP 200. Current status: ' . $StvHttpStatus . '.';
    }

    return 'The STV plugin-local rewrite probe could not be reached with HTTP 200.';
}

function lc_stv_has_prepend_protection_rule(): bool {
    $StvHtaccessFile = lc_stv_get_plugin_htaccess_file();

    if (!is_file($StvHtaccessFile)) {
        return false;
    }

    $StvContent = @file_get_contents($StvHtaccessFile);

    if (!is_string($StvContent) || $StvContent === '') {
        return false;
    }

    return (stripos($StvContent, 'RewriteRule ^stv_prepend\.php$ - [F,L]') !== false);
}

function lc_stv_has_prepend_file(): bool {
    return is_file(lc_stv_get_prepend_file());
}

function lc_stv_get_root_htaccess_file(): string {
    return ABSPATH . '.htaccess';
}

function lc_stv_get_user_ini_file(): string {
    return ABSPATH . '.user.ini';
}

function lc_stv_get_auto_prepend_target_file(): string {
    return lc_stv_get_prepend_file();
}

function lc_stv_get_prepend_marker_begin(string $type): string {
    return '# BEGIN LiteCache STV auto_prepend ' . $type;
}

function lc_stv_get_prepend_marker_end(string $type): string {
    return '# END LiteCache STV auto_prepend ' . $type;
}

function lc_stv_quote_ini_path(string $path): string {
    return '"' . str_replace('"', '\\"', $path) . '"';
}

function lc_stv_get_htaccess_prepend_block(): string {
    $TargetFile = lc_stv_quote_ini_path(lc_stv_get_auto_prepend_target_file());

    $Lines = [
        lc_stv_get_prepend_marker_begin('htaccess'),
        '<IfModule mod_headers.c>',
        'Header always merge Accept-CH "Device-Memory"',
        'Header always merge Accept-CH "Sec-CH-UA-Full-Version-List"',
        '</IfModule>',
        'php_value auto_prepend_file ' . $TargetFile,
        lc_stv_get_prepend_marker_end('htaccess'),
    ];

    return implode(PHP_EOL, $Lines) . PHP_EOL;
}

function lc_stv_get_user_ini_prepend_block(): string {
    $TargetFile = lc_stv_quote_ini_path(lc_stv_get_auto_prepend_target_file());

    $Lines = [
        lc_stv_get_prepend_marker_begin('user_ini'),
        'auto_prepend_file = ' . $TargetFile,
        lc_stv_get_prepend_marker_end('user_ini'),
    ];

    return implode(PHP_EOL, $Lines) . PHP_EOL;
}

function lc_stv_strip_managed_prepend_block(string $content, string $type): string {
    $Begin = preg_quote(lc_stv_get_prepend_marker_begin($type), '/');
    $End = preg_quote(lc_stv_get_prepend_marker_end($type), '/');

    return (string) preg_replace('/\R?' . $Begin . '.*?' . $End . '\R?/s', PHP_EOL, $content);
}

function lc_stv_file_has_foreign_prepend_directive(string $file, string $type): bool {
    if (!is_file($file)) {
        return false;
    }

    $Content = @file_get_contents($file);

    if (!is_string($Content) || $Content === '') {
        return false;
    }

    $Content = lc_stv_strip_managed_prepend_block($Content, $type);

    if ($type === 'htaccess') {
        return (bool) preg_match('/^\s*php_(?:admin_)?value\s+auto_prepend_file\b/im', $Content);
    }

    return (bool) preg_match('/^\s*auto_prepend_file\s*=/im', $Content);
}

function lc_stv_write_managed_prepend_block(string $file, string $type, string $block): bool {
    $Content = '';

    if (is_file($file)) {
        $ExistingContent = @file_get_contents($file);

        if (!is_string($ExistingContent)) {
            return false;
        }

        $Content = $ExistingContent;
    }

    $Content = lc_stv_strip_managed_prepend_block($Content, $type);
    $Content = ltrim($Content);

    $Block = rtrim($block) . PHP_EOL;

    if ($Content === '') {
        return (@file_put_contents($file, $Block, LOCK_EX) !== false);
    }

    return (@file_put_contents($file, $Block . PHP_EOL . $Content, LOCK_EX) !== false);
}

function lc_stv_user_ini_is_supported(): bool {
    $Filename = (string) ini_get('user_ini.filename');

    return ($Filename !== '');
}

function lc_stv_get_prepend_detection_debug(): array {
    return [
        'php_sapi' => strtolower((string) PHP_SAPI),
        'server_type' => function_exists('lc_stv_get_server_type') ? lc_stv_get_server_type() : 'unknown',
        'server_software' => lc_stv_get_server_or_env_text('SERVER_SOFTWARE'),
        'lsws_edition' => lc_stv_get_server_or_env_text('LSWS_EDITION'),
        'user_ini_filename' => (string) ini_get('user_ini.filename'),
        'user_ini_supported' => lc_stv_user_ini_is_supported(),
        'methods' => lc_stv_get_prepend_setup_methods(),
    ];
}

function lc_stv_get_prepend_setup_methods(): array {
    $Sapi = strtolower((string) PHP_SAPI);
    $ServerType = function_exists('lc_stv_get_server_type') ? lc_stv_get_server_type() : 'unknown';

    if (in_array($Sapi, ['litespeed', 'lsapi'], true) || $ServerType === 'litespeed') {
        return lc_stv_user_ini_is_supported() ? ['htaccess', 'user_ini'] : ['htaccess'];
    }

    if ($ServerType === 'openlitespeed') {
        return lc_stv_user_ini_is_supported() ? ['user_ini'] : [];
    }

    if ($Sapi === 'apache2handler') {
        return ['htaccess'];
    }

    if (in_array($Sapi, ['cgi-fcgi', 'fpm-fcgi', 'cgi'], true)) {
        return lc_stv_user_ini_is_supported() ? ['user_ini'] : [];
    }

    return [];
}

function lc_stv_prepare_prepend_configuration(): array {
    $Result = [
        'status' => 'skipped',
        'methods' => [],
        'written' => [],
        'skipped' => [],
        'errors' => [],
        'debug' => lc_stv_get_prepend_detection_debug(),
    ];

    $ActivePrepend = lc_stv_get_existing_auto_prepend_file();

    if ($ActivePrepend !== '' && !lc_stv_is_own_prepend_active($ActivePrepend)) {
        $Result['status'] = lc_stv_is_wordfence_prepend($ActivePrepend) ? 'wordfence_active' : 'foreign_active';
        $Result['errors'][] = 'Another auto_prepend_file is already active. STV did not overwrite it.';
        return $Result;
    }

    $Methods = lc_stv_get_prepend_setup_methods();
    $Result['methods'] = $Methods;

    if (empty($Methods)) {
        $Result['status'] = 'manual_required';
        $Result['errors'][] = 'STV could not detect a supported automatic auto_prepend_file setup method.';
        return $Result;
    }

    foreach ($Methods as $Method) {
        if ($Method === 'htaccess') {
            $File = lc_stv_get_root_htaccess_file();

            if (lc_stv_file_has_foreign_prepend_directive($File, 'htaccess')) {
                $Result['skipped'][] = 'htaccess_foreign_prepend';
                continue;
            }

            if (lc_stv_write_managed_prepend_block($File, 'htaccess', lc_stv_get_htaccess_prepend_block())) {
                $Result['written'][] = 'htaccess';
            } else {
                $Result['errors'][] = 'The root .htaccess file could not be written.';
            }

            continue;
        }

        if ($Method === 'user_ini') {
            $File = lc_stv_get_user_ini_file();

            if (lc_stv_file_has_foreign_prepend_directive($File, 'user_ini')) {
                $Result['skipped'][] = 'user_ini_foreign_prepend';
                continue;
            }

            if (lc_stv_write_managed_prepend_block($File, 'user_ini', lc_stv_get_user_ini_prepend_block())) {
                $Result['written'][] = 'user_ini';
            } else {
                $Result['errors'][] = 'The .user.ini file could not be written.';
            }
        }
    }

    if (!empty($Result['errors'])) {
        $Result['status'] = 'failed';
        return $Result;
    }

    if (!empty($Result['written'])) {
        $Result['status'] = 'prepared';
        return $Result;
    }

    $Result['status'] = 'blocked';
    $Result['errors'][] = 'STV found an existing auto_prepend_file directive in the writable configuration files and did not overwrite it.';

    return $Result;
}

function lc_stv_get_prepend_configuration_status(): array {
    $ActivePrepend = lc_stv_get_existing_auto_prepend_file();
    $OwnActive = ($ActivePrepend !== '' && lc_stv_is_own_prepend_active($ActivePrepend));

    $HtaccessFile = lc_stv_get_root_htaccess_file();
    $UserIniFile = lc_stv_get_user_ini_file();

    $HtaccessManaged = false;
    $UserIniManaged = false;

    if (is_file($HtaccessFile)) {
        $Content = @file_get_contents($HtaccessFile);
        $HtaccessManaged = is_string($Content) && strpos($Content, lc_stv_get_prepend_marker_begin('htaccess')) !== false;
    }

    if (is_file($UserIniFile)) {
        $Content = @file_get_contents($UserIniFile);
        $UserIniManaged = is_string($Content) && strpos($Content, lc_stv_get_prepend_marker_begin('user_ini')) !== false;
    }

    return [
        'active_prepend' => $ActivePrepend,
        'own_active' => $OwnActive,
        'foreign_active' => ($ActivePrepend !== '' && !$OwnActive),
        'is_wordfence' => lc_stv_is_wordfence_prepend($ActivePrepend),
        'htaccess_file' => $HtaccessFile,
        'user_ini_file' => $UserIniFile,
        'htaccess_managed' => $HtaccessManaged,
        'user_ini_managed' => $UserIniManaged,
        'prepared' => ($HtaccessManaged || $UserIniManaged),
        'methods' => lc_stv_get_prepend_setup_methods(),
        'debug' => lc_stv_get_prepend_detection_debug(),
    ];
}
