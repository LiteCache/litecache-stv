<?php

defined('ABSPATH') || exit;

function lc_stv_on_activation(): void {
    require_once LC_STV_DIR . 'inc/stv_control.php';
    require_once LC_STV_DIR . 'inc/stv_environment.php';
    require_once LC_STV_DIR . 'inc/stv_prepend_tools.php';
    require_once LC_STV_DIR . 'inc/stv_settings.php';

    if (!lc_stv_ensure_storage_dir()) {
        wp_die('The STV storage directory could not be created.');
    }

    lc_stv_install_db();

    $StvPluginHtaccessFile = lc_stv_get_plugin_htaccess_file();
    $StvPluginHtaccessContent = lc_stv_get_plugin_htaccess_content();

    if (@file_put_contents($StvPluginHtaccessFile, $StvPluginHtaccessContent, LOCK_EX) === false) {
        wp_die('The STV plugin .htaccess file could not be written.');
    }

    $StvRewriteProbeFile = lc_stv_get_rewrite_probe_file();
    $StvRewriteProbeBody = lc_stv_get_rewrite_probe_body();

    if (@file_put_contents($StvRewriteProbeFile, $StvRewriteProbeBody, LOCK_EX) === false) {
        wp_die('The STV rewrite probe file could not be written.');
    }

    clearstatcache(true, $StvPluginHtaccessFile);
    clearstatcache(true, $StvRewriteProbeFile);

    if (!lc_stv_disable()) {
        wp_die('The STV control file could not be removed.');
    }

    $StvCacheResult = lc_stv_remove_cache_compatibility_block();

    if (empty($StvCacheResult['success'])) {
        wp_die('The STV cache compatibility rules could not be removed.');
    }

    /*
     * auto_prepend is permanent STV infrastructure. Capture itself remains off
     * until the pid control file is explicitly created through the GUI.
     */
    lc_stv_prepare_prepend_configuration();

    if (function_exists('lc_stv_schedule_daily_import_event')) {
        lc_stv_schedule_daily_import_event();
    }

    if (function_exists('lc_stv_clear_requirement_report_cache')) {
        lc_stv_clear_requirement_report_cache();
    }
}

function lc_stv_on_deactivation(): void {
    require_once LC_STV_DIR . 'inc/stv_control.php';
    require_once LC_STV_DIR . 'inc/stv_settings.php';

    $StvCacheResult = lc_stv_remove_cache_compatibility_block();

    if (empty($StvCacheResult['success'])) {
        error_log('LiteCache STV could not remove its cache compatibility rules during plugin deactivation.');
    }

    if (!lc_stv_disable()) {
        error_log('LiteCache STV could not remove its control file during plugin deactivation.');
    }

    if (function_exists('lc_stv_unschedule_daily_import_event')) {
        lc_stv_unschedule_daily_import_event();
    }

    if (function_exists('lc_stv_clear_requirement_report_cache')) {
        lc_stv_clear_requirement_report_cache();
    }
}
