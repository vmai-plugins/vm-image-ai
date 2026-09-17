<?php
/**
 * Resize / compress / WebP-convert attachments and regenerate metadata.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Resize {

	/**
	 * Optimise an attachment in place: clamp dimensions, compress, optional WebP,
	 * then regenerate WordPress thumbnail metadata.
	 *
	 * @param int      $attach_id
	 * @param int|null $target_w Optional hard target width (e.g. featured 1200).
	 * @param int|null $target_h Optional hard target height.
	 * @return array { ok, before?, after?, converted?, error? }
	 */
	public function optimize_attachment( $attach_id, $target_w = null, $target_h = null ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$path = get_attached_file( $attach_id );
		if ( ! $path || ! file_exists( $path ) ) {
			return array( 'ok' => false, 'error' => 'file missing' );
		}

		$before = filesize( $path );
		$max_w  = $target_w ?: (int) VMIA_Settings::get( 'blog_max_w', 1600 );
		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return array( 'ok' => false, 'error' => $editor->get_error_message() );
		}

		$size = $editor->get_size();
		$cur_w = $size['width'] ?? 0;
		$cur_h = $size['height'] ?? 0;

		// Featured mode: crop to exact ratio. Blog mode: clamp width, keep ratio.
		if ( $target_w && $target_h ) {
			$editor->resize( $target_w, $target_h, true );
		} elseif ( $cur_w > $max_w ) {
			$editor->resize( $max_w, null, false );
		}

		// Compression quality.
		$quality = VMIA_Settings::get( 'convert_webp' ) ? (int) VMIA_Settings::get( 'webp_quality', 82 ) : (int) VMIA_Settings::get( 'jpeg_quality', 82 );
		$editor->set_quality( $quality );

		$converted = false;
		if ( VMIA_Settings::get( 'convert_webp' ) && $this->supports_webp() ) {
			$new_path = preg_replace( '/\.\w+$/', '.webp', $path );
			$saved    = $editor->save( $new_path, 'image/webp' );
			if ( ! is_wp_error( $saved ) && ! empty( $saved['path'] ) ) {
				$old_url = wp_get_attachment_url( $attach_id );

				// Point the attachment at the new WebP file; drop the old original.
				if ( $saved['path'] !== $path && file_exists( $path ) ) {
					@unlink( $path ); // phpcs:ignore
				}
				update_attached_file( $attach_id, $saved['path'] );
				wp_update_post( array( 'ID' => $attach_id, 'post_mime_type' => 'image/webp' ) );
				$path      = $saved['path'];
				$converted = true;

				// Update any post_content referencing the old URL to avoid broken 404 image links.
				$new_url = wp_get_attachment_url( $attach_id );
				if ( $old_url && $new_url && $old_url !== $new_url ) {
					global $wpdb;
					$wpdb->query(
						$wpdb->prepare(
							"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
							$old_url,
							$new_url,
							'%' . $wpdb->esc_like( $old_url ) . '%'
						)
					);
				}
			}
		}

		if ( ! $converted ) {
			$saved = $editor->save( $path );
			if ( is_wp_error( $saved ) ) {
				return array( 'ok' => false, 'error' => $saved->get_error_message() );
			}
			$path = $saved['path'] ?? $path;
			if ( isset( $saved['path'] ) && $saved['path'] !== get_attached_file( $attach_id ) ) {
				update_attached_file( $attach_id, $saved['path'] );
			}
		}

		// Regenerate the full metadata + thumbnail set.
		$meta = wp_generate_attachment_metadata( $attach_id, $path );
		if ( ! is_wp_error( $meta ) && $meta ) {
			wp_update_attachment_metadata( $attach_id, $meta );
		}

		clearstatcache();
		$after = file_exists( $path ) ? filesize( $path ) : $before;

		// Record storage saved in stats.
		if ( $before > $after ) {
			VMIA_Stats::record( 'storage_saved', ( $before - $after ) / 1024 );
		}

		VMIA_Logger::add(
			'resize',
			$attach_id,
			'local',
			sprintf( 'Optimised %s -> %s%s', size_format( $before ), size_format( $after ), $converted ? ' (WebP)' : '' )
		);

		return array( 'ok' => true, 'before' => $before, 'after' => $after, 'converted' => $converted );
	}

	/**
	 * @return bool
	 */
	protected function supports_webp() {
		if ( function_exists( 'imagewebp' ) ) {
			return true;
		}
		if ( class_exists( 'Imagick' ) ) {
			$formats = @\Imagick::queryFormats( 'WEBP' );
			return ! empty( $formats );
		}
		return false;
	}
}
