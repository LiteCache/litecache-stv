<?php

defined('ABSPATH') || exit;

$RushPluginUrl = str_replace('inc/', '', plugin_dir_url(__FILE__));
define('LITECACHE_STV_PLUGIN_URL', $RushPluginUrl);

function litecache_stv_enqueue_static() {
    $StvDetect = new LC_STV_Mobile_Detect;
    if ($StvDetect->StvIsMobile() && !$StvDetect->StvIsTablet()) {
        $RushCssLastMod = (filemtime(WP_PLUGIN_DIR . '/litecache-stv/admin/css/styles_mobile.css'));
        wp_enqueue_style('litecache-stv', LITECACHE_STV_PLUGIN_URL . 'admin/css/styles_mobile.css', array(), $RushCssLastMod, 'all');
    } else {
        $RushCssLastMod = (filemtime(WP_PLUGIN_DIR . '/litecache-stv/admin/css/styles.css'));
        wp_enqueue_style('litecache-stv', LITECACHE_STV_PLUGIN_URL . 'admin/css/styles.css', array(), $RushCssLastMod, 'all');
        $JsLastMod = (filemtime(WP_PLUGIN_DIR . '/litecache-stv/admin/js/d3.v7.min.js'));
        wp_enqueue_script('litecache-stv', LITECACHE_STV_PLUGIN_URL . 'admin/js/d3.v7.min.js', array(), $JsLastMod, false);
    }
}

add_action('admin_enqueue_scripts', 'litecache_stv_enqueue_static');

