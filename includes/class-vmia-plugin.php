<?php
/**
 * Main orchestrator. Wires hooks, cron, admin, REST, CLI.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Plugin {

	/** @var VMIA_Plugin|null */
	protected static $instance = null;

	/** @var VMIA_Admin */
	public $admin;

	/** @var VMIA_Rest */
	public $rest;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function run() {
		VMIA_Activator::maybe_upgrade();

		// Admin UI + REST.
		if ( is_admin() ) {
			$this->admin = new VMIA_Admin();
			$this->admin->hooks();
			VMIA_GitHub_Updater::init();
		}
		$this->rest = new VMIA_Rest();
		add_action( 'rest_api_init', array( $this->rest, 'register_routes' ) );

		// Cron handlers.
		add_action( VMIA_Activator::CRON_SCAN, array( $this, 'cron_scan' ) );
		add_action( VMIA_Activator::CRON_SYNC, array( 'VMIA_Model_Sync', 'refresh' ) );
		add_action( VMIA_Activator::CRON_AUTOPILOT, array( $this, 'cron_autopilot' ) );

		// On new upload: optionally auto-write SEO metadata + resize.
		add_action( 'add_attachment', array( $this, 'on_upload' ), 20 );

		// WP-CLI.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'vmia', 'VMIA_CLI' );
		}
	}

	/**
	 * Handle freshly uploaded attachments.
	 *
	 * @param int $attach_id
	 */
	public function on_upload( $attach_id ) {
		if ( ! wp_attachment_is_image( $attach_id ) ) {
			return;
		}

		if ( VMIA_Settings::get( 'auto_resize_upload' ) ) {
			( new VMIA_Resize() )->optimize_attachment( $attach_id );
		}

		if ( VMIA_Settings::get( 'auto_alt_on_upload' ) && VMIA_Settings::has_text_ai() ) {
			$post_id = (int) wp_get_post_parent_id( $attach_id );
			( new VMIA_SEO_Writer() )->write_for_attachment( $attach_id, $post_id );
		}
	}

	/**
	 * Scheduled library scan.
	 */
	public function cron_scan() {
		( new VMIA_Auditor() )->scan();
	}

	/**
	 * Background autopilot: fix a batch of issues automatically.
	 */
	public function cron_autopilot() {
		if ( ! VMIA_Settings::get( 'autopilot_enabled' ) ) {
			return;
		}
		$res = ( new VMIA_Fixer() )->god_fix();
		if ( $res['fixed'] > 0 ) {
			VMIA_Logger::add( 'autopilot', 0, 'system', sprintf( 'Autopilot fixed %d issues.', $res['fixed'] ) );
		}
	}
}
