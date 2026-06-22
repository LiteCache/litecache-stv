<?php

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

$lc_stv_plugin_dir = __DIR__ . '/';
$lc_stv_content_dir = dirname(__DIR__, 2) . '/';
$lc_stv_storage_dir = $lc_stv_content_dir . 'cache/litecache-stv/';
$lc_stv_control_dir = $lc_stv_content_dir . 'litecache-stv/';
$lc_stv_plugin_htaccess_file = $lc_stv_plugin_dir . '.htaccess';
$lc_stv_rewrite_probe_file = $lc_stv_plugin_dir . 'stv-rewrite-probe.txt';
$lc_stv_root_htaccess_file = ABSPATH . '.htaccess';
$lc_stv_user_ini_file = ABSPATH . '.user.ini';

function lc_stv_uninstall_boot_filesystem(): bool {
    static $lc_stv_booted = null;

    if ($lc_stv_booted !== null) {
        return $lc_stv_booted;
    }

    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $lc_stv_booted = function_exists('WP_Filesystem') && WP_Filesystem();

    if (!$lc_stv_booted) {
        return false;
    }

    return isset($GLOBALS['wp_filesystem']) && is_object($GLOBALS['wp_filesystem']);
}

function lc_stv_uninstall_delete_file(string $lc_stv_file): void {
    if (!is_file($lc_stv_file)) {
        return;
    }

    if (function_exists('wp_delete_file')) {
        wp_delete_file($lc_stv_file);

        if (!is_file($lc_stv_file)) {
            return;
        }
    }

    if (!lc_stv_uninstall_boot_filesystem()) {
        return;
    }

    global $wp_filesystem;

    $wp_filesystem->delete($lc_stv_file, false, 'f');
}

function lc_stv_uninstall_delete_dir_if_empty(string $lc_stv_dir): void {
    if (!is_dir($lc_stv_dir)) {
        return;
    }

    $lc_stv_items = @scandir($lc_stv_dir);

    if (!is_array($lc_stv_items)) {
        return;
    }

    $lc_stv_items = array_diff($lc_stv_items, ['.', '..']);

    if (!empty($lc_stv_items)) {
        return;
    }

    if (!lc_stv_uninstall_boot_filesystem()) {
        return;
    }

    global $wp_filesystem;

    $wp_filesystem->delete($lc_stv_dir, false, 'd');
}

function lc_stv_uninstall_get_prepend_marker_begin(string $lc_stv_type): string {
    return '# BEGIN LiteCache STV auto_prepend ' . $lc_stv_type;
}

function lc_stv_uninstall_get_prepend_marker_end(string $lc_stv_type): string {
    return '# END LiteCache STV auto_prepend ' . $lc_stv_type;
}

function lc_stv_uninstall_get_cache_compatibility_marker_begin(): string {
    return '# BEGIN LiteCache STV Cache Compatibility';
}

function lc_stv_uninstall_get_cache_compatibility_marker_end(): string {
    return '# END LiteCache STV Cache Compatibility';
}

function lc_stv_uninstall_strip_managed_prepend_block(string $lc_stv_content, string $lc_stv_type): string {
    $lc_stv_begin = preg_quote(lc_stv_uninstall_get_prepend_marker_begin($lc_stv_type), '/');
    $lc_stv_end = preg_quote(lc_stv_uninstall_get_prepend_marker_end($lc_stv_type), '/');

    return (string) preg_replace('/\R?' . $lc_stv_begin . '.*?' . $lc_stv_end . '\R?/s', PHP_EOL, $lc_stv_content);
}

function lc_stv_uninstall_strip_cache_compatibility_block(string $lc_stv_content): string {
    $lc_stv_begin = preg_quote(lc_stv_uninstall_get_cache_compatibility_marker_begin(), '/');
    $lc_stv_end = preg_quote(lc_stv_uninstall_get_cache_compatibility_marker_end(), '/');

    return (string) preg_replace('/\R?' . $lc_stv_begin . '.*?' . $lc_stv_end . '\R?/s', PHP_EOL, $lc_stv_content);
}

function lc_stv_uninstall_cleanup_managed_prepend_file(string $lc_stv_file, string $lc_stv_type): void {
    if (!is_file($lc_stv_file)) {
        return;
    }

    $lc_stv_content = @file_get_contents($lc_stv_file);

    if (!is_string($lc_stv_content) || $lc_stv_content === '') {
        return;
    }

    $lc_stv_updated = lc_stv_uninstall_strip_managed_prepend_block($lc_stv_content, $lc_stv_type);
    $lc_stv_updated = trim($lc_stv_updated);

    if ($lc_stv_updated === '') {
        lc_stv_uninstall_delete_file($lc_stv_file);
        return;
    }

    @file_put_contents($lc_stv_file, $lc_stv_updated . PHP_EOL, LOCK_EX);
}

function lc_stv_uninstall_cleanup_cache_compatibility_file(string $lc_stv_file): void {
    if (!is_file($lc_stv_file)) {
        return;
    }

    $lc_stv_content = @file_get_contents($lc_stv_file);

    if (!is_string($lc_stv_content) || $lc_stv_content === '') {
        return;
    }

    $lc_stv_updated = lc_stv_uninstall_strip_cache_compatibility_block($lc_stv_content);
    $lc_stv_updated = trim($lc_stv_updated);

    if ($lc_stv_updated === '') {
        lc_stv_uninstall_delete_file($lc_stv_file);
        return;
    }

    @file_put_contents($lc_stv_file, $lc_stv_updated . PHP_EOL, LOCK_EX);
}

function lc_stv_uninstall_get_expected_plugin_htaccess_content(): string {
    $lc_stv_lines = [
        'RewriteEngine On',
        'RewriteRule ^stv_prepend\.php$ - [F,L]',
        'RewriteRule ^stv-probe\.txt$ stv-rewrite-probe.txt [L]',
    ];

    return implode(PHP_EOL, $lc_stv_lines) . PHP_EOL;
}

function lc_stv_uninstall_maybe_delete_plugin_htaccess(string $lc_stv_file): void {
    if (!is_file($lc_stv_file)) {
        return;
    }

    $lc_stv_content = @file_get_contents($lc_stv_file);

    if (!is_string($lc_stv_content)) {
        return;
    }

    $lc_stv_expected = lc_stv_uninstall_get_expected_plugin_htaccess_content();

    if (trim($lc_stv_content) === trim($lc_stv_expected)) {
        lc_stv_uninstall_delete_file($lc_stv_file);
    }
}

// Remove DB tables.
$wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . 'stv_requests`');
$wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . 'stv_agents`');
$wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . 'stv_agent_hits`');

// Remove scheduled events and cached admin checks.
wp_clear_scheduled_hook('lc_stv_daily_import_event');
delete_transient('lc_stv_admin_checks');

// Remove plugin options.
delete_option('stv_db_version');
delete_option('lc_stv_prepend_setup_state');
delete_option('lc_stv_rewrite_check_state_v2');
delete_option('lc_stv_cache_compatibility_mode');

// Remove STV-managed root configuration.
lc_stv_uninstall_cleanup_managed_prepend_file($lc_stv_root_htaccess_file, 'htaccess');
lc_stv_uninstall_cleanup_managed_prepend_file($lc_stv_user_ini_file, 'user_ini');
lc_stv_uninstall_cleanup_cache_compatibility_file($lc_stv_root_htaccess_file);

// Remove plugin-local probe files.
lc_stv_uninstall_delete_file($lc_stv_rewrite_probe_file);
lc_stv_uninstall_maybe_delete_plugin_htaccess($lc_stv_plugin_htaccess_file);

// Remove storage files.
lc_stv_uninstall_delete_file($lc_stv_storage_dir . 'requests.log');
lc_stv_uninstall_delete_dir_if_empty($lc_stv_storage_dir);
lc_stv_uninstall_delete_dir_if_empty(dirname(rtrim($lc_stv_storage_dir, '/')));

// Remove control files.
lc_stv_uninstall_delete_file($lc_stv_control_dir . 'pid');
lc_stv_uninstall_delete_file($lc_stv_control_dir . 'custom-excludes.php');
lc_stv_uninstall_delete_dir_if_empty($lc_stv_control_dir);
