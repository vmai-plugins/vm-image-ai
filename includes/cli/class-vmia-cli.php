<?php
/**
 * WP-CLI: `wp vmia <command>` for agency multi-site scripting.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage VM Image AI from the command line.
 */
class VMIA_CLI {

	/**
	 * Scan the media library + posts for image-SEO issues.
	 *
	 * ## EXAMPLES
	 *     wp vmia scan
	 *
	 * @when after_wp_load
	 */
	public function scan() {
		$summary = ( new VMIA_Auditor() )->scan();
		\WP_CLI::log( 'Health score: ' . VMIA_Auditor::health_score() . '/100' );
		foreach ( $summary as $code => $n ) {
			\WP_CLI::log( sprintf( '  %-22s %d', $code, $n ) );
		}
		\WP_CLI::success( 'Scan complete — ' . array_sum( $summary ) . ' issue(s).' );
	}

	/**
	 * Run the God Fix autopilot until the queue is empty.
	 *
	 * ## OPTIONS
	 *
	 * [--batch=<n>]
	 * : Items per batch. Default from settings.
	 *
	 * ## EXAMPLES
	 *     wp vmia god-fix --batch=25
	 *
	 * @when after_wp_load
	 */
	public function god_fix( $args, $assoc ) {
		$batch = isset( $assoc['batch'] ) ? (int) $assoc['batch'] : 0;
		$fixer = new VMIA_Fixer();
		$total = 0;
		$stuck = 0;
		do {
			$res    = $fixer->god_fix( $batch );
			$total += $res['fixed'];
			\WP_CLI::log( sprintf( 'Batch: %d fixed, %d remaining', $res['fixed'], $res['remaining'] ) );
			if ( 0 === $res['processed'] ) {
				break;
			}
			if ( 0 === $res['fixed'] && $res['processed'] > 0 ) {
				$stuck++;
				if ( $stuck >= 2 ) {
					\WP_CLI::warning( sprintf( 'Stopping: %d remaining issue(s) could not be resolved automatically.', $res['remaining'] ) );
					break;
				}
			} else {
				$stuck = 0;
			}
		} while ( $res['remaining'] > 0 );
		\WP_CLI::success( "God Fix complete — {$total} resolved." );
	}

	/**
	 * Generate a featured image for a post.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : Target post ID.
	 *
	 * @when after_wp_load
	 */
	public function featured( $args ) {
		$post_id = (int) $args[0];
		if ( ! get_post( $post_id ) ) {
			\WP_CLI::error( 'No such post.' );
		}
		$res = ( new VMIA_Image_Router() )->generate_to_library(
			get_the_title( $post_id ),
			array(
				'post_id'      => $post_id,
				'set_featured' => true,
				'width'        => (int) VMIA_Settings::get( 'featured_w', 1200 ),
				'height'       => (int) VMIA_Settings::get( 'featured_h', 630 ),
				'title'        => get_the_title( $post_id ),
			)
		);
		if ( ! empty( $res['ok'] ) ) {
			\WP_CLI::success( 'Featured image set via ' . $res['provider'] . ' (attachment #' . $res['attach_id'] . ').' );
		} else {
			\WP_CLI::error( $res['error'] ?? 'generation failed' );
		}
	}

	/**
	 * Write SEO metadata for all images missing alt text.
	 *
	 * ## OPTIONS
	 *
	 * [--overwrite]
	 * : Rewrite even where alt text already exists.
	 *
	 * @when after_wp_load
	 */
	public function seo( $args, $assoc ) {
		$overwrite = isset( $assoc['overwrite'] );
		$q = new WP_Query( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		$writer = new VMIA_SEO_Writer();
		$n      = 0;
		foreach ( $q->posts as $id ) {
			$alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
			if ( $overwrite || '' === trim( (string) $alt ) ) {
				$writer->write_for_attachment( $id, (int) wp_get_post_parent_id( $id ), array( 'overwrite' => $overwrite ) );
				$n++;
			}
		}
		\WP_CLI::success( "Wrote metadata for {$n} image(s)." );
	}

	/**
	 * Refresh the live model catalogue.
	 *
	 * @when after_wp_load
	 */
	public function sync() {
		$cat = VMIA_Model_Sync::refresh();
		\WP_CLI::success( 'Synced ' . count( $cat['openrouter'] ?? array() ) . ' models. Text model: ' . VMIA_Model_Sync::resolve_text_model() );
	}
}
