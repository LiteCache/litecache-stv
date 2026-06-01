<?php

if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
    return;
}

function lc_stv_prepend_contains_any(string $Value, array $Needles): string {
    foreach ($Needles as $Needle) {
        if ($Needle !== '' && stripos($Value, $Needle) !== false) {
            return $Needle;
        }
    }

    return '';
}

function lc_stv_prepend_is_asset_request(string $Uri): bool {
    $Path = (string) parse_url($Uri, PHP_URL_PATH);

    return (bool) preg_match('~\.(?:css|js|mjs|map|jpg|jpeg|png|gif|webp|avif|svg|ico|woff|woff2|ttf|otf|eot|mp4|webm|mov|mp3|wav|pdf|zip|gz|br|xml|txt)$~i', $Path);
}

function lc_stv_prepend_get_request_kind(string $Uri): string {
    if (lc_stv_prepend_is_asset_request($Uri)) {
        return 'asset';
    }

    return 'document';
}

function lc_stv_prepend_has_sec_fetch_triad(): bool {
    return isset($_SERVER['HTTP_SEC_FETCH_SITE'], $_SERVER['HTTP_SEC_FETCH_MODE'], $_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_SITE'] !== '' && $_SERVER['HTTP_SEC_FETCH_MODE'] !== '' && $_SERVER['HTTP_SEC_FETCH_DEST'] !== '';
}

function lc_stv_prepend_get_agent_signature_header(): string {
    if (isset($_SERVER['HTTP_SIGNATURE_AGENT'])) {
        return 'signature-agent';
    }

    if (isset($_SERVER['HTTP_SIGNATURE_INPUT'])) {
        return 'signature-input';
    }

    if (isset($_SERVER['HTTP_SIGNATURE'])) {
        return 'signature';
    }

    if (!function_exists('getallheaders')) {
        return '';
    }

    $Headers = getallheaders();

    if (!is_array($Headers)) {
        return '';
    }

    $Headers = array_change_key_case($Headers, CASE_LOWER);

    if (isset($Headers['signature-agent'])) {
        return 'signature-agent';
    }

    if (isset($Headers['signature-input'])) {
        return 'signature-input';
    }

    if (isset($Headers['signature'])) {
        return 'signature';
    }

    return '';
}

function lc_stv_prepend_get_headless_signal(string $UserAgent): string {
    $HeadlessHaystack = $UserAgent . ' ' .
            (string) ($_SERVER['HTTP_SEC_CH_UA'] ?? '') . ' ' .
            (string) ($_SERVER['HTTP_SEC_CH_UA_FULL_VERSION_LIST'] ?? '');

    return lc_stv_prepend_contains_any($HeadlessHaystack, ['HeadlessChrome', 'Headless']);
}

function lc_stv_prepend_is_safari(string $UserAgent): bool {
    if (stripos($UserAgent, 'Safari/') === false || stripos($UserAgent, 'Version/') === false) {
        return false;
    }

    return lc_stv_prepend_contains_any($UserAgent, ['Chrome/', 'Chromium/', 'CriOS/', 'Edg/', 'EdgiOS/', 'EdgA/', 'OPR/', 'OPT/', 'Firefox/', 'FxiOS/']) === '';
}

function lc_stv_prepend_get_safari_major(string $UserAgent): int {
    if (preg_match('~Version/(\d+)~i', $UserAgent, $Match)) {
        return (int) $Match[1];
    }

    return 0;
}

function lc_stv_prepend_get_legacy_browser_reason(string $UserAgent): string {
    if (lc_stv_prepend_is_safari($UserAgent)) {
        $SafariMajor = lc_stv_prepend_get_safari_major($UserAgent);

        if ($SafariMajor > 0 && $SafariMajor <= 13) {
            return 'legacy_safari_' . $SafariMajor;
        }
    }

    if (preg_match('~(?:MSIE\s+|Trident/)~i', $UserAgent)) {
        return 'legacy_ie';
    }

    $ChromiumMajor = lc_stv_prepend_get_chromium_major($UserAgent);

    if ($ChromiumMajor > 0 && $ChromiumMajor < 140) {
        return 'chromium_lt_140';
    }

    $FirefoxMajor = lc_stv_prepend_get_firefox_major($UserAgent);

    if ($FirefoxMajor > 0 && $FirefoxMajor < 140) {
        return 'firefox_lt_140';
    }

    return '';
}

function lc_stv_prepend_get_chromium_major(string $UserAgent): int {
    if (preg_match('~(?:Chrome|Chromium|CriOS|Edg|OPR)/(\d+)~i', $UserAgent, $Match)) {
        return (int) $Match[1];
    }

    return 0;
}

function lc_stv_prepend_get_sec_ch_ua_chromium_major(string $SecChUa): int {
    if ($SecChUa === '') {
        return 0;
    }

    $Brands = [
        'Google Chrome',
        'Chromium',
        'HeadlessChrome',
        'Microsoft Edge',
    ];

    foreach ($Brands as $Brand) {
        if (preg_match('~"' . preg_quote($Brand, '~') . '"\s*;\s*v="(\d+)"~i', $SecChUa, $Match)) {
            return (int) $Match[1];
        }
    }

    return 0;
}

function lc_stv_prepend_has_empty_sec_ch_ua_full_version_list(): bool {
    if (!array_key_exists('HTTP_SEC_CH_UA_FULL_VERSION_LIST', $_SERVER)) {
        return false;
    }

    return trim((string) $_SERVER['HTTP_SEC_CH_UA_FULL_VERSION_LIST']) === '';
}

function lc_stv_prepend_has_ua_ch_version_mismatch(string $UserAgent): bool {
    $SecChUa = (string) ($_SERVER['HTTP_SEC_CH_UA'] ?? '');

    if (trim($UserAgent) === '' || trim($SecChUa) === '') {
        return false;
    }

    $UserAgentMajor = lc_stv_prepend_get_chromium_major($UserAgent);
    $SecChUaMajor = lc_stv_prepend_get_sec_ch_ua_chromium_major($SecChUa);

    if ($UserAgentMajor === 0 || $SecChUaMajor === 0) {
        return false;
    }

    return $UserAgentMajor !== $SecChUaMajor;
}

function lc_stv_prepend_get_client_hint_suspicious_reason(string $UserAgent): string {
    $Reasons = [];

    if (lc_stv_prepend_has_ua_ch_version_mismatch($UserAgent)) {
        $Reasons[] = 'ua_ch_version_mismatch';
    }

    if (lc_stv_prepend_has_empty_sec_ch_ua_full_version_list()) {
        $Reasons[] = 'empty_sec_ch_ua_full_version_list';
    }

    return implode('+', $Reasons);
}

function lc_stv_prepend_get_firefox_major(string $UserAgent): int {
    if (preg_match('~(?:Firefox|FxiOS)/(\d+)~i', $UserAgent, $Match)) {
        return (int) $Match[1];
    }

    return 0;
}

function lc_stv_prepend_is_valid_ip(string $Ip): bool {
    return $Ip !== '' && filter_var($Ip, FILTER_VALIDATE_IP) !== false;
}

function lc_stv_prepend_get_trusted_proxy_ips(): array {
    $TrustedProxyIps = [
        '127.0.0.1',
        '::1',
    ];

    $ServerAddr = (string) ($_SERVER['SERVER_ADDR'] ?? '');

    if (lc_stv_prepend_is_valid_ip($ServerAddr)) {
        $TrustedProxyIps[] = $ServerAddr;
    }

    return array_values(array_unique($TrustedProxyIps));
}

function lc_stv_prepend_get_internal_wordpress_ips(): array {
    $InternalWordPressIps = [
        '127.0.0.1',
        '::1',
    ];

    $ServerAddr = (string) ($_SERVER['SERVER_ADDR'] ?? '');

    if (lc_stv_prepend_is_valid_ip($ServerAddr)) {
        $InternalWordPressIps[] = $ServerAddr;
    }

    return array_values(array_unique($InternalWordPressIps));
}

function lc_stv_prepend_get_effective_client_ip(): string {
    $RemoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    if (!lc_stv_prepend_is_valid_ip($RemoteAddr)) {
        return '';
    }

    if (!in_array($RemoteAddr, lc_stv_prepend_get_trusted_proxy_ips(), true)) {
        return $RemoteAddr;
    }

    $CfConnectingIp = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));

    if (lc_stv_prepend_is_valid_ip($CfConnectingIp)) {
        return $CfConnectingIp;
    }

    $XRealIp = trim((string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''));

    if (lc_stv_prepend_is_valid_ip($XRealIp)) {
        return $XRealIp;
    }

    $XForwardedFor = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));

    if ($XForwardedFor !== '') {
        foreach (explode(',', $XForwardedFor) as $ForwardedIp) {
            $ForwardedIp = trim($ForwardedIp);

            if (lc_stv_prepend_is_valid_ip($ForwardedIp)) {
                return $ForwardedIp;
            }
        }
    }

    return $RemoteAddr;
}

function lc_stv_prepend_is_internal_wordpress_request(string $UserAgent, string $EffectiveClientIp): bool {
    if (stripos($UserAgent, 'WordPress/') !== 0) {
        return false;
    }

    if (!lc_stv_prepend_is_valid_ip($EffectiveClientIp)) {
        return false;
    }

    return in_array($EffectiveClientIp, lc_stv_prepend_get_internal_wordpress_ips(), true);
}

function lc_stv_prepend_classify_request(string $UserAgent, string $Uri): array {
    $RequestKind = lc_stv_prepend_get_request_kind($Uri);

    $AiBots = [
        'GPTBot',
        'OAI-SearchBot',
        'ChatGPT-User',
        'OAI-AdsBot',
        'ClaudeBot',
        'Claude-User',
        'Claude-SearchBot',
        'PerplexityBot',
        'Perplexity-User',
        'CCBot',
        'Google-CloudVertexBot',
    ];

    $MatchedAgent = lc_stv_prepend_contains_any($UserAgent, $AiBots);

    if ($MatchedAgent !== '') {
        return [$RequestKind, 'known_ki_crawler', $MatchedAgent];
    }

    $KnownBots = [
        'Googlebot',
        'bingbot',
        'Applebot',
        'Qwantbot',
        'meta-externalagent',
        'PetalBot',
        'AhrefsBot',
        'SemrushBot',
        'YandexBot',
        'Bytespider',
    ];

    $MatchedAgent = lc_stv_prepend_contains_any($UserAgent, $KnownBots);

    if ($MatchedAgent !== '') {
        return [$RequestKind, 'known_bot', $MatchedAgent];
    }

    $AgentSignatureHeader = lc_stv_prepend_get_agent_signature_header();

    if ($AgentSignatureHeader !== '') {
        return [$RequestKind, 'suspicious', $AgentSignatureHeader];
    }

    if (trim($UserAgent) === '') {
        return [$RequestKind, 'suspicious', 'missing_user_agent'];
    }

    $HeadlessSignal = lc_stv_prepend_get_headless_signal($UserAgent);

    if ($HeadlessSignal !== '') {
        return [$RequestKind, 'suspicious', $HeadlessSignal];
    }

    $LegacyBrowserReason = lc_stv_prepend_get_legacy_browser_reason($UserAgent);

    if ($LegacyBrowserReason !== '') {
        return [$RequestKind, 'suspicious', $LegacyBrowserReason];
    }

    if ($RequestKind !== 'document') {
        return [$RequestKind, 'human_like', ''];
    }

    $ClientHintSuspiciousReason = lc_stv_prepend_get_client_hint_suspicious_reason($UserAgent);

    if ($ClientHintSuspiciousReason !== '') {
        return [$RequestKind, 'suspicious', $ClientHintSuspiciousReason];
    }

    if (!lc_stv_prepend_has_sec_fetch_triad()) {
        if (lc_stv_prepend_is_safari($UserAgent)) {
            return [$RequestKind, 'human_like', 'Safari'];
        }

        return [$RequestKind, 'suspicious', 'missing_sec_fetch'];
    }

    return [$RequestKind, 'human_like', ''];
}

function lc_stv_prepend_debug_headers_enabled(): bool {
    return is_file(__DIR__ . '/log/debug');
}

function lc_stv_prepend_get_debug_headers_log_file(): string {
    return __DIR__ . '/log/debug-headers.log';
}

function lc_stv_prepend_get_debug_headers(): array {
    $Headers = [];

    if (function_exists('getallheaders')) {
        $RawHeaders = getallheaders();

        if (is_array($RawHeaders)) {
            foreach ($RawHeaders as $Key => $Value) {
                $Headers[(string) $Key] = $Value;
            }
        }
    }

    foreach ($_SERVER as $Key => $Value) {
        if (strpos($Key, 'HTTP_') === 0 || strpos($Key, 'CONTENT_') === 0) {
            $Headers[$Key] = $Value;
        }
    }

    return $Headers;
}

function lc_stv_prepend_get_debug_server_context(): array {
    return [
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
        'server_addr' => $_SERVER['SERVER_ADDR'] ?? '',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'query_string' => $_SERVER['QUERY_STRING'] ?? '',
        'request_scheme' => $_SERVER['REQUEST_SCHEME'] ?? '',
        'https' => $_SERVER['HTTPS'] ?? '',
        'server_protocol' => $_SERVER['SERVER_PROTOCOL'] ?? '',
        'request_time_float' => $_SERVER['REQUEST_TIME_FLOAT'] ?? '',
    ];
}

function lc_stv_prepend_write_debug_headers(array $Request): void {
    if (!lc_stv_prepend_debug_headers_enabled()) {
        return;
    }

    $DebugLogFile = lc_stv_prepend_get_debug_headers_log_file();
    $DebugLogDir = dirname($DebugLogFile);

    if (!is_dir($DebugLogDir) || !is_writable($DebugLogDir)) {
        return;
    }

    $DebugRequest = [
        'time' => gmdate('c'),
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'query_string' => $_SERVER['QUERY_STRING'] ?? '',
        'ip' => $Request['ip'] ?? '',
        'request_kind' => $Request['request_kind'] ?? '',
        'traffic_class' => $Request['traffic_class'] ?? '',
        'matched_agent' => $Request['matched_agent'] ?? '',
        'status' => $Request['status'] ?? 0,
        'server' => lc_stv_prepend_get_debug_server_context(),
        'headers' => lc_stv_prepend_get_debug_headers(),
    ];

    $DebugJson = json_encode($DebugRequest, JSON_UNESCAPED_SLASHES);

    if ($DebugJson === false) {
        return;
    }

    file_put_contents($DebugLogFile, $DebugJson . PHP_EOL, FILE_APPEND | LOCK_EX);
}

$StvDirName = dirname(__DIR__, 2);
$StvLogFile = $StvDirName . '/cache/litecache-stv/requests.log';
$StvLogDir = dirname($StvLogFile);

require_once __DIR__ . '/inc/stv_control.php';
require_once __DIR__ . '/inc/stv_custom_excludes.php';

if (!lc_stv_is_enabled()) {
    return;
}

$StvRequestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$StvRequestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$StvRequestKind = lc_stv_prepend_get_request_kind($StvRequestUri);

if (!headers_sent()) {
    if (($StvRequestMethod === 'GET' || $StvRequestMethod === 'HEAD') && $StvRequestKind === 'document') {
        header('Accept-CH: Device-Memory');
    }
}

$StvRequestIp = lc_stv_prepend_get_effective_client_ip();
$StvRequestUserAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

$StvInternalUserAgents = [
    'kitt_runner',
    'lscache_walker',
    'lscache_runner',
];

if (lc_stv_prepend_contains_any($StvRequestUserAgent, $StvInternalUserAgents) !== '') {
    return;
}

if (lc_stv_prepend_is_internal_wordpress_request($StvRequestUserAgent, $StvRequestIp)) {
    return;
}

$StvCustomExcludes = lc_stv_load_custom_excludes();

if (lc_stv_is_request_custom_excluded($StvCustomExcludes, $StvRequestIp, $StvRequestUserAgent, $StvRequestUri)) {
    return;
}

$StvCanWriteRequestLog = is_string($StvLogFile) && $StvLogFile !== '' && is_dir($StvLogDir) && is_writable($StvLogDir);

if (!$StvCanWriteRequestLog && !lc_stv_prepend_debug_headers_enabled()) {
    return;
}

[$StvClassifiedRequestKind, $StvTrafficClass, $StvMatchedAgent] = lc_stv_prepend_classify_request($StvRequestUserAgent, $StvRequestUri);
$StvRequestKind = $StvClassifiedRequestKind;

$StvRequest = [
    'time' => gmdate('c'),
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'uri' => $StvRequestUri,
    'query_string' => $_SERVER['QUERY_STRING'] ?? '',
    'ip' => $StvRequestIp,
    'user_agent' => $StvRequestUserAgent,
    'referer' => $_SERVER['HTTP_REFERER'] ?? '',
    'sec_fetch_site' => $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '',
    'sec_fetch_mode' => $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '',
    'sec_fetch_dest' => $_SERVER['HTTP_SEC_FETCH_DEST'] ?? '',
    'device_memory' => $_SERVER['HTTP_DEVICE_MEMORY'] ?? '',
    'request_kind' => $StvRequestKind,
    'traffic_class' => $StvTrafficClass,
    'matched_agent' => $StvMatchedAgent,
    'status' => 200,
];

register_shutdown_function(static function () use ($StvLogFile, $StvCanWriteRequestLog, $StvRequest): void {
    $StvStatus = http_response_code();

    if (!is_int($StvStatus) || $StvStatus < 100) {
        $StvStatus = 200;
    }

    $StvRequest['status'] = $StvStatus;

    lc_stv_prepend_write_debug_headers($StvRequest);

    if (!$StvCanWriteRequestLog) {
        return;
    }

    if ($StvRequest['traffic_class'] === 'human_like' && $StvStatus >= 200 && $StvStatus < 400) {
        return;
    }

    $StvJson = json_encode($StvRequest, JSON_UNESCAPED_SLASHES);

    if ($StvJson === false) {
        return;
    }

    file_put_contents($StvLogFile, $StvJson . PHP_EOL, FILE_APPEND | LOCK_EX);
});
