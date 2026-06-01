<?php

defined('ABSPATH') || exit;

function lc_stv_ensure_storage_dir(): bool {
    if (is_dir(LC_STV_STORAGE_DIR)) {
        return wp_is_writable(LC_STV_STORAGE_DIR);
    }

    if (!wp_mkdir_p(LC_STV_STORAGE_DIR)) {
        return false;
    }

    return wp_is_writable(LC_STV_STORAGE_DIR);
}

function lc_stv_is_storage_writable(): bool {
    if (!is_dir(LC_STV_STORAGE_DIR)) {
        return false;
    }

    return wp_is_writable(LC_STV_STORAGE_DIR);
}

function lc_stv_get_existing_auto_prepend_file(): string {
    $prepend = (string) ini_get('auto_prepend_file');

    if ($prepend === '') {
        return '';
    }

    if ($prepend === 'none') {
        return '';
    }

    return $prepend;
}

function lc_stv_is_wordfence_prepend(string $prepend_file = ''): bool {
    if ($prepend_file === '') {
        $prepend_file = lc_stv_get_existing_auto_prepend_file();
    }

    if ($prepend_file === '') {
        return false;
    }

    return stripos($prepend_file, 'wordfence-waf.php') !== false;
}

function lc_stv_get_environment_status(): array {
    $prepend_file = lc_stv_get_existing_auto_prepend_file();
    $storage_dir_exists = is_dir(LC_STV_STORAGE_DIR);
    $storage_dir_writable = lc_stv_is_storage_writable();

    return [
        'storage_dir' => LC_STV_STORAGE_DIR,
        'storage_dir_exists' => $storage_dir_exists,
        'storage_dir_writable' => $storage_dir_writable,
        'log_file' => LC_STV_LOG_FILE,
        'log_file_exists' => file_exists(LC_STV_LOG_FILE),
        'has_prepend_file' => ($prepend_file !== ''),
        'existing_prepend_file' => $prepend_file,
        'is_wordfence_prepend' => lc_stv_is_wordfence_prepend($prepend_file),
    ];
}

function lc_stv_get_requirement_errors(): array {
    $StvEnv = lc_stv_get_environment_status();
    $StvErrors = [];

    if (!$StvEnv['storage_dir_exists']) {
        $StvErrors[] = 'The STV storage directory does not exist.';
    }

    if (!$StvEnv['storage_dir_writable']) {
        $StvErrors[] = 'The STV storage directory is not writable.';
    }

    if (!file_exists(lc_stv_get_plugin_htaccess_file())) {
        $StvErrors[] = 'The STV plugin .htaccess file is missing.';
    }

    if (!lc_stv_has_prepend_protection_rule()) {
        $StvErrors[] = 'Direct access protection for stv_prepend.php is missing.';
    }

    if (!lc_stv_has_prepend_file()) {
        $StvErrors[] = 'The STV prepend file is missing.';
    }

    if (function_exists('lc_stv_collect_requirement_errors')) {
        $StvErrors = array_merge($StvErrors, lc_stv_collect_requirement_errors());
    }

    return array_values(array_unique($StvErrors));
}

function lc_stv_get_requirement_warnings(): array {
    if (!function_exists('lc_stv_collect_requirement_warnings')) {
        return [];
    }

    return lc_stv_collect_requirement_warnings();
}
