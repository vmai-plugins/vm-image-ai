<?php
/**
 * Activation / deactivation: DB schema, defaults, cron.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Activator {

	const CRON_SCAN      = 'vmia_cron_scan';
	const CRON_SYNC      = 'vmia_cron_model_sync';
	const CRON_AUTOPILOT = 'vmia_cron_autopilot';

	public static function activate() {
		self::create_tables();

		// Seed defaults on first activation only.
		if ( ! get_option( VMIA_OPTION ) ) {
			update_option( VMIA_OPTION, VMIA_Settings::defaults(), false );
		}
		update_option( 'vmia_db_version', VMIA_DB_VERSION, false );

		self::schedule();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_SCAN );
		wp_clear_scheduled_hook( self::CRON_SYNC );
		wp_clear_scheduled_hook( self::CRON_AUTOPILOT );
	}

	/**
	 * Register cron events (idempotent).
	 */
	public static function schedule() {
		$freq = VMIA_Settings::get( 'scan_frequency', 'daily' );
		if ( VMIA_Settings::get( 'auto_scan' ) ) {
			$timestamp = wp_next_scheduled( self::CRON_SCAN );
			$event     = wp_get_scheduled_event( self::CRON_SCAN );
			if ( ! $timestamp ) {
				wp_schedule_event( time() + 300, $freq, self::CRON_SCAN );
			} elseif ( $event && $event->schedule !== $freq ) {
				wp_clear_scheduled_hook( self::CRON_SCAN );
				wp_schedule_event( time() + 300, $freq, self::CRON_SCAN );
			}
		} else {
			wp_clear_scheduled_hook( self::CRON_SCAN );
		}
		if ( ! wp_next_scheduled( self::CRON_SYNC ) ) {
			wp_schedule_event( time() + 120, 'twicedaily', self::CRON_SYNC );
		}
		if ( VMIA_Settings::get( 'autopilot_enabled' ) && ! wp_next_scheduled( self::CRON_AUTOPILOT ) ) {
			wp_schedule_event( time() + 600, 'hourly', self::CRON_AUTOPILOT );
		} elseif ( ! VMIA_Settings::get( 'autopilot_enabled' ) ) {
			wp_clear_scheduled_hook( self::CRON_AUTOPILOT );
		}
	}

	/**
	 * dbDelta schema.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$audit = $wpdb->prefix . 'vmia_audit';
		$log   = $wpdb->prefix . 'vmia_log';
		$stats = $wpdb->prefix . 'vmia_stats';
		$undo  = $wpdb->prefix . 'vmia_undo';

		$sql_audit = "CREATE TABLE {$audit} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			object_type VARCHAR(20) NOT NULL DEFAULT 'attachment',
			object_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			issue_code VARCHAR(50) NOT NULL DEFAULT '',
			severity TINYINT(1) NOT NULL DEFAULT 1,
			detail TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			resolved_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY object_id (object_id),
			KEY issue_code (issue_code),
			KEY status (status),
			KEY severity (severity)
		) {$charset};";

		$sql_log = "CREATE TABLE {$log} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			kind VARCHAR(30) NOT NULL DEFAULT '',
			object_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			provider VARCHAR(30) NOT NULL DEFAULT '',
			message TEXT NULL,
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY kind (kind),
			KEY object_id (object_id)
		) {$charset};";

		$sql_stats = "CREATE TABLE {$stats} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			metric VARCHAR(50) NOT NULL,
			value FLOAT NOT NULL DEFAULT 0,
			meta TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY metric (metric),
			KEY created_at (created_at)
		) {$charset};";

		$sql_undo = "CREATE TABLE {$undo} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			object_id BIGINT(20) UNSIGNED NOT NULL,
			field VARCHAR(50) NOT NULL,
			old_value TEXT NULL,
			new_value TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY object_id (object_id)
		) {$charset};";

		dbDelta( $sql_audit );
		dbDelta( $sql_log );
		dbDelta( $sql_stats );
		dbDelta( $sql_undo );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'vmia_db_version' ) !== VMIA_DB_VERSION ) {
			self::create_tables();
			update_option( 'vmia_db_version', VMIA_DB_VERSION, false );
		}
	}
}
