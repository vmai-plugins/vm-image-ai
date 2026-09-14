<?php
/**
 * Media library helpers: import remote/temp images as attachments.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Media {

	protected function bootstrap() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	/**
	 * Import a remote image URL as an attachment.
	 *
	 * @param string $url
	 * @param int    $post_id
	 * @param string $desc     Used to build a descriptive filename + title.
	 * @return int|WP_Error attachment id
	 */
	public function sideload_url( $url, $post_id = 0, $desc = '' ) {
		$this->bootstrap();

		$tmp = download_url( $url, 120 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		return $this->handle_tmp( $tmp, $post_id, $desc, $url );
	}

	/**
	 * Import a local temp file as an attachment.
	 *
	 * @param string $path
	 * @param int    $post_id
	 * @param string $desc
	 * @return int|WP_Error
	 */
	public function sideload_path( $path, $post_id = 0, $desc = '' ) {
		$this->bootstrap();
		return $this->handle_tmp( $path, $post_id, $desc );
	}

	/**
	 * @param string $tmp
	 * @param int    $post_id
	 * @param string $desc
	 * @param string $src_url
	 * @return int|WP_Error
	 */
	protected function handle_tmp( $tmp, $post_id, $desc, $src_url = '' ) {
		// Determine an SEO-friendly filename.
		$slug = $desc ? sanitize_title( $desc ) : 'vmia-image-' . gmdate( 'Ymd-His' );
		$slug = substr( $slug, 0, 80 );

		$ext  = 'jpg';
		$type = wp_check_filetype( $tmp )['ext'] ?? '';

		// If wp_check_filetype failed (e.g. because it's a .tmp file from wp_tempnam),
		// try to guess by reading the binary magic bytes.
		if ( ! $type && file_exists( $tmp ) ) {
			$kb = filesize( $tmp ) / 1024;
			if ( $kb < 0.1 ) {
				@unlink( $tmp ); // phpcs:ignore
				return new WP_Error( 'vmia_blank_file', 'The generated file is blank or too small' );
			}
			$img_info = @getimagesize( $tmp ); // phpcs:ignore
			if ( ! empty( $img_info['mime'] ) ) {
				$mime_to_ext = array(
					'image/jpeg' => 'jpg',
					'image/png'  => 'png',
					'image/gif'  => 'gif',
					'image/webp' => 'webp',
					'video/mp4'  => 'mp4',
					'video/webm' => 'webm',
					'video/quicktime' => 'mov',
				);
				$type = $mime_to_ext[ $img_info['mime'] ] ?? '';
			}
		}

		if ( $type ) {
			$ext = $type;
		} elseif ( $src_url && preg_match( '/\.(png|jpe?g|webp|gif|mp4|webm|mov)/i', $src_url, $m ) ) {
			$ext = strtolower( $m[1] );
		}

		$file_array = array(
			'name'     => $slug . '.' . $ext,
			'tmp_name' => $tmp,
		);

		$attach_id = media_handle_sideload( $file_array, $post_id, $desc );

		if ( is_wp_error( $attach_id ) ) {
			@unlink( $tmp ); // phpcs:ignore
			return $attach_id;
		}

		// Human title from desc.
		if ( $desc ) {
			wp_update_post(
				array(
					'ID'         => $attach_id,
					'post_title' => wp_strip_all_tags( $desc ),
				)
			);
		}
		update_post_meta( $attach_id, '_vmia_generated', 1 );
		return $attach_id;
	}
}
