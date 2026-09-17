<?php
/**
 * Undo history management.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Undo {

	/**
	 * Record a field change.
	 */
	public static function record( $object_id, $field, $old, $new ) {
		global $wpdb;
		if ( $old === $new ) return;

		$wpdb->insert(
			$wpdb->prefix . 'vmia_undo',
			array(
				'object_id'  => (int) $object_id,
				'field'      => $field,
				'old_value'  => is_scalar( $old ) ? (string) $old : wp_json_encode( $old ),
				'new_value'  => is_scalar( $new ) ? (string) $new : wp_json_encode( $new ),
				'created_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Revert the last change for an object.
	 */
	public static function revert_last( $object_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}vmia_undo WHERE object_id=%d ORDER BY id DESC LIMIT 1",
				(int) $object_id
			),
			ARRAY_A
		);

		if ( ! $row ) return false;

		$field = $row['field'];
		$old   = $row['old_value'];

		if ( '_wp_attachment_image_alt' === $field ) {
			update_post_meta( $object_id, '_wp_attachment_image_alt', $old );
		} elseif ( in_array( $field, array( 'post_title', 'post_excerpt', 'post_content' ), true ) ) {
			wp_update_post( array( 'ID' => $object_id, $field => $old ) );
		}

		$wpdb->delete( $wpdb->prefix . 'vmia_undo', array( 'id' => $row['id'] ) );
		return true;
	}
}
