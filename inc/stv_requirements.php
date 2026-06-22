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

/**
 * Return only hard requirement errors from the extended requirement checks.
 */
function lc_stv_collect_requirement_errors(bool $force_refresh = false): array {
    $report = lc_stv_get_requirement_report($force_refresh);

    return $report['errors'];
}

/**
 * Return only warnings from the extended requirement checks.
 */
function lc_stv_collect_requirement_warnings(bool $force_refresh = false): array {
    $report = lc_stv_get_requirement_report($force_refresh);

    return $report['warnings'];
}

/**
 * Build the requirement report.
 *
 * The rewrite probe is checked automatically and cached for a short time.
 */
function lc_stv_get_requirement_report(bool $force_refresh = false): array {
    if ($force_refresh || lc_stv_should_refresh_rewrite_check()) {
        lc_stv_run_rewrite_check();
    }

    return lc_stv_build_requirement_report();
}

/**
 * Reset stored requirement state.
 */
function lc_stv_clear_requirement_report_cache(): void {
    lc_stv_reset_rewrite_check_state();
}

/**
 * Return the rewrite state option key.
 */
function lc_stv_get_rewrite_check_option_key(): string {
    return 'lc_stv_rewrite_check_state_v2';
}

/**
 * True when the plugin-local rewrite probe file exists.
 */
function lc_stv_has_rewrite_probe_file(): bool {
    return is_file(lc_stv_get_rewrite_probe_file());
}

/**
 * True when the plugin-local .htaccess contains the expected probe rule.
 */
function lc_stv_has_rewrite_probe_rule(): bool {
    $file = lc_stv_get_plugin_htaccess_file();

    if (!is_file($file)) {
        return false;
    }

    $content = @file_get_contents($file);

    if (!is_string($content) || $content === '') {
        return false;
    }

    $expected = 'RewriteRule ^' . str_replace('.', '\.', lc_stv_get_rewrite_probe_route()) . '$ ' . basename(lc_stv_get_rewrite_probe_file()) . ' [L]';

    return (stripos($content, $expected) !== false);
}

/**
 * Return the default rewrite check state.
 */
function lc_stv_get_default_rewrite_check_state(): array {
    return [
        'status' => 'unknown',
        'checked_at' => '',
        'checked_at_ts' => 0,
        'http_code' => 0,
        'message' => '',
        'error_type' => '',
    ];
}

/**
 * Normalize and return the stored rewrite check state.
 */
function lc_stv_get_rewrite_check_state(): array {
    $state = get_option(lc_stv_get_rewrite_check_option_key(), []);

    if (!is_array($state)) {
        return lc_stv_get_default_rewrite_check_state();
    }

    $state = array_merge(lc_stv_get_default_rewrite_check_state(), $state);

    if (!in_array($state['status'], ['unknown', 'ok', 'failed', 'skipped'], true)) {
        $state['status'] = 'unknown';
    }

    $state['checked_at'] = (string) $state['checked_at'];
    $state['checked_at_ts'] = (int) $state['checked_at_ts'];
    $state['http_code'] = (int) $state['http_code'];
    $state['message'] = (string) $state['message'];
    $state['error_type'] = (string) $state['error_type'];

    return $state;
}

/**
 * Persist the rewrite check state.
 */
function lc_stv_update_rewrite_check_state(array $state): void {
    $state = array_merge(lc_stv_get_default_rewrite_check_state(), $state);

    if ($state['checked_at'] === '') {
        $state['checked_at'] = current_time('mysql');
    }

    if ($state['checked_at_ts'] <= 0) {
        $state['checked_at_ts'] = time();
    }

    update_option(lc_stv_get_rewrite_check_option_key(), $state, false);
}

/**
 * Reset the rewrite check state.
 */
function lc_stv_reset_rewrite_check_state(): void {
    delete_option(lc_stv_get_rewrite_check_option_key());
}

/**
 * Return the rewrite check TTL in seconds.
 */
function lc_stv_get_rewrite_check_ttl(): int {
    return 900;
}

/**
 * Decide whether the cached rewrite check should be refreshed.
 */
function lc_stv_should_refresh_rewrite_check(): bool {
    $state = lc_stv_get_rewrite_check_state();

    if ($state['checked_at_ts'] <= 0) {
        return true;
    }

    return ((time() - $state['checked_at_ts']) >= lc_stv_get_rewrite_check_ttl());
}

/**
 * Build the full requirement report.
 */
function lc_stv_build_requirement_report(): array {
    $errors = [];
    $warnings = [];

    $server_type = lc_stv_get_server_type();
    $rewrite_state = lc_stv_get_rewrite_check_state();

    if (!lc_stv_is_apache_or_litespeed()) {
        $errors[] = 'STV requires Apache or LiteSpeed with working .htaccess support in the plugin directory. The current server environment is not supported.';
    } elseif ($rewrite_state['status'] === 'failed') {
        $errors[] = ($rewrite_state['message'] !== '') ? $rewrite_state['message'] : 'STV could not verify the plugin-local rewrite probe URL.';
    } elseif ($rewrite_state['status'] === 'unknown') {
        $errors[] = 'STV could not verify the plugin-local rewrite probe URL yet.';
    }

    $prepend_file = function_exists('lc_stv_get_existing_auto_prepend_file') ? lc_stv_get_existing_auto_prepend_file() : lc_stv_get_ini_auto_prepend_file();
    $capture_enabled = function_exists('lc_stv_is_enabled') && lc_stv_is_enabled();

    if ($prepend_file === '') {
        $prepend_config = function_exists('lc_stv_get_prepend_configuration_status') ? lc_stv_get_prepend_configuration_status() : [];

        if (!empty($prepend_config['prepared'])) {
            $methods = !empty($prepend_config['methods']) && is_array($prepend_config['methods']) ? implode(', ', $prepend_config['methods']) : 'unknown';
            $errors[] = 'STV request capture is not active yet. STV has prepared the auto_prepend_file setup (' . $methods . '), but PHP has not applied it in this request. On .user.ini based setups this can take a few minutes. If the error remains, the hosting environment does not apply the prepared auto_prepend_file configuration.';
        } elseif ($capture_enabled) {
            $errors[] = 'STV request capture is enabled, but no auto_prepend_file is currently active and no STV-managed prepend setup was detected. Disable and re-enable STV to rebuild the managed prepend configuration, or configure auto_prepend_file manually to point to stv_prepend.php.';
        } else {
            $errors[] = 'STV request capture is currently disabled, but no STV-managed auto_prepend_file setup was detected. Enable STV to create the managed prepend configuration, or configure auto_prepend_file manually to point to stv_prepend.php.';
        }
    } elseif (!lc_stv_is_own_prepend_active($prepend_file)) {
        if (function_exists('lc_stv_is_wordfence_prepend') && lc_stv_is_wordfence_prepend($prepend_file)) {
            $errors[] = 'STV request capture is inactive because Wordfence currently owns auto_prepend_file. STV does not overwrite Wordfence. Disable Wordfence firewall optimization first or configure a compatible wrapper manually.';
        } else {
            $errors[] = 'STV request capture is inactive because another auto_prepend_file is configured: ' . $prepend_file . '. STV does not overwrite foreign prepend setups. Remove or review the existing prepend configuration before enabling STV capture.';
        }
    }

    $headers = lc_stv_fetch_main_document_headers();

    if (!empty($headers)) {
        if (lc_stv_detect_reverse_proxy($headers)) {
            $warnings[] = 'A CDN was detected in front of the origin. If the main document is cached there, STV cannot reliably observe all real page requests because some of them may never reach the server.';
        }

        $cache_error = lc_stv_get_main_document_cache_error($headers);

        if ($cache_error !== '') {
            $errors[] = $cache_error;
        }
    }

    return [
        'errors' => array_values(array_unique($errors)),
        'warnings' => array_values(array_unique($warnings)),
        'meta' => [
            'server_type' => $server_type,
            'checked_at' => current_time('mysql'),
            'rewrite_state' => $rewrite_state,
            'rewrite_probe_url' => lc_stv_get_rewrite_probe_url(),
            'prepend_debug' => function_exists('lc_stv_get_prepend_detection_debug') ? lc_stv_get_prepend_detection_debug() : [],
        ],
    ];
}

/**
 * Read auto_prepend_file directly from ini if needed.
 */
function lc_stv_get_ini_auto_prepend_file(): string {
    $prepend = (string) ini_get('auto_prepend_file');

    if ($prepend === '' || strtolower($prepend) === 'none') {
        return '';
    }

    return $prepend;
}

/**
 * Detect the local server type.
 */
function lc_stv_get_server_type(): string {
    $sapi = strtolower((string) PHP_SAPI);
    $software = strtolower(lc_stv_get_server_or_env_text('SERVER_SOFTWARE'));
    $lsws = strtolower(lc_stv_get_server_or_env_text('LSWS_EDITION'));

    if ($lsws !== '' && strpos($lsws, 'openlitespeed') === 0) {
        return 'openlitespeed';
    }

    if ($software !== '' && strpos($software, 'openlitespeed') !== false) {
        return 'openlitespeed';
    }

    if ($sapi === 'litespeed' || $sapi === 'lsapi') {
        return 'litespeed';
    }

    if ($software !== '' && strpos($software, 'litespeed') !== false && strpos($software, 'openlitespeed') === false) {
        return 'litespeed';
    }

    if ($sapi === 'apache2handler') {
        return 'apache';
    }

    if ($software !== '' && strpos($software, 'apache') !== false && strpos($software, 'litespeed') === false) {
        return 'apache';
    }

    if (function_exists('apache_get_version') || function_exists('apache_get_modules')) {
        return 'apache';
    }

    if ($software !== '' && strpos($software, 'nginx') !== false) {
        return 'nginx';
    }

    return 'unknown';
}

/**
 * True for Apache, LiteSpeed and OpenLiteSpeed.
 */
function lc_stv_is_apache_or_litespeed(): bool {
    $type = lc_stv_get_server_type();

    return in_array($type, ['apache', 'litespeed', 'openlitespeed'], true);
}

/**
 * True only for plain nginx detection.
 */
function lc_stv_is_nginx_only(): bool {
    return (lc_stv_get_server_type() === 'nginx');
}

/**
 * Run and persist the rewrite check.
 */
function lc_stv_run_rewrite_check(): array {
    if (!lc_stv_is_apache_or_litespeed()) {
        $state = [
            'status' => 'skipped',
            'checked_at' => current_time('mysql'),
            'checked_at_ts' => time(),
            'http_code' => 0,
            'message' => 'Rewrite verification was skipped because the current server environment is not supported.',
            'error_type' => 'unsupported_server',
        ];

        lc_stv_update_rewrite_check_state($state);

        return lc_stv_get_rewrite_check_state();
    }

    if (!is_file(lc_stv_get_plugin_htaccess_file())) {
        $state = [
            'status' => 'failed',
            'checked_at' => current_time('mysql'),
            'checked_at_ts' => time(),
            'http_code' => 0,
            'message' => 'The STV plugin .htaccess file is missing. The plugin-local rewrite probe cannot work without it. Re-activate STV to rebuild the plugin directory files.',
            'error_type' => 'plugin_htaccess_missing',
        ];

        lc_stv_update_rewrite_check_state($state);

        return lc_stv_get_rewrite_check_state();
    }

    if (!lc_stv_has_rewrite_probe_file()) {
        $state = [
            'status' => 'failed',
            'checked_at' => current_time('mysql'),
            'checked_at_ts' => time(),
            'http_code' => 0,
            'message' => 'The STV rewrite probe file is missing from the plugin directory. Re-activate STV to rebuild the plugin directory files.',
            'error_type' => 'rewrite_probe_file_missing',
        ];

        lc_stv_update_rewrite_check_state($state);

        return lc_stv_get_rewrite_check_state();
    }

    if (!lc_stv_has_rewrite_probe_rule()) {
        $state = [
            'status' => 'failed',
            'checked_at' => current_time('mysql'),
            'checked_at_ts' => time(),
            'http_code' => 0,
            'message' => 'The STV plugin .htaccess file does not contain the expected fixed rewrite probe rule. Re-activate STV to rebuild the plugin directory rewrite setup.',
            'error_type' => 'rewrite_probe_rule_missing',
        ];

        lc_stv_update_rewrite_check_state($state);

        return lc_stv_get_rewrite_check_state();
    }

    $result = lc_stv_request_rewrite_probe_url(lc_stv_get_rewrite_probe_url());

    $state = [
        'status' => $result['ok'] ? 'ok' : 'failed',
        'checked_at' => current_time('mysql'),
        'checked_at_ts' => time(),
        'http_code' => (int) $result['http_code'],
        'message' => (string) $result['message'],
        'error_type' => (string) $result['error_type'],
    ];

    lc_stv_update_rewrite_check_state($state);

    return lc_stv_get_rewrite_check_state();
}

/**
 * Perform the plugin-local rewrite probe request.
 */
function lc_stv_request_rewrite_probe_url(string $url): array {
    $result = [
        'ok' => false,
        'http_code' => 0,
        'message' => '',
        'error_type' => '',
    ];

    $url = add_query_arg(
            [
                'lc_stv_probe_ts' => rawurlencode((string) microtime(true)),
            ],
            $url
    );

    $response = wp_remote_get($url, [
        'timeout' => 10,
        'redirection' => 0,
        'headers' => [
            'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Accept' => 'text/plain',
        ],
        'user-agent' => 'LC_STV_Rewrite_Probe/2.0',
    ]);

    if (is_wp_error($response)) {
        $result['error_type'] = 'rewrite_probe_wp_error';
        $result['message'] = 'The STV plugin-local rewrite probe request failed: ' . $response->get_error_message() . ' Check whether the site can perform local loopback requests.';

        return $result;
    }

    $result['http_code'] = (int) wp_remote_retrieve_response_code($response);

    if ($result['http_code'] === 200) {
        $result['ok'] = true;
        $result['message'] = 'Rewrite verification succeeded.';

        return $result;
    }

    if ($result['http_code'] === 404) {
        $result['error_type'] = 'rewrite_probe_http_404';
        $result['message'] = 'The STV plugin-local rewrite probe URL returned HTTP 404. The plugin directory rewrite rule is not active. Re-activate STV to rebuild the plugin directory files and verify that Apache or LiteSpeed allows .htaccess rewrites inside the plugin directory.';

        return $result;
    }

    $result['error_type'] = 'rewrite_probe_http_' . $result['http_code'];
    $result['message'] = 'The STV plugin-local rewrite probe URL returned HTTP ' . $result['http_code'] . '.';

    return $result;
}

/**
 * Backward-compatible alias for the rewrite check.
 */
function lc_stv_verify_htaccess_rewrite_support(): array {
    $state = lc_stv_run_rewrite_check();

    return [
        'ok' => ($state['status'] === 'ok'),
        'http_code' => (int) $state['http_code'],
        'message' => (string) $state['message'],
    ];
}

/**
 * Fetch headers for the main document.
 */
function lc_stv_fetch_main_document_headers(): array {
    $url = home_url('/');

    $response = wp_remote_get($url, [
        'timeout' => 15,
        'redirection' => 0,
        'headers' => [
            'Cache-Control' => 'no-cache, max-age=0',
            'Pragma' => 'no-cache',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ],
        'user-agent' => 'LC_STV_Requirement_Check/1.0 kitt_runner',
    ]);

    if (is_wp_error($response)) {
        return [];
    }

    $headers = wp_remote_retrieve_headers($response);

    return lc_stv_normalize_headers($headers);
}

/**
 * Normalize WP response headers to a lowercase array.
 */
function lc_stv_normalize_headers($headers): array {
    $out = [];

    if (!is_object($headers) && !is_array($headers)) {
        return $out;
    }

    foreach ($headers as $name => $value) {
        $key = strtolower((string) $name);

        if (is_array($value)) {
            $out[$key] = array_values(array_map('strval', $value));
            continue;
        }

        $out[$key] = [(string) $value];
    }

    return $out;
}

/**
 * Return the first header value or an empty string.
 */
function lc_stv_get_first_header_value(array $headers, string $name): string {
    $key = strtolower($name);

    if (empty($headers[$key]) || !isset($headers[$key][0])) {
        return '';
    }

    return trim((string) $headers[$key][0]);
}

/**
 * Detect a CDN or reverse proxy from pragmatic response headers.
 */
function lc_stv_detect_reverse_proxy(array $headers): bool {
    if (isset($headers['cf-cache-status'])) {
        return true;
    }

    if (isset($headers['cf-ray'])) {
        return true;
    }

    if (isset($headers['x-cache'])) {
        return true;
    }

    if (isset($headers['via'])) {
        return true;
    }

    $server = lc_stv_get_first_header_value($headers, 'server');

    if ($server !== '') {
        $server_lc = strtolower($server);

        if (
                strpos($server_lc, 'cloudflare') !== false || strpos($server_lc, 'varnish') !== false || strpos($server_lc, 'sucuri') !== false || strpos($server_lc, 'cdn') !== false || strpos($server_lc, 'proxy') !== false
        ) {
            return true;
        }
    }

    return false;
}

/**
 * Return an error for harmful main document browser caching.
 */
function lc_stv_get_main_document_cache_error(array $headers): string {
    $cache_control = strtolower(lc_stv_get_first_header_value($headers, 'cache-control'));

    if ($cache_control !== '') {
        if (preg_match('/(?:^|,)\s*max-age\s*=\s*(\d+)/i', $cache_control, $m)) {
            $max_age = (int) $m[1];

            if ($max_age > 0) {
                return 'Browser caching for the main document was detected. STV request analysis becomes unreliable while the HTML document is cached by the browser. Remove Cache-Control max-age for the main document before using STV.';
            }
        }
    }

    $expires = lc_stv_get_first_header_value($headers, 'expires');

    if ($expires !== '') {
        $expires_ts = strtotime($expires);

        if (is_int($expires_ts) && $expires_ts > time()) {
            return 'A future Expires header was detected for the main document. STV request analysis becomes unreliable while the HTML document is cached by the browser. Remove browser caching for the main document before using STV.';
        }
    }

    return '';
}
