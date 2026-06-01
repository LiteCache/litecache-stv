<?php

function lc_stv_get_custom_excludes_file(): string {
    return lc_stv_get_control_dir() . 'custom-excludes.php';
}

function lc_stv_get_default_custom_excludes(): array {
    return [
        'ips' => [],
        'user_agents' => [],
        'uris' => [],
    ];
}

function lc_stv_build_php_export_value(mixed $StvValue, int $StvIndent = 0): string {
    if (is_array($StvValue)) {
        if ($StvValue === []) {
            return '[]';
        }

        $StvIndentString = str_repeat('    ', $StvIndent);
        $StvChildIndentString = str_repeat('    ', $StvIndent + 1);
        $StvLines = ["["];

        foreach ($StvValue as $StvKey => $StvItem) {
            $StvLines[] = $StvChildIndentString
                    . lc_stv_build_php_export_value($StvKey, $StvIndent + 1)
                    . ' => '
                    . lc_stv_build_php_export_value($StvItem, $StvIndent + 1)
                    . ',';
        }

        $StvLines[] = $StvIndentString . ']';

        return implode("
", $StvLines);
    }

    if (is_string($StvValue)) {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $StvValue) . "'";
    }

    if (is_int($StvValue) || is_float($StvValue)) {
        return (string) $StvValue;
    }

    if (is_bool($StvValue)) {
        return $StvValue ? 'true' : 'false';
    }

    if ($StvValue === null) {
        return 'null';
    }

    return "''";
}

function lc_stv_normalize_custom_exclude_lines(array|string $input, int $max_length = 512): array {
    if (is_array($input)) {
        $StvLines = $input;
    } else {
        $StvLines = preg_split('/\r\n|\r|\n/', (string) $input) ?: [];
    }

    $StvNormalized = [];

    foreach ($StvLines as $StvLine) {
        $StvLine = trim((string) $StvLine);

        if ($StvLine === '') {
            continue;
        }

        if (strlen($StvLine) > $max_length) {
            $StvLine = substr($StvLine, 0, $max_length);
        }

        $StvNormalized[] = $StvLine;
    }

    return array_values(array_unique($StvNormalized));
}

function lc_stv_normalize_custom_excludes(array $input): array {
    $StvIps = lc_stv_normalize_custom_exclude_lines($input['ips'] ?? []);
    $StvUserAgents = lc_stv_normalize_custom_exclude_lines($input['user_agents'] ?? []);
    $StvUris = lc_stv_normalize_custom_exclude_lines($input['uris'] ?? []);

    $StvIps = array_values(array_filter($StvIps, static function (string $StvIp): bool {
                return filter_var($StvIp, FILTER_VALIDATE_IP) !== false;
            }));

    return [
        'ips' => $StvIps,
        'user_agents' => $StvUserAgents,
        'uris' => $StvUris,
    ];
}

function lc_stv_load_custom_excludes(): array {
    $StvFile = lc_stv_get_custom_excludes_file();

    if (!is_file($StvFile)) {
        return lc_stv_get_default_custom_excludes();
    }

    $StvData = require $StvFile;

    if (!is_array($StvData)) {
        return lc_stv_get_default_custom_excludes();
    }

    return lc_stv_normalize_custom_excludes($StvData);
}

function lc_stv_has_custom_excludes(array $custom_excludes): bool {
    return !empty($custom_excludes['ips']) || !empty($custom_excludes['user_agents']) || !empty($custom_excludes['uris']);
}

function lc_stv_save_custom_excludes(array $custom_excludes): bool {
    $StvCustomExcludes = lc_stv_normalize_custom_excludes($custom_excludes);
    $StvFile = lc_stv_get_custom_excludes_file();
    $StvDir = dirname($StvFile);

    if (!is_dir($StvDir)) {
        if (!@mkdir($StvDir, 0755, true) && !is_dir($StvDir)) {
            return false;
        }
    }

    if (!lc_stv_has_custom_excludes($StvCustomExcludes)) {
        if (is_file($StvFile)) {
            return @unlink($StvFile);
        }

        return true;
    }

    $StvContent = "<?php\n\nreturn " . lc_stv_build_php_export_value($StvCustomExcludes) . ";\n";

    return (@file_put_contents($StvFile, $StvContent, LOCK_EX) !== false);
}

function lc_stv_is_request_custom_excluded(array $custom_excludes, string $ip, string $user_agent, string $uri): bool {
    foreach (($custom_excludes['ips'] ?? []) as $StvIp) {
        if ($StvIp !== '' && $ip === $StvIp) {
            return true;
        }
    }

    foreach (($custom_excludes['user_agents'] ?? []) as $StvFragment) {
        if ($StvFragment !== '' && stripos($user_agent, $StvFragment) !== false) {
            return true;
        }
    }

    foreach (($custom_excludes['uris'] ?? []) as $StvFragment) {
        if ($StvFragment !== '' && stripos($uri, $StvFragment) !== false) {
            return true;
        }
    }

    return false;
}

function lc_stv_get_custom_excludes_form_values(): array {
    $StvCustomExcludes = lc_stv_load_custom_excludes();

    return [
        'ips' => implode("\n", $StvCustomExcludes['ips']),
        'user_agents' => implode("\n", $StvCustomExcludes['user_agents']),
        'uris' => implode("\n", $StvCustomExcludes['uris']),
    ];
}

function lc_stv_get_custom_excludes_admin_url(array $args = []): string {
    $StvArgs = $args + [
        'page' => 'litecache-stv-excludes',
    ];

    return add_query_arg($StvArgs, admin_url('admin.php'));
}

function lc_stv_handle_save_custom_excludes(): void {
    if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
        wp_die('You are not allowed to change the STV custom excludes.');
    }

    check_admin_referer('lc_stv_save_custom_excludes');

    $StvInputRaw = isset($_POST['lc_stv_custom_excludes']) ? wp_unslash($_POST['lc_stv_custom_excludes']) : [];

    if (!is_array($StvInputRaw)) {
        $StvInputRaw = [];
    }

    $StvInput = [
        'ips' => isset($StvInputRaw['ips']) ? sanitize_textarea_field((string) $StvInputRaw['ips']) : '',
        'user_agents' => isset($StvInputRaw['user_agents']) ? sanitize_textarea_field((string) $StvInputRaw['user_agents']) : '',
        'uris' => isset($StvInputRaw['uris']) ? sanitize_textarea_field((string) $StvInputRaw['uris']) : '',
    ];

    $StvSaved = lc_stv_save_custom_excludes($StvInput);

    wp_safe_redirect(lc_stv_get_custom_excludes_admin_url([
        'lc_stv_saved' => $StvSaved ? '1' : '0',
    ]));
    exit;
}

function lc_stv_render_custom_excludes_notice(): void {
    $StvSaved = isset($_GET['lc_stv_saved']) ? sanitize_key(wp_unslash((string) $_GET['lc_stv_saved'])) : '';

    if ($StvSaved === '') {
        return;
    }

    if ($StvSaved === '1') {
        echo '<div class="errormessage success"><p>Custom excludes saved.</p></div>';
        return;
    }

    echo '<div class="errormessage"><p>The custom excludes could not be saved.</p></div>';
}

function lc_stv_render_custom_excludes_section(): void {
    $StvValues = lc_stv_get_custom_excludes_form_values();
    ?>
    <section>
        <ul>
            <li class="shadow">
                <h2>Custom Excludes</h2>
                <br />
                <p>Use exact IP matches and simple contains matches for User-Agent and URI. One entry per line.</p>
            </li>
        </ul>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="lc_stv_save_custom_excludes" />
            <?php wp_nonce_field('lc_stv_save_custom_excludes'); ?>
            <ul>
                <li class="shadow">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="lc-stv-exclude-ips">Excluded IP addresses</label></th>
                            <td>
                                <textarea id="lc-stv-exclude-ips" name="lc_stv_custom_excludes[ips]" rows="6" cols="70" class="large-text code"><?php echo esc_textarea($StvValues['ips']); ?></textarea>
                                <p class="description">Exact match only. One IP per line.</p>
                            </td>
                        </tr>
                    </table>
                </li>
                <li class="shadow">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="lc-stv-exclude-user-agents">Excluded User-Agent fragments</label></th>
                            <td>
                                <textarea id="lc-stv-exclude-user-agents" name="lc_stv_custom_excludes[user_agents]" rows="6" cols="70" class="large-text code"><?php echo esc_textarea($StvValues['user_agents']); ?></textarea>
                                <p class="description">Case-insensitive contains match. One fragment per line.</p>
                            </td>
                        </tr>
                    </table>
                </li>
                <li class="shadow">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="lc-stv-exclude-uris">Excluded URI fragments</label></th>
                            <td>
                                <textarea id="lc-stv-exclude-uris" name="lc_stv_custom_excludes[uris]" rows="6" cols="70" class="large-text code"><?php echo esc_textarea($StvValues['uris']); ?></textarea>
                                <p class="description">Case-insensitive contains match. One fragment per line.</p>
                            </td>
                        </tr>
                    </table>
                </li>
            </ul>
            <br />
            <?php submit_button('Save custom excludes'); ?>
        </form>

    </section>
    <?php
}
