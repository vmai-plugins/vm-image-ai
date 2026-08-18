<?php
/**
 * Admin menus, assets and view routing.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Admin {

	const CAP = 'manage_options';

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . VMIA_BASENAME, array( $this, 'action_links' ) );

		// Media library single-image actions.
		add_filter( 'attachment_fields_to_edit', array( $this, 'attachment_fields' ), 10, 2 );
	}

	public function menu() {
		$icon = 'dashicons-format-image';

		add_menu_page(
			__( 'VM Image AI', 'vm-image-ai' ),
			__( 'VM Image AI', 'vm-image-ai' ),
			self::CAP,
			VMIA_SLUG,
			array( $this, 'render_dashboard' ),
			$icon,
			58
		);
		add_submenu_page( VMIA_SLUG, __( 'Dashboard', 'vm-image-ai' ), __( 'Dashboard', 'vm-image-ai' ), self::CAP, VMIA_SLUG, array( $this, 'render_dashboard' ) );
		add_submenu_page( VMIA_SLUG, __( 'Generate Images', 'vm-image-ai' ), __( 'Generate', 'vm-image-ai' ), self::CAP, VMIA_SLUG . '-generate', array( $this, 'render_generator' ) );
		add_submenu_page( VMIA_SLUG, __( 'Style Manager', 'vm-image-ai' ), __( 'Style Manager', 'vm-image-ai' ), self::CAP, VMIA_SLUG . '-style', array( $this, 'render_style' ) );
		add_submenu_page( VMIA_SLUG, __( 'SEO Auditor', 'vm-image-ai' ), __( 'SEO Auditor', 'vm-image-ai' ), self::CAP, VMIA_SLUG . '-audit', array( $this, 'render_auditor' ) );
		add_submenu_page( VMIA_SLUG, __( 'Reports', 'vm-image-ai' ), __( 'Reports', 'vm-image-ai' ), self::CAP, VMIA_SLUG . '-reports', array( $this, 'render_reports' ) );
		add_submenu_page( VMIA_SLUG, __( 'Settings', 'vm-image-ai' ), __( 'Settings', 'vm-image-ai' ), self::CAP, VMIA_SLUG . '-settings', array( $this, 'render_settings' ) );
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, VMIA_SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'vmia-admin', VMIA_URL . 'assets/css/admin.css', array(), VMIA_VERSION );
		wp_enqueue_script( 'vmia-admin', VMIA_URL . 'assets/js/admin.js', array( 'wp-api-fetch' ), VMIA_VERSION, true );
		wp_localize_script(
			'vmia-admin',
			'VMIA',
			array(
				'root'      => esc_url_raw( rest_url( 'vmia/v1' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'catalogue' => VMIA_Model_Sync::catalogue(),
			)
		);
	}

	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=' . VMIA_SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Dashboard', 'vm-image-ai' ) . '</a>' );
		return $links;
	}

	/* --------------------------- views --------------------------------- */

	protected function view( $file, $data = array() ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'vm-image-ai' ) );
		}
		$path = VMIA_DIR . 'includes/admin/views/' . $file . '.php';
		if ( is_readable( $path ) ) {
			extract( $data, EXTR_SKIP ); // phpcs:ignore
			include $path;
		}
	}

	public function render_dashboard() {
		$this->view(
			'dashboard',
			array(
				'summary' => VMIA_Auditor::summary(),
				'score'   => VMIA_Auditor::health_score(),
				'total'   => VMIA_Auditor::total_images(),
				'last'    => get_option( 'vmia_last_scan', '' ),
				'log'     => VMIA_Logger::recent( 12 ),
			)
		);
	}

	public function render_generator() {
		$this->view( 'generator', array( 'posts' => $this->recent_posts() ) );
	}

	public function render_style() {
		$this->view( 'style', array( 's' => VMIA_Settings::all() ) );
	}

	public function render_auditor() {
		$this->view(
			'auditor',
			array(
				'issues' => VMIA_Auditor::open_issues( 200 ),
				'labels' => VMIA_Auditor::ISSUES,
			)
		);
	}

	public function render_reports() {
		$this->view(
			'reports',
			array(
				'health_history' => VMIA_Stats::get_history( 'health_score' ),
				'storage_saved'  => VMIA_Stats::get_storage_saved(),
			)
		);
	}

	public function render_settings() {
		$this->view( 'settings', array( 's' => VMIA_Settings::all() ) );
	}

	protected function recent_posts() {
		return get_posts(
			array(
				'post_type'      => (array) VMIA_Settings::get( 'scan_post_types', array( 'post', 'page' ) ),
				'posts_per_page' => 40,
				'post_status'    => array( 'publish', 'draft' ),
				'orderby'        => 'modified',
			)
		);
	}

	/**
	 * Add a "VM Image AI" action panel to the media modal / edit screen.
	 *
	 * @param array   $fields
	 * @param WP_Post $post
	 * @return array
	 */
	public function attachment_fields( $fields, $post ) {
		if ( ! wp_attachment_is_image( $post->ID ) ) {
			return $fields;
		}
		$url = wp_nonce_url( admin_url( 'admin.php?page=' . VMIA_SLUG . '-audit' ), 'vmia' );
		$fields['vmia'] = array(
			'label' => __( 'VM Image AI', 'vm-image-ai' ),
			'input' => 'html',
			'html'  => '<button type="button" class="button vmia-quick-seo" data-id="' . (int) $post->ID . '">'
				. esc_html__( 'Auto-write SEO metadata', 'vm-image-ai' ) . '</button>'
				. '<button type="button" class="button vmia-quick-optimize" data-id="' . (int) $post->ID . '">'
				. esc_html__( 'Optimize / WebP', 'vm-image-ai' ) . '</button>'
				. '<p class="description">' . esc_html__( 'Powered by the VM Image AI engine.', 'vm-image-ai' ) . '</p>',
		);
		return $fields;
	}
}
