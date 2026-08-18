<?php
/**
 * Applies fixes for detected image-SEO issues. Powers the one-click per-issue
 * fix and the bulk "God Fix" autopilot.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Fixer {

	/**
	 * Fix a single audit row by id.
	 *
	 * @param int $audit_id
	 * @return array { ok, message }
	 */
	public function fix_by_id( $audit_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vmia_audit';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $audit_id ), ARRAY_A ); // phpcs:ignore
		if ( ! $row ) {
			return array( 'ok' => false, 'message' => 'Issue not found' );
		}
		return $this->fix_row( $row );
	}

	/**
	 * @param array $row Audit row.
	 * @return array
	 */
	public function fix_row( $row ) {
		$code   = $row['issue_code'];
		$obj_id = (int) $row['object_id'];
		$result = array( 'ok' => false, 'message' => 'No handler' );

		switch ( $code ) {
			case 'missing_alt':
			case 'short_alt':
			case 'filename_alt':
			case 'filename_title':
			case 'missing_caption':
			case 'bad_filename':
			case 'duplicate_alt':
				$post_id = (int) wp_get_post_parent_id( $obj_id );
				( new VMIA_SEO_Writer() )->write_for_attachment( $obj_id, $post_id, array( 'overwrite' => true ) );
				$result = array( 'ok' => true, 'message' => 'Rewrote SEO metadata' );
				break;

			case 'oversized_file':
			case 'oversized_dim':
			case 'legacy_format':
				$res = ( new VMIA_Resize() )->optimize_attachment( $obj_id );
				$result = ! empty( $res['ok'] )
					? array( 'ok' => true, 'message' => sprintf( 'Optimised %s → %s', size_format( $res['before'] ), size_format( $res['after'] ) ) )
					: array( 'ok' => false, 'message' => $res['error'] ?? 'resize failed' );
				break;

			case 'no_featured':
			case 'no_images':
				$result = $this->fix_no_featured( (int) $row['post_id'] );
				break;

			case 'content_img_no_alt':
				$result = $this->fix_content_alts( (int) $row['post_id'] );
				break;

			case 'broken_file':
				$result = $this->regenerate_image_for_audit( (int) $row['id'] );
				break;
		}

		if ( ! empty( $result['ok'] ) ) {
			$this->resolve( (int) $row['id'] );
		}
		return $result;
	}

	/**
	 * Generate + attach a featured image for a post lacking one.
	 */
	protected function fix_no_featured( $post_id ) {
		if ( ! $post_id ) {
			return array( 'ok' => false, 'message' => 'no post' );
		}
		$title = get_the_title( $post_id );
		$res   = ( new VMIA_Image_Router() )->generate_to_library(
			$title,
			array(
				'post_id'      => $post_id,
				'set_featured' => true,
				'width'        => (int) VMIA_Settings::get( 'featured_w', 1200 ),
				'height'       => (int) VMIA_Settings::get( 'featured_h', 630 ),
				'title'        => $title,
			)
		);
		return ! empty( $res['ok'] )
			? array( 'ok' => true, 'message' => 'Generated featured image via ' . $res['provider'] )
			: array( 'ok' => false, 'message' => $res['error'] ?? 'generation failed' );
	}

	/**
	 * Fill missing alt attributes inside a post's content.
	 */
	protected function fix_content_alts( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'ok' => false, 'message' => 'no post' );
		}
		$content = $post->post_content;
		$title   = get_the_title( $post_id );
		$fixed   = 0;

		$content = preg_replace_callback(
			'/<img\b[^>]*>/i',
			function ( $m ) use ( &$fixed, $title, $post_id ) {
				$tag = $m[0];
				if ( preg_match( '/\balt\s*=\s*(["\'])(.*?)\1/i', $tag, $a ) && '' !== trim( $a[2] ) ) {
					return $tag; // already has alt.
				}
				// Resolve attachment id from wp-image-XX to reuse/generate its alt.
				$alt = '';
				if ( preg_match( '/wp-image-(\d+)/', $tag, $idm ) ) {
					$aid = (int) $idm[1];
					$alt = trim( (string) get_post_meta( $aid, '_wp_attachment_image_alt', true ) );
					if ( '' === $alt && VMIA_Settings::has_text_ai() ) {
						$data = ( new VMIA_SEO_Writer() )->write_for_attachment( $aid, $post_id, array( 'overwrite' => false ) );
						$alt  = $data['alt'] ?? '';
					}
				}
				if ( '' === $alt ) {
					$alt = $title; // last-resort contextual alt.
				}
				$alt   = esc_attr( $alt );
				$fixed++;
				if ( preg_match( '/\balt\s*=\s*(["\']).*?\1/i', $tag ) ) {
					return preg_replace( '/\balt\s*=\s*(["\']).*?\1/i', 'alt="' . $alt . '"', $tag );
				}
				return preg_replace( '/<img\b/i', '<img alt="' . $alt . '"', $tag, 1 );
			},
			$content
		);

		if ( $fixed > 0 ) {
			wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
			return array( 'ok' => true, 'message' => sprintf( 'Added alt to %d image(s)', $fixed ) );
		}
		return array( 'ok' => true, 'message' => 'Nothing to change' );
	}

	/**
	 * Bulk autopilot. Processes up to $batch open issues (highest severity first).
	 *
	 * @param int $batch
	 * @return array { processed, fixed, remaining, results[] }
	 */
	public function god_fix( $batch = 0 ) {
		$batch = $batch ?: (int) VMIA_Settings::get( 'god_fix_batch', 15 );
		$rows  = VMIA_Auditor::open_issues( $batch );
		$out   = array( 'processed' => 0, 'fixed' => 0, 'results' => array() );

		foreach ( $rows as $row ) {
			$res = $this->fix_row( $row );
			$out['processed']++;
			if ( ! empty( $res['ok'] ) ) {
				$out['fixed']++;
			}
			$out['results'][] = array(
				'code'    => $row['issue_code'],
				'object'  => (int) $row['object_id'],
				'ok'      => ! empty( $res['ok'] ),
				'message' => $res['message'],
			);
		}

		$out['remaining'] = array_sum( VMIA_Auditor::summary() );
		VMIA_Logger::add( 'fix', 0, 'godfix', sprintf( 'God Fix batch: %d/%d fixed, %d remaining', $out['fixed'], $out['processed'], $out['remaining'] ) );
		return $out;
	}

	public function resolve_audit_by_id( $audit_id ) {
		$this->resolve( (int) $audit_id );
	}

	/**
	 * Completely replace a broken or undesired image with a new AI generation.
	 *
	 * @param int $audit_id
	 * @return array
	 */
	public function regenerate_image_for_audit( $audit_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vmia_audit';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $audit_id ), ARRAY_A ); // phpcs:ignore
		if ( ! $row ) {
			return array( 'ok' => false, 'message' => 'Audit entry not found' );
		}

		$obj_id  = (int) $row['object_id'];
		$post_id = (int) $row['post_id'];
		if ( ! $post_id ) {
			$post_id = (int) wp_get_post_parent_id( $obj_id );
		}

		$subject = $post_id ? get_the_title( $post_id ) : 'Professional blog image';

		// 1. Generate new image.
		$router = new VMIA_Image_Router();
		$res = $router->generate_to_library( $subject, array(
			'post_id'      => $post_id,
			'set_featured' => ( 'no_featured' === $row['issue_code'] || has_post_thumbnail( $post_id ) && get_post_thumbnail_id( $post_id ) == $obj_id ),
			'width'        => (int) VMIA_Settings::get( 'featured_w', 1200 ),
			'height'       => (int) VMIA_Settings::get( 'featured_h', 630 ),
		) );

		if ( ! empty( $res['ok'] ) ) {
			// 2. If it was an existing attachment that we replaced, delete the old one to avoid clutter.
			if ( $obj_id && $obj_id !== (int) ( $res['attach_id'] ?? 0 ) ) {
				wp_delete_attachment( $obj_id, true );
			}
			$this->resolve( $audit_id );
			return array( 'ok' => true, 'message' => 'Image replaced with new AI generation' );
		}

		return array( 'ok' => false, 'message' => $res['error'] ?? 'Generation failed' );
	}

	/**
	 * Apply a specific bulk action to multiple audit rows.
	 *
	 * @param array  $ids    Audit row IDs.
	 * @param string $action 'fix' (AI Metadata) or 'optimize'.
	 * @return array
	 */
	public function bulk_fix( $ids, $action ) {
		$results = array( 'fixed' => 0, 'failed' => 0, 'messages' => array() );
		global $wpdb;
		$table = $wpdb->prefix . 'vmia_audit';

		foreach ( $ids as $id ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", (int) $id ), ARRAY_A ); // phpcs:ignore
			if ( ! $row ) continue;

			$obj_id = (int) $row['object_id'];
			$res    = array( 'ok' => false );

			if ( 'fix' === $action ) {
				if ( 'broken_file' === $row['issue_code'] ) {
					$res = $this->regenerate_image_for_audit( (int) $id );
				} else {
					$post_id = (int) wp_get_post_parent_id( $obj_id );
					( new VMIA_SEO_Writer() )->write_for_attachment( $obj_id, $post_id, array( 'overwrite' => true ) );
					$res = array( 'ok' => true );
				}
			} elseif ( 'optimize' === $action ) {
				$res = ( new VMIA_Resize() )->optimize_attachment( $obj_id );
			}

			if ( ! empty( $res['ok'] ) ) {
				$results['fixed']++;
				$this->resolve( (int) $id );
			} else {
				$results['failed']++;
			}
		}

		return $results;
	}

	protected function resolve( $audit_id ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'vmia_audit',
			array( 'status' => 'resolved', 'resolved_at' => current_time( 'mysql' ) ),
			array( 'id' => $audit_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}
}
