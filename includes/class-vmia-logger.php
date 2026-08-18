<?php
/**
 * Lightweight activity logger.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Logger {

	/**
	 * @param string $kind      generate|fix|scan|resize|error|ai
	 * @param int    $object_id
	 * @param string $provider
	 * @param string $message
	 * @param array  $meta
	 */
	public static function add( $kind, $object_id, $provider, $message, $meta = array() ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'vmia_log',
			array(
				'kind'       => sanitize_key( $kind ),
				'object_id'  => (int) $object_id,
				'provider'   => sanitize_key( $provider ),
				'message'    => wp_kses_post( $message ),
				'meta'       => $meta ? wp_json_encode( $meta ) : null,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * @param int $limit
	 * @return array
	 */
	public static function recent( $limit = 30 ) {
		global $wpdb;
		$limit = max( 1, min( 200, (int) $limit ) );
		$table = $wpdb->prefix . 'vmia_log';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore
	}
}
