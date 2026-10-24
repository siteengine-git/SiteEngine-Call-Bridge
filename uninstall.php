<?php
/**
 * Call Bridge preserves call history by default.
 * Define SECB_ALLOW_UNINSTALL_DROP=true before uninstall to remove its tables.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

if (!defined('SECB_ALLOW_UNINSTALL_DROP') || SECB_ALLOW_UNINSTALL_DROP !== true) {
    return;
}

global $wpdb;
foreach ([
    'se_cb_calls',
    'se_cb_number_leases',
    'se_cb_attribution_sessions',
    'se_cb_tracking_numbers',
] as $suffix) {
    $table = $wpdb->prefix . $suffix;
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

delete_option('se_cb_db_version');
wp_clear_scheduled_hook('se_cb_gc');
