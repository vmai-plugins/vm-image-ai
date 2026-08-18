<?php
/**
 * Uninstall cleanup.
 *
 * @package VM_Image_AI
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop plugin tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}vmia_audit" ); // phpcs:ignore
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}vmia_log" );   // phpcs:ignore

// Remove options + transients.
delete_option( 'vmia_settings' );
delete_option( 'vmia_db_version' );
delete_option( 'vmia_last_scan' );
delete_transient( 'vmia_model_catalogue' );

// Clear scheduled events.
wp_clear_scheduled_hook( 'vmia_cron_scan' );
wp_clear_scheduled_hook( 'vmia_cron_model_sync' );
