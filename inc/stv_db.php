<?php

defined('ABSPATH') || exit;

define('STV_DB_VERSION', '1.2.0');

/**
 * Create or update STV database tables.
 */
function lc_stv_install_db(): void {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    $table_agents = $wpdb->prefix . 'stv_agents';
    $table_agent_hits = $wpdb->prefix . 'stv_agent_hits';
    $table_requests = $wpdb->prefix . 'stv_requests';

    $sql_agents = "CREATE TABLE {$table_agents} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        ua_hash char(40) NOT NULL,
        user_agent text NOT NULL,
        client_class tinyint(3) unsigned NOT NULL DEFAULT 0,
        reason_mask bigint(20) unsigned NOT NULL DEFAULT 0,
        hits bigint(20) unsigned NOT NULL DEFAULT 0,
        days int(10) unsigned NOT NULL DEFAULT 0,
        hits_404 bigint(20) unsigned NOT NULL DEFAULT 0,
        hits_410 bigint(20) unsigned NOT NULL DEFAULT 0,
        first_seen datetime NOT NULL,
        last_seen datetime NOT NULL,
        last_status smallint(5) unsigned NOT NULL DEFAULT 0,
        last_uri varchar(1024) NOT NULL DEFAULT '',
        last_country char(2) NOT NULL DEFAULT '',
        PRIMARY KEY  (id),
        UNIQUE KEY ua_hash (ua_hash),
        KEY client_class (client_class),
        KEY hits (hits),
        KEY days (days),
        KEY last_seen (last_seen)
    ) {$charset_collate};";

    $sql_agent_hits = "CREATE TABLE {$table_agent_hits} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        ua_hash char(40) NOT NULL,
        hit_date date NOT NULL,
        hits int(10) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        UNIQUE KEY ua_hash_date (ua_hash, hit_date),
        KEY ua_hash (ua_hash),
        KEY hit_date (hit_date)
    ) {$charset_collate};";

    $sql_requests = "CREATE TABLE {$table_requests} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        request_time datetime NOT NULL,
        method varchar(10) NOT NULL DEFAULT '',
        uri varchar(2048) NOT NULL DEFAULT '',
        status smallint(5) unsigned NOT NULL DEFAULT 0,
        ip varchar(45) NOT NULL DEFAULT '',
        ua_hash char(40) NOT NULL DEFAULT '',
        user_agent text NOT NULL,
        traffic_class varchar(32) NOT NULL DEFAULT '',
        matched_agent varchar(191) NOT NULL DEFAULT '',
        bot_verification varchar(20) NOT NULL DEFAULT '',
        PRIMARY KEY  (id),
        KEY request_time (request_time),
        KEY status (status),
        KEY method (method),
        KEY ip (ip),
        KEY ua_hash (ua_hash),
        KEY traffic_class (traffic_class),
        KEY matched_agent (matched_agent),
        KEY bot_verification (bot_verification)
    ) {$charset_collate};";

    dbDelta($sql_agents);
    dbDelta($sql_agent_hits);
    dbDelta($sql_requests);

    update_option('stv_db_version', STV_DB_VERSION);
}

/**
 * Run DB upgrades when version changes.
 */
function lc_stv_get_agents_table_name(): string {
    global $wpdb;

    return $wpdb->prefix . 'stv_agents';
}

function lc_stv_get_agent_hits_table_name(): string {
    global $wpdb;

    return $wpdb->prefix . 'stv_agent_hits';
}

function lc_stv_get_requests_table_name(): string {
    global $wpdb;

    return $wpdb->prefix . 'stv_requests';
}

function lc_stv_clear_requests_table(): bool {
    global $wpdb;

    $StvTable = lc_stv_get_requests_table_name();
    $StvResult = $wpdb->query("DELETE FROM {$StvTable}");

    return $StvResult !== false;
}

function lc_stv_delete_old_agent_hits(int $StvRetentionDays = 30) {
    global $wpdb;

    $StvRetentionDays = max(1, $StvRetentionDays);
    $StvTable = lc_stv_get_agent_hits_table_name();
    $StvCutoffDate = gmdate('Y-m-d', strtotime('-' . $StvRetentionDays . ' days'));
    $StvSql = $wpdb->prepare("DELETE FROM {$StvTable} WHERE hit_date < %s", $StvCutoffDate);
    $StvResult = $wpdb->query($StvSql);

    if ($StvResult === false) {
        return new WP_Error('stv_agent_hits_cleanup_failed', 'Could not clean old stv_agent_hits rows.');
    }

    return (int) $StvResult;
}

function lc_stv_delete_old_agents(int $StvRetentionDays = 30) {
    global $wpdb;

    $StvRetentionDays = max(1, $StvRetentionDays);
    $StvTable = lc_stv_get_agents_table_name();
    $StvCutoffDateTime = gmdate('Y-m-d H:i:s', strtotime('-' . $StvRetentionDays . ' days'));
    $StvSql = $wpdb->prepare("DELETE FROM {$StvTable} WHERE last_seen < %s", $StvCutoffDateTime);
    $StvResult = $wpdb->query($StvSql);

    if ($StvResult === false) {
        return new WP_Error('stv_agents_cleanup_failed', 'Could not clean old stv_agents rows.');
    }

    return (int) $StvResult;
}

function lc_stv_get_request_orderby_sql(string $StvOrderBy): string {
    switch ($StvOrderBy) {
        case 'method':
            return 'r.method';

        case 'uri':
            return 'r.uri';

        case 'status':
            return 'r.status';

        case 'hits':
            return 'a.hits';

        case 'traffic_class':
            return 'r.traffic_class';

        case 'ip':
            return 'r.ip';

        case 'user_agent':
            return 'r.user_agent';

        case 'time':
        default:
            return 'r.request_time';
    }
}

function lc_stv_get_request_order_sql(string $StvOrder): string {
    $StvOrder = strtoupper($StvOrder);

    if ($StvOrder !== 'ASC' && $StvOrder !== 'DESC') {
        return 'DESC';
    }

    return $StvOrder;
}

function lc_stv_normalize_request_filters(array $StvFilters): array {
    $StvNormalized = [
        'traffic_class' => '',
        'status' => '',
        'method' => '',
        'search' => '',
    ];

    $StvAllowedTrafficClasses = [
        'known_ki_crawler',
        'known_bot',
        'suspicious',
        'human_like',
    ];

    $StvTrafficClass = trim((string) ($StvFilters['traffic_class'] ?? ''));

    if (in_array($StvTrafficClass, $StvAllowedTrafficClasses, true)) {
        $StvNormalized['traffic_class'] = $StvTrafficClass;
    }

    $StvStatus = trim((string) ($StvFilters['status'] ?? ''));

    if ($StvStatus !== '' && ctype_digit($StvStatus)) {
        $StvNormalized['status'] = (string) ((int) $StvStatus);
    }

    $StvMethod = strtoupper(trim((string) ($StvFilters['method'] ?? '')));

    if ($StvMethod !== '' && preg_match('/^[A-Z]+$/', $StvMethod)) {
        $StvNormalized['method'] = $StvMethod;
    }

    $StvSearch = trim((string) ($StvFilters['search'] ?? ''));

    if ($StvSearch !== '') {
        $StvNormalized['search'] = substr($StvSearch, 0, 200);
    }

    return $StvNormalized;
}

function lc_stv_build_request_filters_sql(array $StvFilters): array {
    global $wpdb;

    $StvFilters = lc_stv_normalize_request_filters($StvFilters);
    $StvWhere = [];
    $StvParams = [];

    if ($StvFilters['traffic_class'] !== '') {
        $StvWhere[] = 'r.traffic_class = %s';
        $StvParams[] = $StvFilters['traffic_class'];
    }

    if ($StvFilters['status'] !== '') {
        $StvWhere[] = 'r.status = %d';
        $StvParams[] = (int) $StvFilters['status'];
    }

    if ($StvFilters['method'] !== '') {
        $StvWhere[] = 'r.method = %s';
        $StvParams[] = $StvFilters['method'];
    }

    if ($StvFilters['search'] !== '') {
        $StvLike = '%' . $wpdb->esc_like($StvFilters['search']) . '%';
        $StvWhere[] = '(r.uri LIKE %s OR r.ip LIKE %s OR r.user_agent LIKE %s)';
        $StvParams[] = $StvLike;
        $StvParams[] = $StvLike;
        $StvParams[] = $StvLike;
    }

    if (empty($StvWhere)) {
        return [
            'sql' => '',
            'params' => [],
        ];
    }

    return [
        'sql' => ' WHERE ' . implode(' AND ', $StvWhere),
        'params' => $StvParams,
    ];
}

function lc_stv_get_distinct_request_statuses(): array {
    global $wpdb;

    $StvTable = lc_stv_get_requests_table_name();
    $StvResults = $wpdb->get_col("SELECT DISTINCT status FROM {$StvTable} WHERE status > 0 ORDER BY status ASC");

    if (!is_array($StvResults)) {
        return [];
    }

    return array_map('intval', $StvResults);
}

function lc_stv_get_distinct_request_methods(): array {
    global $wpdb;

    $StvTable = lc_stv_get_requests_table_name();
    $StvResults = $wpdb->get_col("SELECT DISTINCT method FROM {$StvTable} WHERE method <> '' ORDER BY method ASC");

    if (!is_array($StvResults)) {
        return [];
    }

    return array_values(array_filter(array_map(static function ($StvMethod): string {
                        return strtoupper(trim((string) $StvMethod));
                    }, $StvResults)));
}

function lc_stv_count_request_entries(array $StvFilters = []): int {
    global $wpdb;

    $StvTable = lc_stv_get_requests_table_name();
    $StvFilterSql = lc_stv_build_request_filters_sql($StvFilters);
    $StvSql = "SELECT COUNT(*) FROM {$StvTable} r" . $StvFilterSql['sql'];

    if (empty($StvFilterSql['params'])) {
        $StvCount = $wpdb->get_var($StvSql);
    } else {
        $StvCount = $wpdb->get_var($wpdb->prepare($StvSql, $StvFilterSql['params']));
    }

    return max(0, (int) $StvCount);
}

function lc_stv_get_request_entries_slice(int $StvOffset, int $StvLimit, string $StvOrderBy, string $StvOrder, array $StvFilters = []): array {
    global $wpdb;

    $StvRequestsTable = lc_stv_get_requests_table_name();
    $StvAgentsTable = lc_stv_get_agents_table_name();
    $StvOffset = max(0, $StvOffset);
    $StvLimit = max(1, $StvLimit);
    $StvOrderBySql = lc_stv_get_request_orderby_sql($StvOrderBy);
    $StvOrderSql = lc_stv_get_request_order_sql($StvOrder);
    $StvFilterSql = lc_stv_build_request_filters_sql($StvFilters);

    $StvSql = "
        SELECT
            r.ua_hash,
            r.request_time AS time,
            r.method,
            r.uri,
            r.status,
            r.ip,
            r.user_agent,
            r.traffic_class,
            r.matched_agent,
            r.bot_verification,
            COALESCE(a.hits, 0) AS hits
        FROM {$StvRequestsTable} r
        LEFT JOIN {$StvAgentsTable} a
            ON a.ua_hash = r.ua_hash
        {$StvFilterSql['sql']}
        ORDER BY {$StvOrderBySql} {$StvOrderSql}, r.request_time DESC
        LIMIT %d OFFSET %d
    ";

    $StvParams = array_merge($StvFilterSql['params'], [$StvLimit, $StvOffset]);

    $StvResults = $wpdb->get_results(
            $wpdb->prepare($StvSql, $StvParams),
            ARRAY_A
    );

    if (!is_array($StvResults)) {
        return [];
    }

    return $StvResults;
}

function lc_stv_map_traffic_class_to_client_class(string $StvTrafficClass): int {
    switch ($StvTrafficClass) {
        case 'suspicious':
            return 1;

        case 'known_bot':
            return 2;

        case 'known_ki_crawler':
            return 3;

        case 'human_like':
            return 4;
    }

    return 0;
}

function lc_stv_normalize_request_time_for_db(string $StvTime): string {
    $StvTimestamp = strtotime($StvTime);

    if ($StvTimestamp === false) {
        return gmdate('Y-m-d H:i:s');
    }

    return gmdate('Y-m-d H:i:s', $StvTimestamp);
}

function lc_stv_matched_agent_contains(string $StvMatchedAgent, string $StvNeedle): bool {
    $StvNeedle = strtolower(trim($StvNeedle));

    if ($StvNeedle === '') {
        return false;
    }

    foreach (explode('+', strtolower($StvMatchedAgent)) as $StvPart) {
        if (trim($StvPart) === $StvNeedle) {
            return true;
        }
    }

    return false;
}

function lc_stv_is_googlebot_candidate(array $StvEntry): bool {
    $StvTrafficClass = trim((string) ($StvEntry['traffic_class'] ?? ''));
    $StvMatchedAgent = trim((string) ($StvEntry['matched_agent'] ?? ''));

    return $StvTrafficClass === 'known_bot' && lc_stv_matched_agent_contains($StvMatchedAgent, 'googlebot');
}

function lc_stv_entries_contain_googlebot(array $StvEntries): bool {
    foreach ($StvEntries as $StvEntry) {
        if (is_array($StvEntry) && lc_stv_is_googlebot_candidate($StvEntry)) {
            return true;
        }
    }

    return false;
}

function lc_stv_get_google_common_crawler_cidrs(): array {
    static $StvCidrs = null;

    if (is_array($StvCidrs)) {
        return $StvCidrs;
    }

    $StvCached = get_transient('lc_stv_google_common_crawler_cidrs');

    if (is_array($StvCached)) {
        $StvCidrs = $StvCached;
        return $StvCidrs;
    }

    $StvResponse = wp_remote_get(
            'https://developers.google.com/static/search/apis/ipranges/common-crawlers.json',
            [
                'timeout' => 5,
                'redirection' => 2,
            ]
    );

    if (is_wp_error($StvResponse) || (int) wp_remote_retrieve_response_code($StvResponse) !== 200) {
        $StvCidrs = [];
        return $StvCidrs;
    }

    $StvData = json_decode(wp_remote_retrieve_body($StvResponse), true);

    if (!is_array($StvData) || empty($StvData['prefixes']) || !is_array($StvData['prefixes'])) {
        $StvCidrs = [];
        return $StvCidrs;
    }

    $StvCidrs = [];

    foreach ($StvData['prefixes'] as $StvPrefix) {
        if (!is_array($StvPrefix)) {
            continue;
        }

        foreach (['ipv4Prefix', 'ipv6Prefix'] as $StvKey) {
            $StvCidr = trim((string) ($StvPrefix[$StvKey] ?? ''));

            if ($StvCidr !== '' && lc_stv_is_valid_cidr($StvCidr)) {
                $StvCidrs[] = $StvCidr;
            }
        }
    }

    $StvCidrs = array_values(array_unique($StvCidrs));

    if (!empty($StvCidrs)) {
        set_transient('lc_stv_google_common_crawler_cidrs', $StvCidrs, DAY_IN_SECONDS);
    }

    return $StvCidrs;
}

function lc_stv_is_valid_cidr(string $StvCidr): bool {
    if (strpos($StvCidr, '/') === false) {
        return false;
    }

    [$StvRangeIp, $StvBits] = explode('/', $StvCidr, 2);
    $StvRangeBinary = @inet_pton($StvRangeIp);

    if ($StvRangeBinary === false || !is_numeric($StvBits)) {
        return false;
    }

    $StvBits = (int) $StvBits;
    $StvMaxBits = strlen($StvRangeBinary) * 8;

    return $StvBits >= 0 && $StvBits <= $StvMaxBits;
}

function lc_stv_ip_matches_cidr(string $StvIp, string $StvCidr): bool {
    if (!lc_stv_is_valid_cidr($StvCidr)) {
        return false;
    }

    [$StvRangeIp, $StvBits] = explode('/', $StvCidr, 2);

    $StvIpBinary = @inet_pton($StvIp);
    $StvRangeBinary = @inet_pton($StvRangeIp);

    if ($StvIpBinary === false || $StvRangeBinary === false || strlen($StvIpBinary) !== strlen($StvRangeBinary)) {
        return false;
    }

    $StvBits = (int) $StvBits;
    $StvBytes = intdiv($StvBits, 8);
    $StvRemainder = $StvBits % 8;

    if ($StvBytes > 0 && substr($StvIpBinary, 0, $StvBytes) !== substr($StvRangeBinary, 0, $StvBytes)) {
        return false;
    }

    if ($StvRemainder === 0) {
        return true;
    }

    $StvMask = (0xff << (8 - $StvRemainder)) & 0xff;

    return (ord($StvIpBinary[$StvBytes]) & $StvMask) === (ord($StvRangeBinary[$StvBytes]) & $StvMask);
}

function lc_stv_verify_googlebot_ip(string $StvIp, array $StvGoogleCidrs): string {
    $StvIp = trim($StvIp);

    if (empty($StvGoogleCidrs)) {
        return 'not_checked';
    }

    if ($StvIp === '' || @inet_pton($StvIp) === false) {
        return 'mismatch';
    }

    foreach ($StvGoogleCidrs as $StvCidr) {
        if (lc_stv_ip_matches_cidr($StvIp, $StvCidr)) {
            return 'verified';
        }
    }

    return 'mismatch';
}

function lc_stv_apply_googlebot_verification(string $StvTrafficClass, string $StvMatchedAgent, string $StvIp, array $StvGoogleCidrs): array {
    $StvResult = [
        'traffic_class' => $StvTrafficClass,
        'bot_verification' => '',
    ];

    if ($StvTrafficClass !== 'known_bot' || !lc_stv_matched_agent_contains($StvMatchedAgent, 'googlebot')) {
        return $StvResult;
    }

    $StvBotVerification = lc_stv_verify_googlebot_ip($StvIp, $StvGoogleCidrs);

    $StvResult['bot_verification'] = $StvBotVerification;

    if ($StvBotVerification === 'mismatch') {
        $StvResult['traffic_class'] = 'suspicious';
    }

    return $StvResult;
}

function lc_stv_import_log_entries_to_db(array $StvEntries) {
    global $wpdb;

    $StvRequestsTable = lc_stv_get_requests_table_name();
    $StvAgentsTable = lc_stv_get_agents_table_name();
    $StvAgentHitsTable = lc_stv_get_agent_hits_table_name();

    $StvImported = 0;
    $StvAgentStats = [];
    $StvAgentHitStats = [];
    $StvGoogleCidrs = [];

    if (lc_stv_entries_contain_googlebot($StvEntries)) {
        $StvGoogleCidrs = lc_stv_get_google_common_crawler_cidrs();
    }

    $StvUseTransaction = $wpdb->query('START TRANSACTION') !== false;

    foreach ($StvEntries as $StvEntry) {
        if (!is_array($StvEntry)) {
            continue;
        }

        $StvRequestTime = lc_stv_normalize_request_time_for_db((string) ($StvEntry['time'] ?? ''));
        $StvMethod = strtoupper(substr(trim((string) ($StvEntry['method'] ?? '')), 0, 10));
        $StvUri = substr((string) ($StvEntry['uri'] ?? ''), 0, 2048);
        $StvStatus = max(0, (int) ($StvEntry['status'] ?? 0));
        $StvIp = substr((string) ($StvEntry['ip'] ?? ''), 0, 45);
        $StvUserAgent = (string) ($StvEntry['user_agent'] ?? '');
        $StvTrafficClass = substr(trim((string) ($StvEntry['traffic_class'] ?? '')), 0, 32);
        $StvMatchedAgent = substr(trim((string) ($StvEntry['matched_agent'] ?? '')), 0, 191);
        $StvVerificationResult = lc_stv_apply_googlebot_verification($StvTrafficClass, $StvMatchedAgent, $StvIp, $StvGoogleCidrs);
        $StvTrafficClass = substr($StvVerificationResult['traffic_class'], 0, 32);
        $StvBotVerification = substr($StvVerificationResult['bot_verification'], 0, 20);
        $StvUaHash = sha1($StvUserAgent);
        $StvRequestDate = substr($StvRequestTime, 0, 10);

        $StvInserted = $wpdb->insert(
                $StvRequestsTable,
                [
                    'request_time' => $StvRequestTime,
                    'method' => $StvMethod,
                    'uri' => $StvUri,
                    'status' => $StvStatus,
                    'ip' => $StvIp,
                    'ua_hash' => $StvUaHash,
                    'user_agent' => $StvUserAgent,
                    'traffic_class' => $StvTrafficClass,
                    'matched_agent' => $StvMatchedAgent,
                    'bot_verification' => $StvBotVerification,
                ],
                ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($StvInserted === false) {
            if ($StvUseTransaction) {
                $wpdb->query('ROLLBACK');
            }

            return new WP_Error('stv_import_failed', 'Could not insert request into stv_requests.');
        }

        $StvImported++;

        if (!isset($StvAgentStats[$StvUaHash])) {
            $StvAgentStats[$StvUaHash] = [
                'user_agent' => $StvUserAgent,
                'client_class' => lc_stv_map_traffic_class_to_client_class($StvTrafficClass),
                'hits' => 0,
                'hits_404' => 0,
                'hits_410' => 0,
                'first_seen' => $StvRequestTime,
                'last_seen' => $StvRequestTime,
                'last_status' => $StvStatus,
                'last_uri' => substr($StvUri, 0, 1024),
            ];
        }

        $StvAgentStats[$StvUaHash]['hits']++;

        if ($StvStatus === 404) {
            $StvAgentStats[$StvUaHash]['hits_404']++;
        }

        if ($StvStatus === 410) {
            $StvAgentStats[$StvUaHash]['hits_410']++;
        }

        if ($StvRequestTime < $StvAgentStats[$StvUaHash]['first_seen']) {
            $StvAgentStats[$StvUaHash]['first_seen'] = $StvRequestTime;
        }

        if ($StvRequestTime >= $StvAgentStats[$StvUaHash]['last_seen']) {
            $StvAgentStats[$StvUaHash]['last_seen'] = $StvRequestTime;
            $StvAgentStats[$StvUaHash]['last_status'] = $StvStatus;
            $StvAgentStats[$StvUaHash]['last_uri'] = substr($StvUri, 0, 1024);
            $StvAgentStats[$StvUaHash]['client_class'] = lc_stv_map_traffic_class_to_client_class($StvTrafficClass);
        }

        if (!isset($StvAgentHitStats[$StvUaHash])) {
            $StvAgentHitStats[$StvUaHash] = [];
        }

        if (!isset($StvAgentHitStats[$StvUaHash][$StvRequestDate])) {
            $StvAgentHitStats[$StvUaHash][$StvRequestDate] = 0;
        }

        $StvAgentHitStats[$StvUaHash][$StvRequestDate]++;
    }

    foreach ($StvAgentHitStats as $StvUaHash => $StvDates) {
        foreach ($StvDates as $StvHitDate => $StvHits) {
            $StvSql = $wpdb->prepare(
                    "INSERT INTO {$StvAgentHitsTable} (ua_hash, hit_date, hits) VALUES (%s, %s, %d) ON DUPLICATE KEY UPDATE hits = hits + VALUES(hits)",
                    $StvUaHash,
                    $StvHitDate,
                    $StvHits
            );

            if ($wpdb->query($StvSql) === false) {
                if ($StvUseTransaction) {
                    $wpdb->query('ROLLBACK');
                }

                return new WP_Error('stv_import_failed', 'Could not update stv_agent_hits.');
            }
        }
    }

    foreach ($StvAgentStats as $StvUaHash => $StvAgentData) {
        $StvExisting = $wpdb->get_row(
                $wpdb->prepare(
                        "SELECT hits, hits_404, hits_410, first_seen, last_seen, last_status, last_uri, client_class FROM {$StvAgentsTable} WHERE ua_hash = %s",
                        $StvUaHash
                ),
                ARRAY_A
        );

        $StvDays = (int) $wpdb->get_var(
                        $wpdb->prepare(
                                "SELECT COUNT(*) FROM {$StvAgentHitsTable} WHERE ua_hash = %s",
                                $StvUaHash
                        )
                );

        if (is_array($StvExisting)) {
            $StvFirstSeen = ($StvExisting['first_seen'] <= $StvAgentData['first_seen']) ? $StvExisting['first_seen'] : $StvAgentData['first_seen'];
            $StvUseImportedLast = ($StvAgentData['last_seen'] >= $StvExisting['last_seen']);
            $StvLastSeen = $StvUseImportedLast ? $StvAgentData['last_seen'] : $StvExisting['last_seen'];
            $StvLastStatus = $StvUseImportedLast ? $StvAgentData['last_status'] : (int) $StvExisting['last_status'];
            $StvLastUri = $StvUseImportedLast ? $StvAgentData['last_uri'] : (string) $StvExisting['last_uri'];
            $StvClientClass = $StvAgentData['client_class'] !== 0 ? $StvAgentData['client_class'] : (int) $StvExisting['client_class'];

            $StvUpdated = $wpdb->update(
                    $StvAgentsTable,
                    [
                        'user_agent' => $StvAgentData['user_agent'],
                        'client_class' => $StvClientClass,
                        'hits' => (int) $StvExisting['hits'] + $StvAgentData['hits'],
                        'days' => $StvDays,
                        'hits_404' => (int) $StvExisting['hits_404'] + $StvAgentData['hits_404'],
                        'hits_410' => (int) $StvExisting['hits_410'] + $StvAgentData['hits_410'],
                        'first_seen' => $StvFirstSeen,
                        'last_seen' => $StvLastSeen,
                        'last_status' => $StvLastStatus,
                        'last_uri' => $StvLastUri,
                    ],
                    ['ua_hash' => $StvUaHash],
                    ['%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s'],
                    ['%s']
            );

            if ($StvUpdated === false) {
                if ($StvUseTransaction) {
                    $wpdb->query('ROLLBACK');
                }

                return new WP_Error('stv_import_failed', 'Could not update stv_agents.');
            }

            continue;
        }

        $StvInserted = $wpdb->insert(
                $StvAgentsTable,
                [
                    'ua_hash' => $StvUaHash,
                    'user_agent' => $StvAgentData['user_agent'],
                    'client_class' => $StvAgentData['client_class'],
                    'reason_mask' => 0,
                    'hits' => $StvAgentData['hits'],
                    'days' => $StvDays,
                    'hits_404' => $StvAgentData['hits_404'],
                    'hits_410' => $StvAgentData['hits_410'],
                    'first_seen' => $StvAgentData['first_seen'],
                    'last_seen' => $StvAgentData['last_seen'],
                    'last_status' => $StvAgentData['last_status'],
                    'last_uri' => $StvAgentData['last_uri'],
                    'last_country' => '',
                ],
                ['%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s']
        );

        if ($StvInserted === false) {
            if ($StvUseTransaction) {
                $wpdb->query('ROLLBACK');
            }

            return new WP_Error('stv_import_failed', 'Could not insert stv_agents row.');
        }
    }

    if ($StvUseTransaction) {
        $wpdb->query('COMMIT');
    }

    return [
        'imported' => $StvImported,
        'agents' => count($StvAgentStats),
    ];
}

function lc_stv_maybe_upgrade_db(): void {
    $installed_version = get_option('stv_db_version', '');

    if ($installed_version !== STV_DB_VERSION) {
        lc_stv_install_db();
    }
}

function lc_stv_get_agent_chart_data(string $StvUaHash, int $StvDays = 30): array {
    global $wpdb;

    $StvUaHash = strtolower(trim($StvUaHash));
    $StvDays = max(1, min(30, $StvDays));

    if (!preg_match('/^[a-f0-9]{40}$/', $StvUaHash)) {
        return [];
    }

    $StvAgentsTable = lc_stv_get_agents_table_name();
    $StvAgentHitsTable = lc_stv_get_agent_hits_table_name();
    $StvCutoffDate = gmdate('Y-m-d', strtotime('-' . $StvDays . ' days'));

    $StvAgent = $wpdb->get_row(
            $wpdb->prepare(
                    "SELECT user_agent
             FROM {$StvAgentsTable}
             WHERE ua_hash = %s
             LIMIT 1",
                    $StvUaHash
            ),
            ARRAY_A
    );

    if (!is_array($StvAgent)) {
        return [];
    }

    $StvRows = $wpdb->get_results(
            $wpdb->prepare(
                    "SELECT hit_date, hits
             FROM {$StvAgentHitsTable}
             WHERE ua_hash = %s
               AND hit_date >= %s
             ORDER BY hit_date ASC",
                    $StvUaHash,
                    $StvCutoffDate
            ),
            ARRAY_A
    );

    if (!is_array($StvRows)) {
        $StvRows = [];
    }

    $StvData = [];

    foreach ($StvRows as $StvRow) {
        $StvData[] = [
            'date' => (string) $StvRow['hit_date'],
            'hits' => (int) $StvRow['hits'],
        ];
    }

    return [
        'ua_hash' => $StvUaHash,
        'user_agent' => (string) $StvAgent['user_agent'],
        'data' => $StvData,
    ];
}

add_action('plugins_loaded', 'lc_stv_maybe_upgrade_db');
