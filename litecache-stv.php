<?php
/*
 * Plugin Name:     LiteCache Suspicious Traffic Viewer
 * Description:     View incoming requests and identify suspicious traffic patterns before they become a blind spot. Reveals suspicious, masked, and non-human request patterns that ordinary traffic views often miss.
 * Version:         1.0.0
 * Requires at least:   6.1
 * Requires PHP:    8.1
 * Author:          LiteCache
 * Author URI:      https://www.litecache.dev
 * License:         GPLv3
 * License URI:     https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:     litecache-stv
 * Domain Path:     /languages/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 */

defined('ABSPATH') || exit;

define('LC_STV_VERSION', '1.0.0');
define('LC_STV_FILE', __FILE__);
define('LC_STV_DIR', plugin_dir_path(__FILE__));
define('LC_STV_URL', plugin_dir_url(__FILE__));

define('LC_STV_STORAGE_DIR', WP_CONTENT_DIR . '/cache/litecache-stv/');
define('LC_STV_LOG_FILE', LC_STV_STORAGE_DIR . 'requests.log');
define('LC_STV_DAILY_IMPORT_HOOK', 'lc_stv_daily_import_event');

$StvPage = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';

$StvAction = '';
if (isset($_POST['action'])) {
    $StvAction = sanitize_key(wp_unslash((string) $_POST['action']));
} elseif (isset($_GET['action'])) {
    $StvAction = sanitize_key(wp_unslash((string) $_GET['action']));
}
$StvPagenow = (string) ($GLOBALS['pagenow'] ?? '');
$StvIsStartPage = is_admin() && $StvPage === 'litecache-stv';
$StvIsExcludesPage = is_admin() && $StvPage === 'litecache-stv-excludes';
$StvIsStvPage = $StvIsStartPage || $StvIsExcludesPage;
$StvIsPluginsPage = is_admin() && $StvPagenow === 'plugins.php';
$StvIsAdminPost = is_admin() && $StvPagenow === 'admin-post.php';
$StvIsCronRequest = (defined('DOING_CRON') && DOING_CRON) || (function_exists('wp_doing_cron') && wp_doing_cron());
$StvAdminPostActions = [
    'lc_stv_save_custom_excludes',
    'lc_stv_toggle_capture',
    'lc_stv_set_per_page',
    'lc_stv_import_log',
    'lc_stv_toggle_cron',
];
$StvIsRelevantAdminPost = $StvIsAdminPost && in_array($StvAction, $StvAdminPostActions, true);
$StvNeedsFunctions = $StvIsStvPage || $StvIsPluginsPage || $StvIsRelevantAdminPost || $StvIsCronRequest;
$StvNeedsCustomExcludes = $StvIsExcludesPage || $StvAction === 'lc_stv_save_custom_excludes';
$StvNeedsStartPageChecks = $StvIsStartPage;
$StvNeedsLog = $StvIsStartPage || $StvIsRelevantAdminPost || $StvIsCronRequest;
$StvNeedsMobileDetect = $StvIsStvPage;

require_once LC_STV_DIR . 'inc/stv_constants.php';
require_once LC_STV_DIR . 'inc/stv_db.php';
require_once LC_STV_DIR . 'inc/stv_functions.php';
require_once LC_STV_DIR . 'inc/stv_activation.php';

if (is_admin()) {
    require_once LC_STV_DIR . 'inc/stv_menu.php';
}

if ($StvNeedsFunctions) {
    require_once LC_STV_DIR . 'inc/stv_control.php';
    require_once LC_STV_DIR . 'inc/stv_environment.php';
    require_once LC_STV_DIR . 'inc/stv_prepend_tools.php';
}

if ($StvNeedsCustomExcludes) {
    require_once LC_STV_DIR . 'inc/stv_custom_excludes.php';
}

if ($StvNeedsLog) {
    require_once LC_STV_DIR . 'inc/stv_log.php';
}

if ($StvNeedsStartPageChecks) {
    require_once LC_STV_DIR . 'inc/stv_requirements.php';
    add_action('admin_notices', 'lc_stv_render_prepend_status_notice');
}

if ($StvIsStvPage) {
    require_once LC_STV_DIR . 'inc/stv_static.php';
}

if ($StvNeedsMobileDetect) {
    require_once (LC_STV_DIR . 'classes/class-lc-stv-mobile-detect.php');
    $StvDetect = new LC_STV_Mobile_Detect();
}

add_action('admin_post_lc_stv_save_custom_excludes', 'lc_stv_handle_save_custom_excludes');
add_action('admin_post_lc_stv_toggle_capture', 'lc_stv_handle_toggle_capture');
add_action('admin_post_lc_stv_set_per_page', 'lc_stv_handle_set_per_page');
add_action('admin_post_lc_stv_import_log', 'lc_stv_handle_import_log');
add_action('admin_post_lc_stv_toggle_cron', 'lc_stv_handle_toggle_cron');
add_action('wp_ajax_lc_stv_agent_chart', 'lc_stv_ajax_get_agent_chart');
add_action('admin_enqueue_scripts', 'lc_stv_enqueue_chart_config');
add_action(LC_STV_DAILY_IMPORT_HOOK, 'lc_stv_run_scheduled_import');

register_activation_hook(__FILE__, 'lc_stv_on_activation');
register_activation_hook(__FILE__, 'lc_stv_on_activation_prepare_rewrite_probe');
register_deactivation_hook(__FILE__, 'lc_stv_on_deactivation');

if ($StvNeedsMobileDetect && $StvDetect->StvIsMobile() && !$StvDetect->StvIsTablet()) {

    function litecache_stv_display_page(): void {
        echo '<div class="wrap stv">';
        echo '<header>';
        echo '<a class="logo">';
        echo '<span>LiteCache Suspicious Traffic Viewer</span><span>Nothing but Speed</span>';
        echo '</a>';
        echo '</header>';
        echo '<div class="main_content">';
        echo '<h1>LiteCache Suspicious Traffic Viewer an only be accessed by desktop computer or tablet</h1>';
        echo '</div>';
        echo '</div>';
    }

    function litecache_stv_excludes_page(): void {

    }

} else {

    function litecache_stv_display_page(): void {
        $StvAdminChecks = function_exists('lc_stv_get_admin_checks') ? lc_stv_get_admin_checks(lc_stv_should_refresh_admin_checks()) : [
            'errors' => lc_stv_get_requirement_errors(),
            'warnings' => function_exists('lc_stv_get_requirement_warnings') ? lc_stv_get_requirement_warnings() : [],
        ];

        $StvErrors = is_array($StvAdminChecks['errors'] ?? null) ? $StvAdminChecks['errors'] : [];
        $StvWarnings = is_array($StvAdminChecks['warnings'] ?? null) ? $StvAdminChecks['warnings'] : [];
        $StvRecordsPerPage = lc_stv_get_records_per_page();
        $StvOrderBy = lc_stv_get_current_orderby();
        $StvOrder = lc_stv_get_current_order();
        $StvGetSortHeadClass = static function (string $StvColumn) use ($StvOrderBy, $StvOrder): string {
            $StvClasses = [''];

            if ($StvOrderBy === $StvColumn) {
                $StvClasses[] = 'is-sorted';
                $StvClasses[] = $StvOrder === 'asc' ? 'is-asc' : 'is-desc';
            }

            return implode(' ', $StvClasses);
        };
        $StvFilters = lc_stv_get_current_request_filters();
        $StvTotalEntries = lc_stv_count_request_entries($StvFilters);
        $StvTotalPages = max(1, (int) ceil($StvTotalEntries / $StvRecordsPerPage));
        $StvCurrentPage = min(lc_stv_get_current_page_number(), $StvTotalPages);
        $StvOffset = ($StvCurrentPage - 1) * $StvRecordsPerPage;
        $StvEntries = lc_stv_get_request_entries_slice($StvOffset, $StvRecordsPerPage, $StvOrderBy, $StvOrder, $StvFilters);
        ?>
        <div class="wrap stv">
            <h1 style="display:none"></h1>
            <?php
            litecache_stv_header_display();
            if (empty($StvErrors)) {
                ?>
                <section>
                    <ul>
                        <li class="shadow">
                            <h2>LiteCache Suspicious Traffic Viewer (STV) makes suspicious traffic visible.</h2>
                            <br />
                            LiteCache Suspicious Traffic Viewer (STV) helps make suspicious traffic visible, especially requests that look normal or remain unnoticed in ordinary logs.
                            It is not a realtime logger and it cannot see requests fully served by page cache or CDN cache. STV is db-less while logging, and captured log data only becomes visible after the next
                            nightly cron import.
                        </li>
                    </ul>
                    <?php
                    lc_stv_render_import_notice();
                    lc_stv_render_toggle_notice();
                    lc_stv_render_cron_notice();
                    if (!empty($StvErrors)) {
                        echo '<ul class="errormessage">';
                        foreach ($StvErrors as $StvError) {
                            echo '<li >' . esc_html($StvError) . '</li>';
                        }
                        echo '</ul>';
                    }

                    if (!empty($StvWarnings)) {
                        echo '<ul class="errormessage">';
                        foreach ($StvWarnings as $StvWarning) {
                            echo '<li>Warning: ' . esc_html($StvWarning) . '</li>';
                        }
                        echo '</ul>';
                    }
                    if (!lc_stv_is_enabled()) {
                        ?>
                        <ul class="errormessage">
                            <li>STV is currently disabled. No new requests are being captured.</li>
                        </ul>
                    <?php } ?>
                    <div class="actions">
                        <?php lc_stv_render_request_search_form($StvFilters); ?>
                        <?php lc_stv_render_request_filters_form($StvFilters); ?>
                        <?php lc_stv_render_toggle_form(); ?>
                    </div>
                    <br />
                    <?php
                    if (litecache_stv_itsme()) {
                        echo '<div class="flex itsme">';
                        lc_stv_render_import_form();
                        lc_stv_render_cron_form();
                        echo '</div>';
                    }
                    ?>
                    <?php if (empty($StvEntries)) { ?>
                        <ul class="errormessage"><li>No request data available yet.</li></ul>
                    <?php } else { ?>
                        <?php lc_stv_render_log_pagination($StvCurrentPage, $StvTotalPages, $StvTotalEntries); ?>
                        <div class="logtbl">
                            <div class="head">
                                <div class="<?php echo esc_attr($StvGetSortHeadClass('time')); ?>"><?php echo wp_kses_post(lc_stv_render_sort_header('time', 'Time')); ?></div>
                                <div class="<?php echo esc_attr($StvGetSortHeadClass('method')); ?>"><?php echo wp_kses_post(lc_stv_render_sort_header('method', 'Method')); ?></div>
                                <div class="<?php echo esc_attr($StvGetSortHeadClass('uri')); ?>"><?php echo wp_kses_post(lc_stv_render_sort_header('uri', 'URI')); ?></div>
                                <div class="<?php echo esc_attr($StvGetSortHeadClass('status')); ?>"><?php echo wp_kses_post(lc_stv_render_sort_header('status', 'Status')); ?></div>
                                <div class="<?php echo esc_attr($StvGetSortHeadClass('hits')); ?>"><?php echo wp_kses_post(lc_stv_render_sort_header('hits', 'Hits')); ?></div>
                                <div class="<?php echo esc_attr($StvGetSortHeadClass('traffic_class')); ?>"><?php echo wp_kses_post(lc_stv_render_sort_header('traffic_class', 'Class')); ?></div>
                                <div class="<?php echo esc_attr($StvGetSortHeadClass('ip')); ?>"><?php echo wp_kses_post(lc_stv_render_sort_header('ip', 'IP')); ?></div>
                                <div class="<?php echo esc_attr($StvGetSortHeadClass('user_agent')); ?>"><?php echo wp_kses_post(lc_stv_render_sort_header('user_agent', 'User-Agent')); ?></div>
                                <div>Graph</div>
                            </div>
                            <?php foreach ($StvEntries as $StvEntry) { ?>
                                <div class="tr">
                                    <div><?php echo esc_html(lc_stv_format_log_time((string) ($StvEntry['time'] ?? ''))); ?></div>
                                    <div><?php echo esc_html($StvEntry['method'] ?? ''); ?></div>
                                    <div><?php echo esc_html($StvEntry['uri'] ?? ''); ?></div>
                                    <div><?php echo esc_html((string) ($StvEntry['status'] ?? '')); ?></div>
                                    <div><?php echo esc_html((string) ($StvEntry['hits'] ?? '0')); ?></div>
                                    <div><?php echo wp_kses_post(lc_stv_render_traffic_class($StvEntry)); ?></div>
                                    <div><?php echo esc_html($StvEntry['ip'] ?? ''); ?></div>
                                    <div><?php echo esc_html(trim((string) ($StvEntry['user_agent'] ?? '')) === '' ? '[empty]' : (string) $StvEntry['user_agent']); ?></div>
                                    <div>
                                        <button
                                            type="button"
                                            class="lc-stv-chart-button"
                                            data-ua-hash="<?php echo esc_attr((string) ($StvEntry['ua_hash'] ?? '')); ?>"
                                            data-ua="<?php echo esc_attr(trim((string) ($StvEntry['user_agent'] ?? '')) === '' ? '[empty]' : (string) $StvEntry['user_agent']); ?>"
                                            title="Show graph"
                                            aria-label="Show graph"
                                            >
                                            📈
                                        </button>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                        <br />
                        <?php lc_stv_render_log_pagination($StvCurrentPage, $StvTotalPages, $StvTotalEntries); ?>

                        <div id="lc-stv-chart-modal" style="display:none;">
                            <div class="lc-stv-chart-backdrop"></div>

                            <div class="lc-stv-chart-dialog">
                                <button type="button" class="lc-stv-chart-close" aria-label="Close dialog">×</button>
                                <h1 id="lc-stv-chart-title"></h1>
                                <div id="lc-stv-chart"></div>
                            </div>
                        </div>
                    <?php } ?>
                </section>
            <?php } ?>
            <div id="landscape">
                <img src="data:image/png;base64,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"  alt="" />
            </div>
        </div>
        <?php
    }

    function litecache_stv_excludes_page(): void {
        ?>
        <div class="wrap stv">
            <h1 style="display:none"></h1>
            <?php
            litecache_stv_header_display();
            if (function_exists('lc_stv_render_custom_excludes_notice')) {
                lc_stv_render_custom_excludes_notice();
            }
            if (function_exists('lc_stv_render_custom_excludes_section')) {
                lc_stv_render_custom_excludes_section();
            }
            ?>
            <div id="landscape">
                <img src="data:image/png;base64,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"  alt="" />
            </div>
        </div>
        <?php
    }

}