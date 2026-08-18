<?php
/**
 * Explicit class-map autoloader.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Autoloader {

	/**
	 * class name => path relative to includes/.
	 *
	 * @var array<string,string>
	 */
	protected static $map = array(
		// Core.
		'VMIA_Plugin'          => 'class-vmia-plugin.php',
		'VMIA_Activator'       => 'class-vmia-activator.php',
		'VMIA_Settings'        => 'class-vmia-settings.php',
		'VMIA_Logger'          => 'class-vmia-logger.php',
		'VMIA_HTTP'            => 'class-vmia-http.php',

		// AI (text + vision).
		'VMIA_AI_Router'       => 'ai/class-vmia-ai-router.php',
		'VMIA_Model_Sync'      => 'ai/class-vmia-model-sync.php',
		'VMIA_Vision'          => 'ai/class-vmia-vision.php',
		'VMIA_Aipuffer_Client' => 'ai/class-vmia-aipuffer-client.php',
		'VMIA_Provider_Audit'  => 'ai/class-vmia-provider-audit.php',

		// Image generation.
		'VMIA_Image_Router'    => 'image/class-vmia-image-router.php',
		'VMIA_Provider_Pollinations' => 'image/class-vmia-pollinations.php',
		'VMIA_Provider_Google'       => 'image/class-vmia-google.php',
		'VMIA_Provider_Google_Search' => 'image/class-vmia-google-search.php',
		'VMIA_Provider_Openai'       => 'image/class-vmia-openai.php',
		'VMIA_Provider_Huggingface'  => 'image/class-vmia-huggingface.php',
		'VMIA_Provider_Cloudflare'   => 'image/class-vmia-cloudflare.php',
		'VMIA_Provider_Comfyui'      => 'image/class-vmia-comfyui.php',
		'VMIA_Provider_Pexels'       => 'image/class-vmia-pexels.php',
		'VMIA_Provider_Aipuffer'     => 'image/class-vmia-aipuffer-image.php',
		'VMIA_Media'           => 'image/class-vmia-media.php',
		'VMIA_Resize'          => 'image/class-vmia-resize.php',

		// SEO.
		'VMIA_SEO_Writer'      => 'seo/class-vmia-seo-writer.php',
		'VMIA_Auditor'         => 'seo/class-vmia-auditor.php',
		'VMIA_Fixer'           => 'seo/class-vmia-fixer.php',
		'VMIA_Stats'           => 'seo/class-vmia-stats.php',
		'VMIA_Undo'            => 'seo/class-vmia-undo.php',

		// Admin.
		'VMIA_Admin'           => 'admin/class-vmia-admin.php',
		'VMIA_Rest'            => 'admin/class-vmia-rest.php',

		// CLI.
		'VMIA_CLI'             => 'cli/class-vmia-cli.php',
	);

	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * @param string $class Class name.
	 */
	public static function load( $class ) {
		if ( isset( self::$map[ $class ] ) ) {
			$path = VMIA_DIR . 'includes/' . self::$map[ $class ];
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	}
}
