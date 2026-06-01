<?php

defined('ABSPATH') || exit;

function lc_stv_get_log_file(): string {
    return LC_STV_LOG_FILE;
}

function lc_stv_open_log_file_read(string $StvFile): ?SplFileObject {
    try {
        return new SplFileObject($StvFile, 'rb');
    } catch (RuntimeException $StvException) {
        return null;
    }
}

function lc_stv_decode_log_line(string $StvLine): ?array {
    $StvLine = trim($StvLine);

    if ($StvLine === '') {
        return null;
    }

    $StvEntry = json_decode($StvLine, true);

    if (!is_array($StvEntry)) {
        return null;
    }

    return $StvEntry;
}

function lc_stv_read_log_lines(int $limit = 100): array {
    $StvFile = lc_stv_get_log_file();

    if ($limit < 1) {
        return [];
    }

    if (!file_exists($StvFile) || !is_readable($StvFile)) {
        return [];
    }

    $StvHandle = lc_stv_open_log_file_read($StvFile);

    if (!$StvHandle instanceof SplFileObject) {
        return [];
    }

    $StvLines = [];
    $StvBuffer = '';
    $StvPosition = -1;

    $StvHandle->fseek(0, SEEK_END);
    $StvFileSize = $StvHandle->ftell();

    if ($StvFileSize === 0) {
        return [];
    }

    while (count($StvLines) < $limit && (-$StvPosition) <= $StvFileSize) {
        $StvHandle->fseek($StvPosition, SEEK_END);
        $StvChar = $StvHandle->fgetc();

        if ($StvChar === "\n") {
            if ($StvBuffer !== '') {
                $StvLines[] = strrev($StvBuffer);
                $StvBuffer = '';
            }
        } else {
            $StvBuffer .= $StvChar;
        }

        $StvPosition--;
    }

    if ($StvBuffer !== '' && count($StvLines) < $limit) {
        $StvLines[] = strrev($StvBuffer);
    }

    return array_reverse($StvLines);
}

function lc_stv_get_log_entries(int $limit = 100): array {
    $StvLines = lc_stv_read_log_lines($limit);
    $StvEntries = [];

    foreach ($StvLines as $StvLine) {
        $StvEntry = lc_stv_decode_log_line($StvLine);

        if ($StvEntry === null) {
            continue;
        }

        $StvEntries[] = $StvEntry;
    }

    return $StvEntries;
}

function lc_stv_count_log_entries(): int {
    $StvFile = lc_stv_get_log_file();

    if (!file_exists($StvFile) || !is_readable($StvFile)) {
        return 0;
    }

    $StvHandle = lc_stv_open_log_file_read($StvFile);

    if (!$StvHandle instanceof SplFileObject) {
        return 0;
    }

    $StvCount = 0;

    while (!$StvHandle->eof()) {
        $StvLine = $StvHandle->fgets();

        if ($StvLine === false) {
            break;
        }

        if (lc_stv_decode_log_line($StvLine) === null) {
            continue;
        }

        $StvCount++;
    }

    return $StvCount;
}

function lc_stv_get_log_entries_slice(int $offset, int $limit): array {
    $StvFile = lc_stv_get_log_file();

    if ($offset < 0) {
        $offset = 0;
    }

    if ($limit < 1) {
        return [];
    }

    if (!file_exists($StvFile) || !is_readable($StvFile)) {
        return [];
    }

    $StvHandle = lc_stv_open_log_file_read($StvFile);

    if (!$StvHandle instanceof SplFileObject) {
        return [];
    }

    $StvHandle->fseek(0, SEEK_END);
    $StvFileSize = $StvHandle->ftell();

    if ($StvFileSize === 0) {
        return [];
    }

    $StvEntries = [];
    $StvBuffer = '';
    $StvPosition = -1;
    $StvSkipped = 0;

    while (count($StvEntries) < $limit && (-$StvPosition) <= $StvFileSize) {
        $StvHandle->fseek($StvPosition, SEEK_END);
        $StvChar = $StvHandle->fgetc();

        if ($StvChar === "\n") {
            if ($StvBuffer !== '') {
                $StvEntry = lc_stv_decode_log_line(strrev($StvBuffer));
                $StvBuffer = '';

                if ($StvEntry !== null) {
                    if ($StvSkipped < $offset) {
                        $StvSkipped++;
                    } else {
                        $StvEntries[] = $StvEntry;
                    }
                }
            }
        } else {
            $StvBuffer .= $StvChar;
        }

        $StvPosition--;
    }

    if ($StvBuffer !== '' && count($StvEntries) < $limit) {
        $StvEntry = lc_stv_decode_log_line(strrev($StvBuffer));

        if ($StvEntry !== null) {
            if ($StvSkipped < $offset) {
                $StvSkipped++;
            } else {
                $StvEntries[] = $StvEntry;
            }
        }
    }

    return $StvEntries;
}

function lc_stv_clear_log_file(): bool {
    $StvFile = lc_stv_get_log_file();

    if (!file_exists($StvFile)) {
        return true;
    }

    if (!wp_is_writable($StvFile)) {
        return false;
    }

    try {
        $StvHandle = new SplFileObject($StvFile, 'wb');
        $StvHandle->ftruncate(0);
    } catch (RuntimeException $StvException) {
        return false;
    }

    return true;
}
