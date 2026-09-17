<?php
/**
 * REST endpoints backing the admin UI (namespace vmia/v1).
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Rest {

	const NS = 'vmia/v1';

	public function register_routes() {
		$perm = array( $this, 'can' );

		register_rest_route( self::NS, '/scan', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'scan' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/fix', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'fix' ),
			'permission_callback' => $perm,
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );

		register_rest_route( self::NS, '/bulk-fix', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'bulk_fix' ),
			'permission_callback' => $perm,
			'args'                => array(
				'ids'    => array( 'required' => true, 'type' => 'array' ),
				'action' => array( 'required' => true, 'type' => 'string' ),
			),
		) );

		register_rest_route( self::NS, '/suggest-fix', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'suggest_fix' ),
			'permission_callback' => $perm,
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );

		register_rest_route( self::NS, '/apply-fix', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'apply_fix' ),
			'permission_callback' => $perm,
			'args'                => array(
				'id' => array( 'required' => true ),
				'metadata' => array( 'required' => true, 'type' => 'object' ),
			),
		) );

		register_rest_route( self::NS, '/god-fix', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'god_fix' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/generate', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'generate' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/generate-video', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'generate_video' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/bulk-generate-missing', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'bulk_generate_missing' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/write-seo', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'write_seo' ),
			'permission_callback' => $perm,
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );

		register_rest_route( self::NS, '/optimize', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'optimize' ),
			'permission_callback' => $perm,
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );

		register_rest_route( self::NS, '/regenerate-image', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'regenerate_image' ),
			'permission_callback' => $perm,
			'args'                => array( 'id' => array( 'required' => true ) ),
		) );

		register_rest_route( self::NS, '/undo', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'undo' ),
			'permission_callback' => $perm,
			'args'                => array( 'oid' => array( 'required' => true ) ),
		) );

		register_rest_route( self::NS, '/settings', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'save_settings' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/sync-models', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'sync_models' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/aipuffer/bots', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'aipuffer_bots' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/aipuffer/test', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'aipuffer_test' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/audit-providers', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'audit_providers' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/summary', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_summary' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/check-update', array(
			'methods'             => array( 'GET', 'POST' ),
			'callback'            => array( $this, 'check_update' ),
			'permission_callback' => $perm,
		) );
	}

	public function can() {
		return current_user_can( 'manage_options' );
	}

	/* ------------------------------- handlers -------------------------- */

	public function scan( WP_REST_Request $r ) {
		$summary = ( new VMIA_Auditor() )->scan();
		return rest_ensure_response(
			array(
				'ok'      => true,
				'summary' => $summary,
				'score'   => VMIA_Auditor::health_score(),
				'total'   => array_sum( $summary ),
			)
		);
	}

	public function fix( WP_REST_Request $r ) {
		$res = ( new VMIA_Fixer() )->fix_by_id( (int) $r['id'] );
		return rest_ensure_response( $res );
	}

	public function bulk_fix( WP_REST_Request $r ) {
		$ids    = $r['ids'];
		$action = $r['action'];
		$res    = ( new VMIA_Fixer() )->bulk_fix( $ids, $action );
		return rest_ensure_response( $res );
	}

	public function suggest_fix( WP_REST_Request $r ) {
		global $wpdb;
		$audit_id = (int) $r['id'];
		$table    = $wpdb->prefix . 'vmia_audit';
		$row      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $audit_id ), ARRAY_A ); // phpcs:ignore

		if ( ! $row ) {
			return new WP_Error( 'vmia_not_found', 'Audit row not found', array( 'status' => 404 ) );
		}

		$obj_id = (int) $row['object_id'];
		$code   = $row['issue_code'];
		$res    = array( 'ok' => false );

		if ( in_array( $code, array( 'missing_alt', 'short_alt', 'filename_alt', 'filename_title', 'missing_caption', 'bad_filename', 'duplicate_alt', 'keyword_gap' ), true ) ) {
			$post_id = (int) wp_get_post_parent_id( $obj_id );
			$suggested = ( new VMIA_SEO_Writer() )->generate( $obj_id, $post_id );
			if ( $suggested ) {
				$res = array(
					'ok' => true,
					'type' => 'metadata',
					'suggestion' => $suggested,
					'current' => array(
						'alt' => get_post_meta( $obj_id, '_wp_attachment_image_alt', true ),
						'title' => get_the_title( $obj_id ),
						'caption' => get_post_field( 'post_excerpt', $obj_id ),
						'description' => get_post_field( 'post_content', $obj_id ),
					)
				);
			}
		}

		return rest_ensure_response( $res );
	}

	public function apply_fix( WP_REST_Request $r ) {
		global $wpdb;
		$audit_id = (int) $r['id'];
		$metadata = $r['metadata'];
		$table    = $wpdb->prefix . 'vmia_audit';
		$row      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $audit_id ), ARRAY_A ); // phpcs:ignore

		if ( ! $row ) {
			return new WP_Error( 'vmia_not_found', 'Audit row not found', array( 'status' => 404 ) );
		}

		$obj_id = (int) $row['object_id'];

		if ( isset( $metadata['alt'] ) ) {
			update_post_meta( $obj_id, '_wp_attachment_image_alt', sanitize_text_field( $metadata['alt'] ) );
		}

		$update = array( 'ID' => $obj_id );
		if ( isset( $metadata['title'] ) ) $update['post_title'] = sanitize_text_field( $metadata['title'] );
		if ( isset( $metadata['caption'] ) ) $update['post_excerpt'] = sanitize_text_field( $metadata['caption'] );
		if ( isset( $metadata['description'] ) ) $update['post_content'] = sanitize_textarea_field( $metadata['description'] );

		if ( count( $update ) > 1 ) {
			wp_update_post( $update );
		}

		( new VMIA_Fixer() )->resolve_audit_by_id( $audit_id );

		return rest_ensure_response( array( 'ok' => true, 'message' => 'Fix applied manually' ) );
	}

	public function god_fix( WP_REST_Request $r ) {
		$batch = (int) $r->get_param( 'batch' );
		$res   = ( new VMIA_Fixer() )->god_fix( $batch );
		return rest_ensure_response( $res );
	}

	public function generate( WP_REST_Request $r ) {
		$subject  = sanitize_text_field( (string) $r->get_param( 'subject' ) );
		$post_id  = (int) $r->get_param( 'post_id' );
		$featured = (bool) $r->get_param( 'set_featured' );
		$mode     = $r->get_param( 'mode' ) === 'featured' ? 'featured' : 'blog';

		if ( '' === $subject && $post_id ) {
			$subject = get_the_title( $post_id );
		}
		if ( '' === $subject ) {
			return new WP_Error( 'vmia_no_subject', __( 'Please provide a subject or a post.', 'vm-image-ai' ), array( 'status' => 400 ) );
		}

		$w = 'featured' === $mode ? (int) VMIA_Settings::get( 'featured_w', 1200 ) : (int) VMIA_Settings::get( 'blog_max_w', 1600 );
		$h = 'featured' === $mode ? (int) VMIA_Settings::get( 'featured_h', 630 ) : (int) round( $w * 0.5625 );

		$res = ( new VMIA_Image_Router() )->generate_to_library(
			$subject,
			array(
				'post_id'      => $post_id,
				'set_featured' => $featured,
				'width'        => $w,
				'height'       => $h,
				'title'        => $subject,
			)
		);
		return rest_ensure_response( $res );
	}

	public function generate_video( WP_REST_Request $r ) {
		$subject  = sanitize_text_field( (string) $r->get_param( 'subject' ) );
		$post_id  = (int) $r->get_param( 'post_id' );
		$featured = (bool) $r->get_param( 'set_featured' );

		if ( '' === $subject && $post_id ) {
			$subject = get_the_title( $post_id );
		}
		if ( '' === $subject ) {
			return new WP_Error( 'vmia_no_subject', __( 'Please provide a subject or a post.', 'vm-image-ai' ), array( 'status' => 400 ) );
		}

		$res = ( new VMIA_Video_Router() )->generate_to_library(
			$subject,
			array(
				'post_id'      => $post_id,
				'set_featured' => $featured,
				'title'        => $subject,
			)
		);
		return rest_ensure_response( $res );
	}

	public function bulk_generate_missing() {
		$post_types = (array) VMIA_Settings::get( 'scan_post_types', array( 'post', 'page' ) );
		$posts = get_posts( array(
			'post_type'      => $post_types,
			'posts_per_page' => 10, // Small batch for safety
			'meta_query'     => array(
				array(
					'key'     => '_thumbnail_id',
					'compare' => 'NOT EXISTS'
				)
			)
		) );

		if ( empty( $posts ) ) {
			return rest_ensure_response( array( 'ok' => true, 'message' => 'No missing featured images found' ) );
		}

		$results = array( 'fixed' => 0, 'failed' => 0 );
		$router  = new VMIA_Image_Router();

		foreach ( $posts as $p ) {
			$res = $router->generate_to_library( get_the_title( $p ), array(
				'post_id'      => $p->ID,
				'set_featured' => true,
				'width'        => (int) VMIA_Settings::get( 'featured_w', 1200 ),
				'height'       => (int) VMIA_Settings::get( 'featured_h', 630 ),
			) );

			if ( ! empty( $res['ok'] ) ) {
				$results['fixed']++;
			} else {
				$results['failed']++;
			}
		}

		return rest_ensure_response( array( 'ok' => true, 'count' => $results['fixed'] ) );
	}

	public function write_seo( WP_REST_Request $r ) {
		$id      = (int) $r['id'];
		$post_id = (int) wp_get_post_parent_id( $id );
		$data    = ( new VMIA_SEO_Writer() )->write_for_attachment( $id, $post_id, array( 'overwrite' => true ) );
		return rest_ensure_response( array( 'ok' => ! empty( $data ), 'data' => $data ) );
	}

	public function optimize( WP_REST_Request $r ) {
		$res = ( new VMIA_Resize() )->optimize_attachment( (int) $r['id'] );
		return rest_ensure_response( $res );
	}

	public function regenerate_image( WP_REST_Request $r ) {
		$res = ( new VMIA_Fixer() )->regenerate_image_for_audit( (int) $r['id'] );
		return rest_ensure_response( $res );
	}

	public function undo( WP_REST_Request $r ) {
		$res = VMIA_Undo::revert_last( (int) $r['oid'] );
		return rest_ensure_response( array( 'ok' => $res, 'message' => $res ? 'Reverted last change' : 'No history found' ) );
	}

	public function save_settings( WP_REST_Request $r ) {
		$in    = (array) $r->get_json_params();
		$clean = $this->sanitize_settings( $in );
		VMIA_Settings::update( $clean );
		VMIA_Activator::schedule();
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public function sync_models() {
		$cat = VMIA_Model_Sync::refresh();
		$counts = array();
		foreach ( $cat as $k => $v ) {
			if ( is_array( $v ) ) {
				$counts[ $k ] = count( $v );
			}
		}

		return rest_ensure_response(
			array(
				'ok'     => true,
				'counts' => $counts,
				'text'   => VMIA_Model_Sync::resolve_text_model(),
				'vision' => VMIA_Model_Sync::resolve_vision_model(),
				'catalogue' => $cat,
			)
		);
	}

	/**
	 * List bots visible to the (possibly unsaved) base/key currently in the
	 * settings form, so the admin can pick a valid bot_id before saving.
	 */
	public function aipuffer_bots( WP_REST_Request $r ) {
		$base = $r->get_param( 'base' );
		$key  = $r->get_param( 'key' );
		$base = ( null !== $base && '' !== $base ) ? sanitize_text_field( $base ) : VMIA_Settings::get( 'aipuffer_base' );
		$key  = ( null !== $key && '' !== $key ) ? sanitize_text_field( $key ) : VMIA_Settings::get( 'aipuffer_key' );

		$res = VMIA_Aipuffer_Client::list_bots( $base, $key );
		if ( empty( $res['ok'] ) ) {
			return new WP_Error( 'vmia_aipuffer_bots', $res['error'] ?? 'could not list bots', array( 'status' => 502 ) );
		}
		return rest_ensure_response( array(
			'ok'        => true,
			'bots'      => $res['bots'],
			'real_base' => $res['real_base'] ?? $base,
		) );
	}

	/**
	 * Full connection test: reachability + (if a bot_id is configured)
	 * whether that bot actually exists. Backs the settings "Test AI Puffer
	 * connection" button and the dashboard status indicator.
	 */
	public function aipuffer_test( WP_REST_Request $r ) {
		$base    = $r->get_param( 'base' );
		$key     = $r->get_param( 'key' );
		$bot_id  = $r->get_param( 'bot_id' );
		$base    = ( null !== $base && '' !== $base ) ? sanitize_text_field( $base ) : VMIA_Settings::get( 'aipuffer_base' );
		$key     = ( null !== $key && '' !== $key ) ? sanitize_text_field( $key ) : VMIA_Settings::get( 'aipuffer_key' );
		$bot_id  = ( null !== $bot_id ) ? sanitize_text_field( $bot_id ) : (string) VMIA_Settings::get( 'aipuffer_bot_id' );

		$list_res = VMIA_Aipuffer_Client::list_bots( $base, $key );
		if ( empty( $list_res['ok'] ) ) {
			set_transient( 'vmia_aipuffer_status', array( 'ok' => false, 'checked_at' => time() ), HOUR_IN_SECONDS );
			return new WP_Error( 'vmia_aipuffer_test', $list_res['error'] ?? 'connection failed', array( 'status' => 502 ) );
		}

		$real_base = $list_res['real_base'] ?? $base;
		$local     = ! empty( $list_res['local'] );

		$bot_valid = null;
		if ( '' !== $bot_id ) {
			$bot_valid = false;
			foreach ( $list_res['bots'] as $bot ) {
				if ( (string) $bot['id'] === (string) $bot_id ) {
					$bot_valid = true;
					break;
				}
			}
		}

		$status = array(
			'ok'         => true,
			'bot_valid'  => $bot_valid,
			'local'      => $local,
			'checked_at' => time(),
		);
		set_transient( 'vmia_aipuffer_status', $status, HOUR_IN_SECONDS );

		return rest_ensure_response(
			array(
				'ok'        => true,
				'bots'      => $list_res['bots'],
				'bot_id'    => $bot_id,
				'bot_valid' => $bot_valid,
				'local'     => $local,
				'real_base' => $real_base,
			)
		);
	}

	public function audit_providers() {
		$res = ( new VMIA_Provider_Audit() )->run_test();
		return rest_ensure_response( array( 'ok' => true, 'results' => $res ) );
	}

	public function get_summary() {
		return rest_ensure_response(
			array(
				'summary' => VMIA_Auditor::summary(),
				'score'   => VMIA_Auditor::health_score(),
				'total'   => VMIA_Auditor::total_images(),
			)
		);
	}

	public function check_update() {
		$res = VMIA_GitHub_Updater::check_for_updates();
		return rest_ensure_response( $res );
	}

	/* ------------------------------- sanitising ------------------------ */

	protected function sanitize_settings( $in ) {
		$out  = array();
		$text = array(
			'aipuffer_base', 'aipuffer_key', 'aipuffer_bot_id', 'aipuffer_img_path', 'aipuffer_img_engine',
			'openai_key', 'openai_model', 'openai_image_model',
			'omniroute_base', 'omniroute_key', 'omniroute_text_model', 'omniroute_vision_model', 'omniroute_image_model', 'omniroute_video_model',
			'gemini_key', 'gemini_model', 'gemini_vision_model', 'gemini_image_model',
			'openrouter_key', 'openrouter_model', 'openrouter_vision',
			'xai_key', 'xai_model',
			'pollinations_model', 'comfyui_base', 'comfyui_ckpt',
			'huggingface_key', 'huggingface_model',
			'cloudflare_account_id', 'cloudflare_key', 'cloudflare_model',
			'pexels_key', 'google_search_key', 'google_search_cx',
			'locale_hint', 'style_preset', 'theme', 'github_token',
		);
		foreach ( $text as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$out[ $k ] = sanitize_text_field( $in[ $k ] );
			}
		}
		if ( isset( $in['brand_context'] ) ) {
			$out['brand_context'] = sanitize_textarea_field( $in['brand_context'] );
		}
		if ( isset( $in['negative_prompt'] ) ) {
			$out['negative_prompt'] = sanitize_textarea_field( $in['negative_prompt'] );
		}

		$ints = array(
			'comfyui_steps', 'featured_w', 'featured_h', 'blog_max_w',
			'webp_quality', 'jpeg_quality', 'max_kb', 'max_dim', 'alt_min_len', 'alt_max_len', 'god_fix_batch',
		);
		foreach ( $ints as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$out[ $k ] = max( 0, (int) $in[ $k ] );
			}
		}

		$bools = array( 'pollinations_nologo', 'convert_webp', 'auto_scan', 'auto_alt_on_upload', 'auto_resize_upload', 'autopilot_enabled' );
		foreach ( $bools as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$out[ $k ] = (bool) $in[ $k ];
			}
		}

		if ( isset( $in['scan_frequency'] ) && in_array( $in['scan_frequency'], array( 'hourly', 'twicedaily', 'daily' ), true ) ) {
			$out['scan_frequency'] = $in['scan_frequency'];
		}

		foreach ( array( 'ai_order', 'vision_order', 'image_order', 'scan_post_types' ) as $k ) {
			if ( isset( $in[ $k ] ) && is_array( $in[ $k ] ) ) {
				$out[ $k ] = array_values( array_map( 'sanitize_key', $in[ $k ] ) );
			}
		}
		return $out;
	}
}
