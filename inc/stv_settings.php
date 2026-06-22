<?php
defined('ABSPATH') || exit;

const LC_STV_CACHE_COMPAT_OPTION = 'lc_stv_cache_compatibility_mode';
const LC_STV_CACHE_COMPAT_BEGIN = '# BEGIN LiteCache STV Cache Compatibility';
const LC_STV_CACHE_COMPAT_END = '# END LiteCache STV Cache Compatibility';

function lc_stv_get_cache_compatibility_modes(): array {
    return [
        'disabled' => 'Disabled',
        'litespeed' => 'LiteSpeed / LSCache',
        'wp_rocket' => 'WP Rocket / Apache rewrite',
    ];
}

function lc_stv_get_cache_compatibility_mode(): string {
    $Mode = (string) get_option(LC_STV_CACHE_COMPAT_OPTION, 'disabled');

    return array_key_exists($Mode, lc_stv_get_cache_compatibility_modes()) ? $Mode : 'disabled';
}

function lc_stv_get_htaccess_path(): string {
    return ABSPATH . '.htaccess';
}

function lc_stv_get_cache_compatibility_common_rules(): string {
    return <<<'HTACCESS'
RewriteEngine On

# Only GET/HEAD main document requests are relevant for page cache visibility.
RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$ [NC]
RewriteCond %{REQUEST_URI} !^/index\.php$ [NC]
RewriteCond %{REQUEST_URI} !\.(css|js|mjs|map|jpg|jpeg|png|gif|webp|avif|svg|ico|woff|woff2|ttf|otf|eot|mp4|webm|mov|mp3|wav|pdf|zip|gz|br|xml|txt)$ [NC]
RewriteRule ^ - [E=LC_STV_CACHE_DOC:1]

# Known AI crawlers, known bots, headless clients, missing UA.
RewriteCond %{ENV:LC_STV_CACHE_DOC} ^1$
RewriteCond %{HTTP_USER_AGENT} ^$ [OR]
RewriteCond %{HTTP_USER_AGENT} (GPTBot|OAI-SearchBot|ChatGPT-User|OAI-AdsBot|ClaudeBot|Claude-User|Claude-SearchBot|PerplexityBot|Perplexity-User|CCBot|Google-CloudVertexBot|Googlebot|bingbot|Applebot|Qwantbot|meta-externalagent|PetalBot|AhrefsBot|SemrushBot|YandexBot|Bytespider|HeadlessChrome|Headless) [NC]
RewriteRule ^ - [E=LC_STV_CACHE_BYPASS:1]

# Request signature headers are suspicious outside normal browser traffic.
RewriteCond %{ENV:LC_STV_CACHE_DOC} ^1$
RewriteCond %{HTTP:Signature-Agent} .+ [OR]
RewriteCond %{HTTP:Signature-Input} .+ [OR]
RewriteCond %{HTTP:Signature} .+
RewriteRule ^ - [E=LC_STV_CACHE_BYPASS:1]

# Legacy or implausibly old browser versions.
RewriteCond %{ENV:LC_STV_CACHE_DOC} ^1$
RewriteCond %{HTTP_USER_AGENT} (MSIE[[:space:]]+|Trident/|Chrome/([1-9]|[1-9][0-9]|1[0-3][0-9])([\.;[:space:]]|$)|Chromium/([1-9]|[1-9][0-9]|1[0-3][0-9])([\.;[:space:]]|$)|CriOS/([1-9]|[1-9][0-9]|1[0-3][0-9])([\.;[:space:]]|$)|Edg/([1-9]|[1-9][0-9]|1[0-3][0-9])([\.;[:space:]]|$)|OPR/([1-9]|[1-9][0-9]|1[0-3][0-9])([\.;[:space:]]|$)|Firefox/([1-9]|[1-9][0-9]|1[0-3][0-9])([\.;[:space:]]|$)|FxiOS/([1-9]|[1-9][0-9]|1[0-3][0-9])([\.;[:space:]]|$)) [NC]
RewriteRule ^ - [E=LC_STV_CACHE_BYPASS:1]

# Real Safari <= 13. Do not match Chromium browsers that also contain Safari/.
RewriteCond %{ENV:LC_STV_CACHE_DOC} ^1$
RewriteCond %{HTTP_USER_AGENT} !(Chrome|Chromium|CriOS|Edg|EdgiOS|EdgA|OPR|OPT|Firefox|FxiOS)/ [NC]
RewriteCond %{HTTP_USER_AGENT} Version/([1-9]|1[0-3])([\.;[:space:]]|$).*Safari/ [NC]
RewriteRule ^ - [E=LC_STV_CACHE_BYPASS:1]

# Modern browser main-document requests normally send the Sec-Fetch triad.
# Safari is treated more carefully because Safari support is inconsistent.
RewriteCond %{ENV:LC_STV_CACHE_DOC} ^1$
RewriteCond %{HTTP_USER_AGENT} !(Version/[0-9]+.*Safari/) [NC]
RewriteCond %{HTTP:Sec-Fetch-Site} ^$ [OR]
RewriteCond %{HTTP:Sec-Fetch-Mode} ^$ [OR]
RewriteCond %{HTTP:Sec-Fetch-Dest} ^$
RewriteRule ^ - [E=LC_STV_CACHE_BYPASS:1]
HTACCESS;
}

function lc_stv_get_cache_compatibility_block(string $Mode): string {
    $CommonRules = lc_stv_get_cache_compatibility_common_rules();

    if ($Mode === 'litespeed') {
        return LC_STV_CACHE_COMPAT_BEGIN . "\n" .
                '<IfModule LiteSpeed>' . "\n" .
                $CommonRules . "\n\n" .
                '# Tell LiteSpeed not to serve/store this request from LSCache.' . "\n" .
                'RewriteCond %{ENV:LC_STV_CACHE_BYPASS} ^1$' . "\n" .
                'RewriteRule ^ - [E=Cache-Control:no-cache,E=no-lscache:1]' . "\n" .
                '</IfModule>' . "\n" .
                LC_STV_CACHE_COMPAT_END . "\n";
    }

    if ($Mode === 'wp_rocket') {
        return LC_STV_CACHE_COMPAT_BEGIN . "\n" .
                '<IfModule mod_rewrite.c>' . "\n" .
                $CommonRules . "\n\n" .
                '# WP Rocket serves static cache before WordPress/PHP.' . "\n" .
                '# Route matching requests to WordPress so STV auto_prepend can see them.' . "\n" .
                'RewriteCond %{REQUEST_URI} !^/index\.php$ [NC]' . "\n" .
                'RewriteCond %{ENV:LC_STV_CACHE_BYPASS} ^1$' . "\n" .
                'RewriteRule ^.*$ /index.php [L]' . "\n" .
                '</IfModule>' . "\n" .
                LC_STV_CACHE_COMPAT_END . "\n";
    }

    return '';
}

function lc_stv_remove_cache_compatibility_block_from_content(string $Content): string {
    $Pattern = '~\R?' . preg_quote(LC_STV_CACHE_COMPAT_BEGIN, '~') . '.*?' . preg_quote(LC_STV_CACHE_COMPAT_END, '~') . '\R?~s';
    $Content = (string) preg_replace($Pattern, "\n", $Content);

    return ltrim($Content);
}

function lc_stv_get_cache_compatibility_status(): array {
    $HtaccessPath = lc_stv_get_htaccess_path();
    $Exists = is_file($HtaccessPath);
    $Content = $Exists ? (string) file_get_contents($HtaccessPath) : '';

    return [
        'path' => $HtaccessPath,
        'exists' => $Exists,
        'writable' => $Exists ? is_writable($HtaccessPath) : is_writable(ABSPATH),
        'has_block' => strpos($Content, LC_STV_CACHE_COMPAT_BEGIN) !== false && strpos($Content, LC_STV_CACHE_COMPAT_END) !== false,
    ];
}

function lc_stv_write_cache_compatibility_block(string $Mode): array {
    $HtaccessPath = lc_stv_get_htaccess_path();
    $Block = lc_stv_get_cache_compatibility_block($Mode);

    if ($Block === '') {
        return lc_stv_remove_cache_compatibility_block();
    }

    $Content = '';

    if (is_file($HtaccessPath)) {
        $Content = file_get_contents($HtaccessPath);

        if ($Content === false) {
            return ['success' => false, 'message' => 'Could not read .htaccess file.'];
        }
    }

    $Content = lc_stv_remove_cache_compatibility_block_from_content($Content);
    $NewContent = $Block . "\n" . $Content;

    $Written = file_put_contents($HtaccessPath, $NewContent, LOCK_EX);

    if ($Written === false) {
        return ['success' => false, 'message' => 'Could not write .htaccess file.'];
    }

    update_option(LC_STV_CACHE_COMPAT_OPTION, $Mode, false);

    return ['success' => true, 'message' => 'Cache compatibility rules were enabled.'];
}

function lc_stv_remove_cache_compatibility_block(): array {
    $HtaccessPath = lc_stv_get_htaccess_path();

    if (!is_file($HtaccessPath)) {
        update_option(LC_STV_CACHE_COMPAT_OPTION, 'disabled', false);
        return ['success' => true, 'message' => 'Cache compatibility rules were disabled.'];
    }

    $Content = file_get_contents($HtaccessPath);

    if ($Content === false) {
        return ['success' => false, 'message' => 'Could not read .htaccess file.'];
    }

    $NewContent = lc_stv_remove_cache_compatibility_block_from_content($Content);

    if ($NewContent !== $Content && file_put_contents($HtaccessPath, $NewContent, LOCK_EX) === false) {
        return ['success' => false, 'message' => 'Could not update .htaccess file.'];
    }

    update_option(LC_STV_CACHE_COMPAT_OPTION, 'disabled', false);

    return ['success' => true, 'message' => 'Cache compatibility rules were disabled.'];
}

function lc_stv_handle_save_cache_compatibility(): void {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }

    check_admin_referer('lc_stv_save_cache_compatibility');

    $Mode = isset($_POST['lc_stv_cache_compatibility_mode']) ? sanitize_key(wp_unslash((string) $_POST['lc_stv_cache_compatibility_mode'])) : 'disabled';

    if (!array_key_exists($Mode, lc_stv_get_cache_compatibility_modes())) {
        $Mode = 'disabled';
    }

    if ($Mode !== 'disabled' && function_exists('lc_stv_is_enabled') && !lc_stv_is_enabled()) {
        $Result = lc_stv_remove_cache_compatibility_block();
        $Notice = !empty($Result['success']) ? 'capture_off' : 'error';
    } else {
        $Result = $Mode === 'disabled' ? lc_stv_remove_cache_compatibility_block() : lc_stv_write_cache_compatibility_block($Mode);
        $Notice = !empty($Result['success']) ? 'updated' : 'error';
    }

    wp_safe_redirect(add_query_arg('lc_stv_settings_notice', $Notice, admin_url('admin.php?page=litecache-stv-settings')));
    exit;
}

function lc_stv_render_cache_compatibility_notice(): void {
    $Notice = isset($_GET['lc_stv_settings_notice']) ? sanitize_key(wp_unslash((string) $_GET['lc_stv_settings_notice'])) : '';

    if ($Notice === 'updated') {
        echo '<ul class="errormessage success"><li>Settings saved.</li></ul>';
    }

    if ($Notice === 'capture_off') {
        echo '<ul class="errormessage"><li>Enable STV request capture before enabling page cache compatibility.</li></ul>';
        return;
    }

    if ($Notice === 'error') {
        echo '<ul class="errormessage"><li>Could not update .htaccess. Please check file permissions.</li></ul>';
    }
}

function lc_stv_render_cache_compatibility_section(): void {
    $Modes = lc_stv_get_cache_compatibility_modes();
    $CurrentMode = lc_stv_get_cache_compatibility_mode();
    $Status = lc_stv_get_cache_compatibility_status();
    $PreviewMode = $CurrentMode === 'disabled' ? 'litespeed' : $CurrentMode;
    $Preview = lc_stv_get_cache_compatibility_block($PreviewMode);
    ?>
    <section>
        <ul>
            <li class="shadow">
                <h2>Page Cache Compatibility</h2>
                <br />
                <p>Page cache can hide suspicious main-document requests from STV after a bot or crawler has warmed the cache. These optional rules keep selected suspicious request patterns visible without disabling page cache globally.</p>
                <br />
                <p><strong>Important:</strong> STV only manages its own marked .htaccess block. The block is intentionally placed at the top because WP Rocket places its own rules at the top too, and a late bypass rule would not help.</p>
                <br />
                <p><strong>Important:</strong> STV can only classify requests that reach PHP. If a page cache serves a cached page before PHP runs, STV cannot log that request.</p>
                <br />
                <p>LiteSpeed offers full no-cache support for STV cache-bypass rules. WP Rocket supports only limited `.htaccess`-based compatibility and cannot provide the same request-level no-cache control. Other cache plugins are not directly supported; their cache should be purged regularly or configured manually to avoid long-term STV blind spots.</p>

            </li>
        </ul>

        <?php if (empty($Status['writable'])) { ?>
            <ul class="errormessage"><li>.htaccess is not writable: <?php echo esc_html((string) $Status['path']); ?></li></ul>
        <?php } ?>
        <?php
        lc_stv_render_cache_compatibility_notice();
        ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('lc_stv_save_cache_compatibility'); ?>
            <input type="hidden" name="action" value="lc_stv_save_cache_compatibility" />

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="lc-stv-cache-compatibility-mode">Mode</label></th>
                    <td>
                        <select id="lc-stv-cache-compatibility-mode" name="lc_stv_cache_compatibility_mode">
                            <?php foreach ($Modes as $Mode => $Label) { ?>
                                <option value="<?php echo esc_attr($Mode); ?>" <?php selected($CurrentMode, $Mode); ?>><?php echo esc_html($Label); ?></option>
                            <?php } ?>
                        </select>
                        <p class="description">LiteSpeed can use cache-control environment flags. WP Rocket requires an early rewrite to WordPress/PHP so STV can see the request before Rocket serves a static cache file.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Status</th>
                    <td>
                        <p>
                            .htaccess block: <strong><?php echo!empty($Status['has_block']) ? 'installed' : 'not installed'; ?></strong><br />
                            .htaccess writable: <strong><?php echo!empty($Status['writable']) ? 'yes' : 'no'; ?></strong>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Rule preview</th>
                    <td>
                        <textarea readonly rows="18" style="width:100%;font-family:monospace;background:#fff"><?php echo esc_textarea($Preview); ?></textarea>
                    </td>
                </tr>
            </table>
            <br />
            <?php submit_button('Save cache compatibility settings'); ?>
        </form>
    </section>
    <?php
}
