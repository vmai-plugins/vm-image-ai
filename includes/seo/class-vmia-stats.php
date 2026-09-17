<?php
/**
 * Metrics and trend tracking.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Stats {

	/**
	 * Record a metric snapshot.
	 */
	public static function record( $metric, $value, $meta = array() ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'vmia_stats',
			array(
				'metric'     => $metric,
				'value'      => (float) $value,
				'meta'       => wp_json_encode( $meta ),
				'created_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Get historical data for a metric.
	 */
	public static function get_history( $metric, $days = 30 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT value, created_at FROM {$wpdb->prefix}vmia_stats WHERE metric=%s AND created_at > %s ORDER BY created_at ASC",
				$metric,
				gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) )
			),
			ARRAY_A
		);
	}

	/**
	 * Calculate total storage saved by WebP conversion.
	 */
	public static function get_storage_saved() {
		global $wpdb;
		return (float) $wpdb->get_var( "SELECT SUM(value) FROM {$wpdb->prefix}vmia_stats WHERE metric='storage_saved'" );
	}
}
