<?php

defined('ABSPATH') || exit;

function lc_stv_on_activation(): void {
    if (!lc_stv_ensure_storage_dir()) {
        wp_die('The STV storage directory could not be created.');
    }

    lc_stv_install_db();

    if (!lc_stv_enable()) {
        wp_die('The STV control file could not be created.');
    }

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

    if (function_exists('lc_stv_prepare_prepend_configuration')) {
        update_option('lc_stv_prepend_setup_state', lc_stv_prepare_prepend_configuration(), false);
    }

    clearstatcache(true, $StvPluginHtaccessFile);
    clearstatcache(true, $StvRewriteProbeFile);

    if (function_exists('lc_stv_schedule_daily_import_event')) {
        lc_stv_schedule_daily_import_event();
    }

    if (function_exists('lc_stv_clear_requirement_report_cache')) {
        lc_stv_clear_requirement_report_cache();
    }
}

function lc_stv_on_deactivation(): void {
    if (function_exists('lc_stv_disable')) {
        lc_stv_disable();
    }

    if (function_exists('lc_stv_schedule_daily_import_event')) {
        lc_stv_schedule_daily_import_event();
    }

    if (function_exists('lc_stv_clear_requirement_report_cache')) {
        lc_stv_clear_requirement_report_cache();
    }
}
