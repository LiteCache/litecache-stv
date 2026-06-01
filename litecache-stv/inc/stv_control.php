<?php

if (!defined('ABSPATH')) {
    $LcStvScriptFilename = filter_input(INPUT_SERVER, 'SCRIPT_FILENAME', FILTER_UNSAFE_RAW);
    $LcStvScriptFilename = is_string($LcStvScriptFilename) ? str_replace("\0", '', $LcStvScriptFilename) : '';

    if ($LcStvScriptFilename !== '' && realpath($LcStvScriptFilename) === __FILE__) {
        http_response_code(403);
        exit;
    }
}

function lc_stv_get_control_dir(): string {
    return dirname(__DIR__, 3) . '/litecache-stv/';
}

function lc_stv_get_control_file(): string {
    return lc_stv_get_control_dir() . 'pid';
}

function lc_stv_is_enabled(): bool {
    return is_file(lc_stv_get_control_file());
}

function lc_stv_enable(): bool {
    $StvControlDir = lc_stv_get_control_dir();

    if (!is_dir($StvControlDir)) {
        if (function_exists('wp_mkdir_p')) {
            if (!wp_mkdir_p($StvControlDir) && !is_dir($StvControlDir)) {
                return false;
            }
        } else {
            if (!@mkdir($StvControlDir, 0755, true) && !is_dir($StvControlDir)) {
                return false;
            }
        }
    }

    $StvControlFile = lc_stv_get_control_file();

    if (is_file($StvControlFile)) {
        return true;
    }

    return file_put_contents($StvControlFile, '') !== false;
}

function lc_stv_disable(): bool {
    $StvControlFile = lc_stv_get_control_file();

    if (!is_file($StvControlFile)) {
        return true;
    }

    if (function_exists('wp_delete_file')) {
        return wp_delete_file($StvControlFile);
    }

    return @unlink($StvControlFile);
}
