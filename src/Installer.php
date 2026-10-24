<?php
/**
 * Schema installation, upgrade drift detection, and garbage collection.
 */

declare(strict_types=1);

namespace SiteEngine\CallBridge;

use SiteEngine\CallBridge\DB\AttributionSessionRepository;
use SiteEngine\CallBridge\DB\NumberLeaseRepository;

defined('ABSPATH') || exit;

final class Installer
{
    public const CRON_GC = 'se_cb_gc';
    private const DB_OPTION = 'se_cb_db_version';

    public static function activate(): void
    {
        self::createTables();
        self::scheduleGc();
        update_option(self::DB_OPTION, SECB_DB_VERSION, false);
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(self::CRON_GC);
    }

    public static function maybeUpgrade(): void
    {
        if ((string) get_option(self::DB_OPTION, '') === SECB_DB_VERSION) {
            self::scheduleGc();
            return;
        }
        self::createTables();
        self::scheduleGc();
        update_option(self::DB_OPTION, SECB_DB_VERSION, false);
    }

    public static function runGc(): void
    {
        try {
            (new NumberLeaseRepository())->expireStale();
            (new AttributionSessionRepository())->purgeExpired();
        } catch (\Throwable $e) {
            error_log('[siteengine-call-bridge] GC failed: ' . $e->getMessage());
        }
    }

    private static function scheduleGc(): void
    {
        if (!wp_next_scheduled(self::CRON_GC)) {
            wp_schedule_event(time() + 300, 'hourly', self::CRON_GC);
        }
    }

    private static function createTables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $numbers = $wpdb->prefix . 'se_cb_tracking_numbers';
        $sessions = $wpdb->prefix . 'se_cb_attribution_sessions';
        $leases = $wpdb->prefix . 'se_cb_number_leases';
        $calls = $wpdb->prefix . 'se_cb_calls';

        dbDelta("CREATE TABLE {$numbers} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            twilio_phone_sid VARCHAR(40) NOT NULL DEFAULT '',
            phone_number VARCHAR(20) NOT NULL,
            display_label VARCHAR(100) NOT NULL DEFAULT '',
            source VARCHAR(50) NOT NULL DEFAULT 'google_ads',
            pool_group VARCHAR(50) DEFAULT NULL,
            forward_to VARCHAR(20) NOT NULL,
            min_duration_sec INT UNSIGNED NOT NULL DEFAULT 60,
            record_calls TINYINT(1) NOT NULL DEFAULT 0,
            recording_notice VARCHAR(255) DEFAULT NULL,
            whisper_new VARCHAR(255) DEFAULT NULL,
            whisper_repeat VARCHAR(255) DEFAULT NULL,
            repeat_window_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            notes TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_phone_number (phone_number),
            KEY idx_source_pool (source, pool_group),
            KEY idx_active (is_active)
        ) {$charset};");

        dbDelta("CREATE TABLE {$sessions} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(64) NOT NULL,
            visitor_uid VARCHAR(64) DEFAULT NULL,
            gclid VARCHAR(255) DEFAULT NULL,
            gbraid VARCHAR(255) DEFAULT NULL,
            wbraid VARCHAR(255) DEFAULT NULL,
            fbclid VARCHAR(255) DEFAULT NULL,
            msclkid VARCHAR(255) DEFAULT NULL,
            utm_source VARCHAR(100) DEFAULT NULL,
            utm_medium VARCHAR(100) DEFAULT NULL,
            utm_campaign VARCHAR(255) DEFAULT NULL,
            utm_term VARCHAR(255) DEFAULT NULL,
            utm_content VARCHAR(255) DEFAULT NULL,
            landing_page TEXT,
            referrer TEXT,
            user_agent TEXT,
            client_ip VARCHAR(45) DEFAULT NULL,
            consent_receipt_id VARCHAR(64) DEFAULT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_session_id (session_id),
            KEY idx_expires_at (expires_at),
            KEY idx_gclid (gclid(191))
        ) {$charset};");

        dbDelta("CREATE TABLE {$leases} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(64) NOT NULL,
            tracking_number_id BIGINT UNSIGNED NOT NULL,
            tracking_number VARCHAR(20) NOT NULL,
            source VARCHAR(50) NOT NULL,
            leased_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            released_at DATETIME DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_session_active (session_id, status, expires_at),
            KEY idx_number_active (tracking_number_id, status, expires_at),
            KEY idx_tracking_phone (tracking_number, status, expires_at),
            KEY idx_expires_at (expires_at)
        ) {$charset};");

        dbDelta("CREATE TABLE {$calls} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            twilio_call_sid VARCHAR(40) NOT NULL,
            parent_call_sid VARCHAR(40) DEFAULT NULL,
            transaction_id VARCHAR(64) NOT NULL,
            tracking_number VARCHAR(20) NOT NULL,
            tracking_number_id BIGINT UNSIGNED DEFAULT NULL,
            caller_number VARCHAR(20) NOT NULL,
            hashed_caller_number CHAR(64) DEFAULT NULL,
            caller_city VARCHAR(100) DEFAULT NULL,
            caller_state VARCHAR(20) DEFAULT NULL,
            caller_country VARCHAR(10) DEFAULT NULL,
            forwarded_to VARCHAR(20) DEFAULT NULL,
            source VARCHAR(50) DEFAULT NULL,
            call_status VARCHAR(20) DEFAULT NULL,
            call_duration_sec INT UNSIGNED DEFAULT NULL,
            qualification_status VARCHAR(32) NOT NULL DEFAULT 'pending',
            recording_sid VARCHAR(40) DEFAULT NULL,
            recording_url TEXT,
            recording_duration_sec INT UNSIGNED DEFAULT NULL,
            matched_session_id VARCHAR(64) DEFAULT NULL,
            consent_receipt_id VARCHAR(64) DEFAULT NULL,
            gclid VARCHAR(255) DEFAULT NULL,
            gbraid VARCHAR(255) DEFAULT NULL,
            wbraid VARCHAR(255) DEFAULT NULL,
            fbclid VARCHAR(255) DEFAULT NULL,
            msclkid VARCHAR(255) DEFAULT NULL,
            utm_source VARCHAR(100) DEFAULT NULL,
            utm_medium VARCHAR(100) DEFAULT NULL,
            utm_campaign VARCHAR(255) DEFAULT NULL,
            utm_term VARCHAR(255) DEFAULT NULL,
            utm_content VARCHAR(255) DEFAULT NULL,
            landing_page TEXT,
            is_repeat_caller TINYINT(1) NOT NULL DEFAULT 0,
            prior_qualifying_calls SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            notes TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_call_sid (twilio_call_sid),
            UNIQUE KEY uk_transaction_id (transaction_id),
            KEY idx_tracking_number (tracking_number),
            KEY idx_caller_recent (caller_number, created_at),
            KEY idx_qualification (qualification_status),
            KEY idx_created_at (created_at)
        ) {$charset};");
    }
}
