<?php
defined('ABSPATH') || exit;

function lc_stv_get_query_key(string $StvKey, string $StvDefault = ''): string {
    if (!isset($_GET[$StvKey])) {
        return $StvDefault;
    }

    return sanitize_key(wp_unslash((string) $_GET[$StvKey]));
}

function lc_stv_get_query_text(string $StvKey, string $StvDefault = ''): string {
    if (!isset($_GET[$StvKey])) {
        return $StvDefault;
    }

    return sanitize_text_field(wp_unslash((string) $_GET[$StvKey]));
}

function lc_stv_get_query_int(string $StvKey, int $StvDefault = 0): int {
    if (!isset($_GET[$StvKey])) {
        return $StvDefault;
    }

    return (int) wp_unslash((string) $_GET[$StvKey]);
}

function lc_stv_get_post_key(string $StvKey, string $StvDefault = ''): string {
    if (!isset($_POST[$StvKey])) {
        return $StvDefault;
    }

    return sanitize_key(wp_unslash((string) $_POST[$StvKey]));
}

function lc_stv_get_post_text(string $StvKey, string $StvDefault = ''): string {
    if (!isset($_POST[$StvKey])) {
        return $StvDefault;
    }

    return sanitize_text_field(wp_unslash((string) $_POST[$StvKey]));
}

function lc_stv_get_post_int(string $StvKey, int $StvDefault = 0): int {
    if (!isset($_POST[$StvKey])) {
        return $StvDefault;
    }

    return (int) wp_unslash((string) $_POST[$StvKey]);
}

function lc_stv_get_all_log_entries(): array {
    $StvFile = LC_STV_LOG_FILE;

    if (!file_exists($StvFile) || !is_readable($StvFile)) {
        return [];
    }

    try {
        $StvHandle = new SplFileObject($StvFile, 'rb');
    } catch (RuntimeException $StvException) {
        return [];
    }

    $StvEntries = [];

    while (!$StvHandle->eof()) {
        $StvLine = trim((string) $StvHandle->fgets());

        if ($StvLine === '') {
            continue;
        }

        $StvEntry = json_decode($StvLine, true);

        if (!is_array($StvEntry)) {
            continue;
        }

        $StvEntries[] = $StvEntry;
    }

    return $StvEntries;
}

function lc_stv_get_sortable_columns(): array {
    return [
        'time' => 'Time',
        'method' => 'Method',
        'uri' => 'URI',
        'status' => 'Status',
        'hits' => 'Hits',
        'traffic_class' => 'Class',
        'ip' => 'IP',
        'user_agent' => 'User-Agent',
    ];
}

function lc_stv_get_current_orderby(): string {
    $StvAllowed = array_keys(lc_stv_get_sortable_columns());
    $StvOrderBy = lc_stv_get_query_key('orderby', 'time');

    if (!in_array($StvOrderBy, $StvAllowed, true)) {
        return 'time';
    }

    return $StvOrderBy;
}

function lc_stv_get_current_order(): string {
    $StvOrder = strtoupper(lc_stv_get_query_key('order', 'DESC'));

    if ($StvOrder !== 'ASC' && $StvOrder !== 'DESC') {
        return 'DESC';
    }

    return $StvOrder;
}

function lc_stv_get_display_traffic_class(array $StvEntry): string {
    $StvTrafficClass = trim((string) ($StvEntry['traffic_class'] ?? ''));

    return $StvTrafficClass;
}

function lc_stv_get_traffic_class_label(string $StvTrafficClass): string {
    switch ($StvTrafficClass) {
        case 'known_ki_crawler':
            return 'Known AI crawler';

        case 'known_bot':
            return 'Known bot';

        case 'suspicious':
            return 'Suspicious';

        case 'human_like':
            return 'Human-like';
    }

    return ucwords(str_replace('_', ' ', $StvTrafficClass));
}

function lc_stv_get_traffic_class_badge_style(string $StvTrafficClass): string {
    switch ($StvTrafficClass) {
        case 'known_ki_crawler':
            return 'display:inline-block;padding:3px 8px;border-radius:999px;background:#ede7f6;color:#5b21b6;font-weight:600;white-space:nowrap';

        case 'known_bot':
            return 'display:inline-block;padding:3px 8px;border-radius:999px;background:#e7f3ff;color:#135e96;font-weight:600;white-space:nowrap';

        case 'suspicious':
            return 'display:inline-block;padding:3px 8px;border-radius:999px;background:#fff1f0;color:#b42318;font-weight:600;white-space:nowrap';

        case 'human_like':
            return 'display:inline-block;padding:3px 8px;border-radius:999px;background:#edf7ed;color:#1d7f38;font-weight:600;white-space:nowrap';
    }

    return 'display:none';
}

function lc_stv_get_matched_agent_label(string $StvMatchedAgent): string {
    $StvKey = trim($StvMatchedAgent);

    if ($StvKey === '') {
        return '';
    }

    $StvLookupKey = strtolower($StvKey);

    if (strpos($StvLookupKey, '+') !== false) {
        $StvLabels = [];

        foreach (explode('+', $StvLookupKey) as $StvPart) {
            $StvPartLabel = lc_stv_get_matched_agent_label(trim($StvPart));

            if ($StvPartLabel !== '') {
                $StvLabels[] = $StvPartLabel;
            }
        }

        return implode(' + ', array_unique($StvLabels));
    }

    if (preg_match('/^chromium_lt_(\d+)$/', $StvLookupKey, $StvMatch)) {
        return 'Chromium below trust threshold (< ' . $StvMatch[1] . ')';
    }

    if (preg_match('/^firefox_lt_(\d+)$/', $StvLookupKey, $StvMatch)) {
        return 'Firefox below trust threshold (< ' . $StvMatch[1] . ')';
    }

    $StvAliases = [
        // AI crawlers
        'gptbot' => 'OpenAI GPTBot',
        'oai-searchbot' => 'OpenAI SearchBot',
        'chatgpt-user' => 'ChatGPT User Agent',
        'oai-adsbot' => 'OpenAI AdsBot',
        'claudebot' => 'ClaudeBot',
        'claude-user' => 'Claude User Agent',
        'claude-searchbot' => 'Claude SearchBot',
        'perplexitybot' => 'PerplexityBot',
        'perplexity-user' => 'Perplexity User Agent',
        'ccbot' => 'Common Crawl Bot',
        'google-cloudvertexbot' => 'Google Cloud Vertex Bot',
        // Known bots
        'googlebot' => 'Googlebot',
        'bingbot' => 'Bingbot',
        'applebot' => 'Applebot',
        'qwantbot' => 'Qwantbot',
        'meta-externalagent' => 'Meta External Agent',
        'petalbot' => 'PetalBot',
        'ahrefsbot' => 'AhrefsBot',
        'semrushbot' => 'SemrushBot',
        'yandexbot' => 'YandexBot',
        'bytespider' => 'ByteDance Bytespider',
        // Suspicious signals
        'missing_user_agent' => 'Missing User-Agent',
        'missing_sec_fetch' => 'Missing Sec-Fetch headers',
        'legacy_mobile_safari_xmlrpc' => 'Legacy Mobile Safari XML-RPC request',
        'ua_ch_version_mismatch' => 'User-Agent / Client Hint version mismatch',
        'empty_sec_ch_ua_full_version_list' => 'Empty Sec-CH-UA-Full-Version-List header',
        // Human-like exceptions
        'safari' => 'Safari browser exception',
    ];

    if (isset($StvAliases[$StvLookupKey])) {
        return $StvAliases[$StvLookupKey];
    }

    $StvLabel = str_replace(['_', '-'], ' ', $StvKey);
    $StvLabel = preg_replace('/\s+/', ' ', $StvLabel);

    return ucwords(trim($StvLabel));
}

function lc_stv_render_traffic_class(array $StvEntry): string {
    $StvTrafficClass = lc_stv_get_display_traffic_class($StvEntry);
    $StvLabel = lc_stv_get_traffic_class_label($StvTrafficClass);
    $StvBadgeStyle = lc_stv_get_traffic_class_badge_style($StvTrafficClass);
    $StvMatchedAgent = trim((string) ($StvEntry['matched_agent'] ?? ''));
    $StvMatchedAgentLabel = lc_stv_get_matched_agent_label($StvMatchedAgent);
    $StvBotVerification = trim((string) ($StvEntry['bot_verification'] ?? ''));

    if ($StvTrafficClass === 'suspicious' && $StvBotVerification === 'mismatch' && stripos($StvMatchedAgent, 'googlebot') !== false) {
        $StvLabel = 'Suspicious bot';
        $StvMatchedAgentLabel = 'Googlebot IP mismatch';
    }

    $StvClass = '';
    $StvTooltip = '';

    if ($StvMatchedAgentLabel !== '') {
        $StvClass = ' class="lc-stv-tooltip"';
        $StvTooltip = ' title="' . esc_attr($StvMatchedAgentLabel) . '"';
    }

    return '<span' . $StvClass . ' style="' . esc_attr($StvBadgeStyle) . '"' . $StvTooltip . '>' . esc_html($StvLabel) . '</span>';
}

function lc_stv_compare_ip_values(string $StvLeft, string $StvRight): int {
    $StvLeftBinary = @inet_pton($StvLeft);
    $StvRightBinary = @inet_pton($StvRight);

    if ($StvLeftBinary !== false && $StvRightBinary !== false) {
        if (strlen($StvLeftBinary) !== strlen($StvRightBinary)) {
            return strlen($StvLeftBinary) <=> strlen($StvRightBinary);
        }

        return strcmp($StvLeftBinary, $StvRightBinary);
    }

    return strcasecmp($StvLeft, $StvRight);
}

function lc_stv_compare_log_entries(array $StvLeft, array $StvRight, string $StvOrderBy): int {
    switch ($StvOrderBy) {
        case 'status':
            return (int) ($StvLeft['status'] ?? 0) <=> (int) ($StvRight['status'] ?? 0);

        case 'ip':
            return lc_stv_compare_ip_values((string) ($StvLeft['ip'] ?? ''), (string) ($StvRight['ip'] ?? ''));

        case 'time':
            $StvLeftTime = strtotime((string) ($StvLeft['time'] ?? '')) ?: 0;
            $StvRightTime = strtotime((string) ($StvRight['time'] ?? '')) ?: 0;
            return $StvLeftTime <=> $StvRightTime;

        case 'traffic_class':
            return strcasecmp(
                    lc_stv_get_display_traffic_class($StvLeft),
                    lc_stv_get_display_traffic_class($StvRight)
            );

        case 'method':
        case 'uri':
        case 'user_agent':
            return strcasecmp((string) ($StvLeft[$StvOrderBy] ?? ''), (string) ($StvRight[$StvOrderBy] ?? ''));
    }

    return 0;
}

function lc_stv_sort_log_entries(array $StvEntries): array {
    $StvOrderBy = lc_stv_get_current_orderby();
    $StvOrder = lc_stv_get_current_order();

    usort($StvEntries, static function (array $StvLeft, array $StvRight) use ($StvOrderBy, $StvOrder): int {
        $StvResult = lc_stv_compare_log_entries($StvLeft, $StvRight, $StvOrderBy);

        if ($StvResult === 0) {
            $StvResult = lc_stv_compare_log_entries($StvLeft, $StvRight, 'time');

            if ($StvOrderBy !== 'time') {
                $StvResult *= -1;
            }
        }

        if ($StvOrder === 'DESC') {
            $StvResult *= -1;
        }

        return $StvResult;
    });

    return $StvEntries;
}

function lc_stv_get_sort_link(string $StvColumn, string $StvDirection): string {
    $StvDirection = strtoupper($StvDirection);

    if ($StvDirection !== 'ASC' && $StvDirection !== 'DESC') {
        $StvDirection = 'ASC';
    }

    return add_query_arg(
            array_merge(
                    [
                        'page' => 'litecache-stv',
                        'orderby' => $StvColumn,
                        'order' => $StvDirection,
                        'paged' => 1,
                    ],
                    lc_stv_get_current_request_filter_query_args()
            ),
            admin_url('admin.php')
    );
}

function lc_stv_render_sort_header(string $StvColumn, string $StvLabel): string {
    $StvIsActive = lc_stv_get_current_orderby() === $StvColumn;
    $StvCurrentOrder = lc_stv_get_current_order();

    $StvLabelClasses = [''];
    $StvAscClasses = [''];
    $StvDescClasses = [''];

    if ($StvIsActive) {
        $StvLabelClasses[] = 'active';

        if ($StvCurrentOrder === 'ASC') {
            $StvAscClasses[] = 'active';
        }

        if ($StvCurrentOrder === 'DESC') {
            $StvDescClasses[] = 'active';
        }
    }

    $StvHtml = '';
    $StvHtml .= '<span>';
    $StvHtml .= '<span class="' . esc_attr(implode(' ', $StvLabelClasses)) . '">' . esc_html($StvLabel) . '</span> ';
    $StvHtml .= '<a href="' . esc_url(lc_stv_get_sort_link($StvColumn, 'ASC')) . '" class="' . esc_attr(implode(' ', $StvAscClasses)) . '" aria-label="Sort ' . esc_attr($StvLabel) . ' ascending">▲</a> ';
    $StvHtml .= '<a href="' . esc_url(lc_stv_get_sort_link($StvColumn, 'DESC')) . '" class="' . esc_attr(implode(' ', $StvDescClasses)) . '" aria-label="Sort ' . esc_attr($StvLabel) . ' descending">▼</a>';
    $StvHtml .= '</span>';

    return $StvHtml;
}

function lc_stv_format_log_time(string $StvTime): string {
    $StvTimestamp = strtotime($StvTime);

    if ($StvTimestamp === false) {
        return $StvTime;
    }

    return gmdate('d-m-Y H:i:s', $StvTimestamp);
}

function lc_stv_get_filterable_traffic_classes(): array {
    return [
        'known_ki_crawler' => 'Known AI crawler',
        'known_bot' => 'Known bot',
        'suspicious' => 'Suspicious',
        'human_like' => 'Human-like',
    ];
}

function lc_stv_get_current_request_filters(): array {
    return lc_stv_normalize_request_filters([
        'traffic_class' => lc_stv_get_query_key('traffic_class'),
        'status' => lc_stv_get_query_text('filter_status'),
        'method' => lc_stv_get_query_key('filter_method'),
        'search' => lc_stv_get_query_text('search'),
    ]);
}

function lc_stv_get_current_request_filter_query_args(): array {
    $StvFilters = lc_stv_get_current_request_filters();
    $StvArgs = [];

    if ($StvFilters['traffic_class'] !== '') {
        $StvArgs['traffic_class'] = $StvFilters['traffic_class'];
    }

    if ($StvFilters['status'] !== '') {
        $StvArgs['filter_status'] = $StvFilters['status'];
    }

    if ($StvFilters['method'] !== '') {
        $StvArgs['filter_method'] = $StvFilters['method'];
    }

    if ($StvFilters['search'] !== '') {
        $StvArgs['search'] = $StvFilters['search'];
    }

    return $StvArgs;
}

function lc_stv_has_active_request_filters(array $StvFilters): bool {
    return $StvFilters['traffic_class'] !== '' || $StvFilters['status'] !== '' || $StvFilters['method'] !== '';
}

function lc_stv_render_request_filters_form(array $StvFilters): void {
    $StvTrafficClasses = lc_stv_get_filterable_traffic_classes();
    $StvStatuses = lc_stv_get_distinct_request_statuses();
    $StvMethods = lc_stv_get_distinct_request_methods();
    ?>
    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="filter">
        <input type="hidden" name="page" value="litecache-stv" />
        <input type="hidden" name="orderby" value="<?php echo esc_attr(lc_stv_get_current_orderby()); ?>" />
        <input type="hidden" name="order" value="<?php echo esc_attr(lc_stv_get_current_order()); ?>" />

        <div>
            <label for="lc-stv-filter-class"><strong>Class</strong></label>
            <select name="traffic_class" id="lc-stv-filter-class">
                <option value="">All</option>
                <?php foreach ($StvTrafficClasses as $StvValue => $StvLabel) { ?>
                    <option value="<?php echo esc_attr($StvValue); ?>" <?php selected($StvFilters['traffic_class'], $StvValue); ?>><?php echo esc_html($StvLabel); ?></option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label for="lc-stv-filter-status"><strong>Status</strong></label>
            <select name="filter_status" id="lc-stv-filter-status">
                <option value="">All</option>
                <?php foreach ($StvStatuses as $StvStatus) { ?>
                    <option value="<?php echo esc_attr((string) $StvStatus); ?>" <?php selected($StvFilters['status'], (string) $StvStatus); ?>><?php echo esc_html((string) $StvStatus); ?></option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label for="lc-stv-filter-method"><strong>Method</strong></label>
            <select name="filter_method" id="lc-stv-filter-method">
                <option value="">All</option>
                <?php foreach ($StvMethods as $StvMethod) { ?>
                    <option value="<?php echo esc_attr($StvMethod); ?>" <?php selected($StvFilters['method'], $StvMethod); ?>><?php echo esc_html($StvMethod); ?></option>
                <?php } ?>
            </select>
        </div>
        <div>
            <button type="submit" class="button button-primary">Filter</button>
            <?php if (lc_stv_has_active_request_filters($StvFilters)) { ?>
                <a href="<?php echo esc_url(lc_stv_get_start_admin_url()); ?>" class="button button-primary">Reset</a>
            <?php } ?>
        </div>

    </form>
    <?php
}

function lc_stv_render_request_search_form(array $StvFilters): void {
    ?>
    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="search">
        <input type="hidden" name="page" value="litecache-stv" />
        <input type="hidden" name="orderby" value="<?php echo esc_attr(lc_stv_get_current_orderby()); ?>" />
        <input type="hidden" name="order" value="<?php echo esc_attr(lc_stv_get_current_order()); ?>" />
        <?php if ($StvFilters['traffic_class'] !== '') { ?>
            <input type="hidden" name="traffic_class" value="<?php echo esc_attr($StvFilters['traffic_class']); ?>" />
        <?php } ?>
        <?php if ($StvFilters['status'] !== '') { ?>
            <input type="hidden" name="filter_status" value="<?php echo esc_attr($StvFilters['status']); ?>" />
        <?php } ?>
        <?php if ($StvFilters['method'] !== '') { ?>
            <input type="hidden" name="filter_method" value="<?php echo esc_attr($StvFilters['method']); ?>" />
        <?php } ?>
        <label for="lc-stv-search"><strong>Search</strong></label>
        <div>
            <input type="text" name="search" id="lc-stv-search" value="<?php echo esc_attr($StvFilters['search']); ?>" placeholder="URI, IP, User-Agent" />
        </div>
        <div>
            <button type="submit" class="button button-primary">Search</button>
            <?php if ($StvFilters['search'] !== '') { ?>
                <a href="<?php echo esc_url(lc_stv_get_start_admin_url(array_diff_key(lc_stv_get_current_request_filter_query_args(), ['search' => true]))); ?>" class="button button-primary">Clear search</a>
            <?php } ?>
        </div>
    </form>
    <?php
}

function lc_stv_on_activation_prepare_rewrite_probe(): void {
    require_once LC_STV_DIR . 'inc/stv_prepend_tools.php';
    lc_stv_prepare_rewrite_probe_configuration();
}

function lc_stv_get_start_admin_url(array $StvArgs = array()): string {
    $StvArgs = array_merge(array('page' => 'litecache-stv'), $StvArgs);

    return add_query_arg($StvArgs, admin_url('admin.php'));
}

function lc_stv_get_next_daily_import_timestamp(): int {
    $StvTimezone = wp_timezone();
    $StvNow = new DateTimeImmutable('now', $StvTimezone);
    $StvNext = $StvNow->setTime(0, 0, 0);

    if ($StvNext <= $StvNow) {
        $StvNext = $StvNext->modify('+1 day');
    }

    return $StvNext->getTimestamp();
}

function lc_stv_is_cron_enabled(): bool {
    return wp_next_scheduled(LC_STV_DAILY_IMPORT_HOOK) !== false;
}

function lc_stv_get_next_daily_import_run(): int {
    $StvTimestamp = wp_next_scheduled(LC_STV_DAILY_IMPORT_HOOK);

    if ($StvTimestamp === false) {
        return 0;
    }

    return (int) $StvTimestamp;
}

function lc_stv_schedule_daily_import_event(): bool {
    if (lc_stv_is_cron_enabled()) {
        return true;
    }

    return wp_schedule_event(lc_stv_get_next_daily_import_timestamp(), 'daily', LC_STV_DAILY_IMPORT_HOOK) !== false;
}

function lc_stv_unschedule_daily_import_event(): bool {
    wp_clear_scheduled_hook(LC_STV_DAILY_IMPORT_HOOK);

    return !lc_stv_is_cron_enabled();
}

function lc_stv_run_import_cycle(bool $StvResetRequests = false) {
    $StvEntries = lc_stv_get_all_log_entries();

    if (empty($StvEntries)) {
        return [
            'state' => 'empty',
            'imported' => 0,
        ];
    }

    if ($StvResetRequests) {
        if (!lc_stv_clear_requests_table()) {
            return new WP_Error('stv_requests_clear_failed', 'Could not clear stv_requests before import.');
        }
    }

    $StvResult = lc_stv_import_log_entries_to_db($StvEntries);

    if (is_wp_error($StvResult)) {
        return $StvResult;
    }

    $StvImported = (int) ($StvResult['imported'] ?? 0);

    if (!lc_stv_clear_log_file()) {
        return [
            'state' => 'clear_failed',
            'imported' => $StvImported,
        ];
    }

    return [
        'state' => 'success',
        'imported' => $StvImported,
    ];
}

function lc_stv_get_agent_retention_days(): int {
    return 30;
}

function lc_stv_run_agent_cleanup(int $StvRetentionDays = 30) {
    $StvDeletedAgentHits = lc_stv_delete_old_agent_hits($StvRetentionDays);

    if (is_wp_error($StvDeletedAgentHits)) {
        return $StvDeletedAgentHits;
    }

    $StvDeletedAgents = lc_stv_delete_old_agents($StvRetentionDays);

    if (is_wp_error($StvDeletedAgents)) {
        return $StvDeletedAgents;
    }

    return [
        'deleted_agent_hits' => (int) $StvDeletedAgentHits,
        'deleted_agents' => (int) $StvDeletedAgents,
    ];
}

function lc_stv_run_scheduled_import(): void {
    if (function_exists('lc_stv_is_enabled') && !lc_stv_is_enabled()) {
        return;
    }

    $StvResult = lc_stv_run_import_cycle(true);

    if (is_wp_error($StvResult)) {
        error_log('LiteCache STV cron import failed: ' . $StvResult->get_error_message());
    } elseif (($StvResult['state'] ?? '') === 'clear_failed') {
        error_log('LiteCache STV cron import completed but the log file could not be cleared afterwards.');
    }

    $StvCleanupResult = lc_stv_run_agent_cleanup(lc_stv_get_agent_retention_days());

    if (is_wp_error($StvCleanupResult)) {
        error_log('LiteCache STV agent cleanup failed: ' . $StvCleanupResult->get_error_message());
    }
}

function lc_stv_handle_toggle_cron(): void {
    if (!current_user_can('manage_options')) {
        wp_die('You are not allowed to do this.');
    }

    check_admin_referer('lc_stv_toggle_cron');

    $StvMode = lc_stv_get_post_key('lc_stv_cron_mode');
    $StvOk = false;
    $StvState = 'error';

    if ($StvMode === 'enable') {
        $StvOk = lc_stv_schedule_daily_import_event();
        $StvState = $StvOk ? 'enabled' : 'error';
    } elseif ($StvMode === 'disable') {
        $StvOk = lc_stv_unschedule_daily_import_event();
        $StvState = $StvOk ? 'disabled' : 'error';
    }

    wp_safe_redirect(lc_stv_get_start_admin_url(['lc_stv_cron' => $StvState]));
    exit;
}

function lc_stv_render_cron_notice(): void {
    $StvState = lc_stv_get_query_key('lc_stv_cron');

    if ($StvState === '') {
        return;
    }

    if ($StvState === 'enabled') {
        echo '<ul class="errormessage success">';
        echo '<li>LiteCache STV daily import cron is now enabled.</li>';
        echo '</ul>';
        return;
    }

    if ($StvState === 'disabled') {
        echo '<ul class="errormessage">';
        echo '<li>LiteCache STV daily import cron is now disabled.</li>';
        echo '</ul>';
        return;
    }

    echo '<ul class="errormessage"><li>LiteCache STV could not update the daily import cron state.</li></ul>';
}

function lc_stv_render_cron_form(): void {
    $StvEnabled = lc_stv_is_cron_enabled();
    $StvMode = $StvEnabled ? 'disable' : 'enable';
    $StvLabel = $StvEnabled ? 'Disable cron' : 'Enable cron';
    $StvStateLabel = $StvEnabled ? 'Cron ON' : 'Cron OFF';
    $StvStateColor = $StvEnabled ? '#3c434a' : '#3c434a';
    $StvNextRun = lc_stv_get_next_daily_import_run();
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('lc_stv_toggle_cron'); ?>
        <input type="hidden" name="action" value="lc_stv_toggle_cron" />
        <input type="hidden" name="lc_stv_cron_mode" value="<?php echo esc_attr($StvMode); ?>" />
        <span style="color:<?php echo esc_attr($StvStateColor); ?>;"><?php echo esc_html($StvStateLabel); ?></span>
        <?php if ($StvNextRun > 0) { ?>
            <span><strong>Next run: </strong><?php echo esc_html(wp_date('d-m-Y H:i:s', $StvNextRun)); ?></span>
        <?php } ?>
        <button type="submit" class="button button-primary"><?php echo esc_html($StvLabel); ?></button>
    </form>
    <?php
}

function lc_stv_handle_toggle_capture(): void {
    if (!current_user_can('manage_options')) {
        wp_die('You are not allowed to do this.');
    }

    check_admin_referer('lc_stv_toggle_capture');

    $StvMode = lc_stv_get_post_key('lc_stv_mode');
    $StvOk = false;
    $StvState = 'error';

    if ($StvMode === 'enable') {
        $StvOk = lc_stv_enable();
        $StvState = $StvOk ? 'enabled' : 'error';
    } elseif ($StvMode === 'disable') {
        $StvOk = lc_stv_disable();
        $StvState = $StvOk ? 'disabled' : 'error';
    }

    wp_safe_redirect(lc_stv_get_start_admin_url(array('lc_stv_toggle' => $StvState)));
    exit;
}

function lc_stv_render_toggle_notice(): void {
    $StvToggle = lc_stv_get_query_key('lc_stv_toggle');

    if ($StvToggle === '') {
        return;
    }

    if ($StvToggle === 'enabled') {
        echo '<div class="notice notice-success is-dismissible"><p>LiteCache STV request capture is now enabled.</p></div>';
        return;
    }

    if ($StvToggle === 'disabled') {
        echo '<div class="notice notice-warning is-dismissible"><p>LiteCache STV request capture is now disabled.</p></div>';
        return;
    }

    echo '<div class="notice notice-error is-dismissible"><p>LiteCache STV could not update the request capture state.</p></div>';
}

function lc_stv_render_toggle_form(): void {
    $StvEnabled = lc_stv_is_enabled();
    $StvMode = $StvEnabled ? 'disable' : 'enable';
    $StvLabel = $StvEnabled ? 'Disable STV' : 'Enable STV';
    $StvStateLabel = $StvEnabled ? 'Capture ON' : 'Capture OFF';
    $StvStateColor = $StvEnabled ? '#1d7f38' : '#8a1f11';
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('lc_stv_toggle_capture'); ?>
        <input type="hidden" name="action" value="lc_stv_toggle_capture" />
        <input type="hidden" name="lc_stv_mode" value="<?php echo esc_attr($StvMode); ?>" />
        <!-- <span style="color:<?php echo esc_attr($StvStateColor); ?>;"><?php echo esc_html($StvStateLabel); ?></span> -->
        <button type="submit" class="button button-primary"><?php echo esc_html($StvLabel); ?></button>
    </form>
    <?php
}

function lc_stv_handle_set_per_page(): void {
    if (!current_user_can('manage_options')) {
        wp_die('You are not allowed to do this.');
    }

    check_admin_referer('lc_stv_set_per_page');

    $StvAllowed = [25, 50, 100, 200];
    $StvPerPage = lc_stv_get_post_int('lc_stv_per_page', 25);

    if (!in_array($StvPerPage, $StvAllowed, true)) {
        $StvPerPage = 25;
    }

    setcookie(
            'lc_stv_per_page',
            (string) $StvPerPage,
            [
                'expires' => time() + YEAR_IN_SECONDS,
                'path' => COOKIEPATH ?: '/',
                'domain' => COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => false,
                'samesite' => 'Lax',
            ]
    );

    $_COOKIE['lc_stv_per_page'] = (string) $StvPerPage;

    $StvArgs = array_merge(['paged' => 1], lc_stv_normalize_request_filters([
        'traffic_class' => lc_stv_get_post_key('traffic_class'),
        'status' => lc_stv_get_post_text('filter_status'),
        'method' => lc_stv_get_post_key('filter_method'),
        'search' => lc_stv_get_post_text('search'),
    ]));

    $StvOrderBy = lc_stv_get_post_key('orderby');
    $StvOrder = strtoupper(lc_stv_get_post_key('order'));

    if ($StvOrderBy !== '') {
        $StvArgs['orderby'] = $StvOrderBy;
    }

    if ($StvOrder === 'ASC' || $StvOrder === 'DESC') {
        $StvArgs['order'] = $StvOrder;
    }

    if (isset($StvArgs['status'])) {
        $StvArgs['filter_status'] = $StvArgs['status'];
        unset($StvArgs['status']);
    }

    if (isset($StvArgs['method'])) {
        $StvArgs['filter_method'] = $StvArgs['method'];
        unset($StvArgs['method']);
    }

    foreach ($StvArgs as $StvKey => $StvValue) {
        if ($StvValue === '') {
            unset($StvArgs[$StvKey]);
        }
    }

    wp_safe_redirect(lc_stv_get_start_admin_url($StvArgs));
    exit;
}

function lc_stv_render_per_page_form(int $StvCurrentPerPage): void {
    $StvOptions = [25, 50, 100, 200];
    ?>
    <li>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('lc_stv_set_per_page'); ?>
            <input type="hidden" name="action" value="lc_stv_set_per_page" />
            <?php foreach (lc_stv_get_current_request_filter_query_args() as $StvKey => $StvValue) { ?>
                <input type="hidden" name="<?php echo esc_attr($StvKey); ?>" value="<?php echo esc_attr((string) $StvValue); ?>" />
            <?php } ?>
            <input type="hidden" name="orderby" value="<?php echo esc_attr(lc_stv_get_current_orderby()); ?>" />
            <input type="hidden" name="order" value="<?php echo esc_attr(lc_stv_get_current_order()); ?>" />
            <label for="lc-stv-per-page"><strong>Items per page</strong></label>
            <select name="lc_stv_per_page" id="lc-stv-per-page">
                <?php foreach ($StvOptions as $StvOption) { ?>
                    <option value="<?php echo esc_attr((string) $StvOption); ?>" <?php selected($StvCurrentPerPage, $StvOption); ?>><?php echo esc_html((string) $StvOption); ?></option>
                <?php } ?>
            </select>
            <button type="submit" class="button button-primary">Apply</button>
        </form>
    </li>
    <?php
}

function lc_stv_handle_import_log(): void {
    if (!current_user_can('manage_options')) {
        wp_die('You are not allowed to do this.');
    }

    check_admin_referer('lc_stv_import_log');

    $StvResult = lc_stv_run_import_cycle();

    if (is_wp_error($StvResult)) {
        wp_safe_redirect(lc_stv_get_start_admin_url(['lc_stv_import' => 'error']));
        exit;
    }

    wp_safe_redirect(lc_stv_get_start_admin_url([
        'lc_stv_import' => (string) ($StvResult['state'] ?? 'error'),
        'lc_stv_imported' => (int) ($StvResult['imported'] ?? 0),
    ]));
    exit;
}

function lc_stv_render_import_notice(): void {
    $StvState = lc_stv_get_query_key('lc_stv_import');

    if ($StvState === '') {
        return;
    }

    $StvImported = max(0, lc_stv_get_query_int('lc_stv_imported'));

    if ($StvState === 'success') {
        echo '<ul class="errormessage success"><li>Imported ' . esc_html((string) $StvImported) . ' request(s) from the log file into the database.</li></ul>';
        return;
    }

    if ($StvState === 'empty') {
        echo '<ul class="errormessage"><li>No log entries were found for import.</li></ul>';
        return;
    }

    if ($StvState === 'clear_failed') {
        echo '<ul class="errormessage"><li>Imported ' . esc_html((string) $StvImported) . ' request(s), but the log file could not be cleared afterwards. Re-importing now may create duplicates.</li></ul>';
        return;
    }

    echo '<ul class="errormessage"><li>STV could not import the current log file into the database.</li></ul>';
}

function lc_stv_render_import_form(): void {
    $StvLogEntries = lc_stv_count_log_entries();
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('lc_stv_import_log'); ?>
        <input type="hidden" name="action" value="lc_stv_import_log" />
        <span><strong>Current log entries:</strong> <?php echo esc_html((string) $StvLogEntries); ?></span>
        <button type="submit" class="button button-primary">Import log now</button>
    </form>
    <?php
}

function lc_stv_get_prepend_notice_status(): array {
    $StvCurrent = lc_stv_get_existing_auto_prepend_file();
    $StvExpected = lc_stv_get_prepend_file();
    $StvCurrentNormalized = lc_stv_normalize_file_path($StvCurrent);
    $StvExpectedNormalized = lc_stv_normalize_file_path($StvExpected);

    if ($StvCurrentNormalized !== '' && $StvExpectedNormalized !== '' && $StvCurrentNormalized === $StvExpectedNormalized) {
        return [
            'status' => 'ok',
            'current' => $StvCurrent,
            'expected' => $StvExpected,
        ];
    }

    if ($StvCurrentNormalized === '') {
        return [
            'status' => 'missing',
            'current' => $StvCurrent,
            'expected' => $StvExpected,
        ];
    }

    if (stripos($StvCurrentNormalized, 'wordfence') !== false) {
        return [
            'status' => 'wordfence',
            'current' => $StvCurrent,
            'expected' => $StvExpected,
        ];
    }

    return [
        'status' => 'foreign',
        'current' => $StvCurrent,
        'expected' => $StvExpected,
    ];
}

function lc_stv_render_prepend_status_notice(): void {
    if (!is_admin()) {
        return;
    }

    if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
        return;
    }

    if (!lc_stv_is_enabled()) {
        return;
    }

    $StvStatus = lc_stv_get_prepend_notice_status();

    if (($StvStatus['status'] ?? '') === 'ok') {
        return;
    }

    $StvNoticeClass = (($StvStatus['status'] ?? '') === 'wordfence') ? 'notice notice-warning' : 'notice notice-error';

    echo '<div class="' . esc_attr($StvNoticeClass) . '"><p>';

    if (($StvStatus['status'] ?? '') === 'missing') {
        echo 'LiteCache STV request capture is inactive because auto_prepend_file is not set to the STV prepend file. Please deactivate and reactivate STV, or review the prepend configuration.';
    } elseif (($StvStatus['status'] ?? '') === 'wordfence') {
        echo 'LiteCache STV request capture is inactive because Wordfence is currently configured as auto_prepend_file. Please deactivate and reactivate STV to recreate a compatible prepend setup.';
    } else {
        echo 'LiteCache STV request capture is inactive because another auto_prepend_file is configured. Please re-enable STV or review the prepend configuration.';
    }

    if (!empty($StvStatus['current'])) {
        echo '<br /><small>Current auto_prepend_file: ' . esc_html((string) $StvStatus['current']) . '</small>';
    }

    echo '</p></div>';
}

function lc_stv_get_current_page_number(): int {
    $StvPage = lc_stv_get_query_int('paged', 1);

    if ($StvPage < 1) {
        $StvPage = 1;
    }

    return $StvPage;
}

function lc_stv_get_records_per_page(): int {
    $StvAllowed = [25, 50, 100, 200];
    $StvPerPage = isset($_COOKIE['lc_stv_per_page']) ? (int) $_COOKIE['lc_stv_per_page'] : 25;

    if (!in_array($StvPerPage, $StvAllowed, true)) {
        $StvPerPage = 25;
    }

    return $StvPerPage;
}

function lc_stv_format_request_time(string $StvTime): string {
    if ($StvTime === '') {
        return '';
    }

    $StvTimestamp = strtotime($StvTime);

    if ($StvTimestamp === false) {
        return $StvTime;
    }

    return gmdate('d-m-Y H:i:s', $StvTimestamp);
}

function lc_stv_render_pagination_link(int $StvPage, string $StvLabel, bool $StvIsActive = false): void {
    if ($StvIsActive) {
        echo '<li><span class="nctn" aria-current="page">' . esc_html($StvLabel) . '</span></li>';
        return;
    }

    $StvArgs = array_merge(
            [
                'paged' => $StvPage,
                'orderby' => lc_stv_get_current_orderby(),
                'order' => lc_stv_get_current_order(),
            ],
            lc_stv_get_current_request_filter_query_args()
    );

    echo '<li><a href="' . esc_url(lc_stv_get_start_admin_url($StvArgs)) . '">' . esc_html($StvLabel) . '</a></li>';
}

function lc_stv_render_log_pagination(int $StvCurrentPage, int $StvTotalPages, int $StvTotalEntries,): void {
    $StvRecordsPerPage = lc_stv_get_records_per_page();
    if ($StvTotalEntries < 1) {
        return;
    }

    echo '<ul class="pagination">';
    lc_stv_render_per_page_form($StvRecordsPerPage);
    echo '<li class="pages">Page ' . esc_html((string) $StvCurrentPage) . ' of ' . esc_html((string) $StvTotalPages) . ' with totally ' . esc_html((string) $StvTotalEntries) . ' requests</li>';

    if ($StvTotalPages > 1) {
        $StvAdjacents = 2;
        $StvStart = max(1, $StvCurrentPage - $StvAdjacents);
        $StvEnd = min($StvTotalPages, $StvCurrentPage + $StvAdjacents);

        echo '<li class="items">';
        echo '<ul>';

        if ($StvCurrentPage > 1) {
            lc_stv_render_pagination_link(1, '«');
            lc_stv_render_pagination_link($StvCurrentPage - 1, '‹');
        } else {
            echo '<li><span class="nctn">«</span></li>';
            echo '<li><span class="nctn">‹</span></li>';
        }

        if ($StvStart > 1) {
            lc_stv_render_pagination_link(1, '1', $StvCurrentPage === 1);
        }

        if ($StvStart > 2) {
            echo '<li><span class="nctn">…</span></li>';
        }

        for ($StvPage = $StvStart; $StvPage <= $StvEnd; $StvPage++) {
            if ($StvPage === 1 || $StvPage === $StvTotalPages) {
                if ($StvPage === 1 && $StvStart > 1) {
                    continue;
                }

                if ($StvPage === $StvTotalPages && $StvEnd < $StvTotalPages) {
                    continue;
                }
            }

            lc_stv_render_pagination_link($StvPage, (string) $StvPage, $StvPage === $StvCurrentPage);
        }

        if ($StvEnd < ($StvTotalPages - 1)) {
            echo '<li><span class="nctn">…</span></li>';
        }

        if ($StvEnd < $StvTotalPages) {
            lc_stv_render_pagination_link($StvTotalPages, (string) $StvTotalPages, $StvCurrentPage === $StvTotalPages);
        }

        if ($StvCurrentPage < $StvTotalPages) {
            lc_stv_render_pagination_link($StvCurrentPage + 1, '›');
            lc_stv_render_pagination_link($StvTotalPages, '»');
        } else {
            echo '<li><span class="nctn">›</span></li>';
            echo '<li><span class="nctn">»</span></li>';
        }
        echo '</ul>';
        //echo '</li>';
    }

    echo '</li>';
    echo '</ul>';
    echo '<br />';
}

function litecache_stv_header_display(): void {
    $StvCurrentPage = lc_stv_get_query_key('page');
    $StvIsStartPage = ($StvCurrentPage === 'litecache-stv');
    $StvIsExcludesPage = ($StvCurrentPage === 'litecache-stv-excludes');
    ?>
    <header>
        <div class="logo">
            <a class="logo" href="<?php echo esc_url(admin_url('admin.php?page=litecache-stv')); ?>" title="LiteCache Suspicious Traffic Viewer">
                <span>LiteCache<sup>®</sup></span><span>Nothing but Speed</span>
            </a>
        </div>
        <div>
            <div>
                <h1>
                    <div>LiteCache - Suspicious Traffic Viewer (STV)</div>
                </h1>
            </div>
            <ul>
                <li<?php echo $StvIsStartPage ? ' class="active"' : ''; ?>><a href="<?php echo esc_url(admin_url('admin.php?page=litecache-stv')); ?>">Start</a></li>
                <li<?php echo $StvIsExcludesPage ? ' class="active"' : ''; ?>><a href="<?php echo esc_url(admin_url('admin.php?page=litecache-stv-excludes')); ?>">Excludes</a></li>
                <li>&nbsp;</li>
                <li>&nbsp;</li>
                <li>&nbsp;</li>
                <li>&nbsp;</li>
            </ul>
        </div>
    </header>
    <br />
    <?php
}

function litecache_stv_itsme() {
    $requested_headers = getallheaders();
    if (isset($requested_headers['x-custom-header'])) {
        return true;
    }
    return false;
}

function lc_stv_should_refresh_admin_checks(): bool {
    $StvNavigationKeys = [
        'search',
        'traffic_class',
        'filter_status',
        'filter_method',
        'paged',
        'orderby',
        'order',
    ];

    foreach ($StvNavigationKeys as $StvKey) {
        $StvValue = lc_stv_get_query_text($StvKey);

        if ($StvValue !== '') {
            return false;
        }
    }

    return true;
}

function lc_stv_get_admin_checks_cache_key(): string {
    return 'lc_stv_admin_checks';
}

function lc_stv_get_admin_checks_cache_ttl(): int {
    return HOUR_IN_SECONDS;
}

function lc_stv_get_admin_checks(bool $StvForceRefresh = false): array {
    $StvCacheKey = lc_stv_get_admin_checks_cache_key();

    if (!$StvForceRefresh) {
        $StvCached = get_transient($StvCacheKey);

        if (is_array($StvCached)) {
            return [
                'errors' => is_array($StvCached['errors'] ?? null) ? $StvCached['errors'] : [],
                'warnings' => is_array($StvCached['warnings'] ?? null) ? $StvCached['warnings'] : [],
            ];
        }
    }

    $StvErrors = function_exists('lc_stv_get_requirement_errors') ? lc_stv_get_requirement_errors() : [];
    $StvWarnings = function_exists('lc_stv_get_requirement_warnings') ? lc_stv_get_requirement_warnings() : [];

    if (function_exists('lc_stv_get_rewrite_probe_warning')) {
        $StvRewriteProbeWarning = lc_stv_get_rewrite_probe_warning();

        if ($StvRewriteProbeWarning !== '') {
            $StvWarnings[] = $StvRewriteProbeWarning;
        }
    }

    $StvData = [
        'errors' => $StvErrors,
        'warnings' => $StvWarnings,
    ];

    set_transient($StvCacheKey, $StvData, lc_stv_get_admin_checks_cache_ttl());

    return $StvData;
}

function lc_stv_ajax_get_agent_chart(): void {
    check_ajax_referer('lc_stv_chart', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error([
            'message' => 'Access denied.',
                ], 403);
    }

    $StvUaHash = lc_stv_get_post_text('ua_hash');
    $StvPayload = lc_stv_get_agent_chart_data($StvUaHash, 30);

    if (empty($StvPayload)) {
        wp_send_json_error([
            'message' => 'Chart data not found.',
                ], 404);
    }

    wp_send_json_success($StvPayload);
}

function lc_stv_enqueue_chart_config(): void {
    $StvPage = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';

    if ($StvPage !== 'litecache-stv') {
        return;
    }

    $StvChartConfig = wp_json_encode([
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('lc_stv_chart'),
    ]);

    if (!is_string($StvChartConfig)) {
        return;
    }

    wp_register_script('lc-stv-chart-config', false, [], LC_STV_VERSION, true);
    wp_enqueue_script('lc-stv-chart-config');
    wp_add_inline_script('lc-stv-chart-config', 'window.lcStvChart = ' . $StvChartConfig . ';', 'before');
}
