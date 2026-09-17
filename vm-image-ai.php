<?php
/**
 * Plugin Name:       VM Image AI
 * Plugin URI:        https://vmstudio.digital/vm-image-ai
 * Description:        AI-integrated featured & blog image generator with intelligent Image-SEO auditing, auto alt/title/caption/description writing, resizing/compression, gap detection and one-click "God Fix". Free engine stack: AI Puffer + Gemini + OpenRouter (live model sync fallback) for text/vision, and AI Puffer + Pollinations + ComfyUI + Pexels for image generation.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            VM Studio Creatives
 * Author URI:        https://vmstudio.digital
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vm-image-ai
 * Domain Path:       /languages
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */
define( 'VMIA_VERSION', '1.0.1' );
define( 'VMIA_DB_VERSION', '1.0.1' );
define( 'VMIA_FILE', __FILE__ );
define( 'VMIA_DIR', plugin_dir_path( __FILE__ ) );
define( 'VMIA_URL', plugin_dir_url( __FILE__ ) );
define( 'VMIA_BASENAME', plugin_basename( __FILE__ ) );
define( 'VMIA_OPTION', 'vmia_settings' );
define( 'VMIA_SLUG', 'vm-image-ai' );

/* -------------------------------------------------------------------------
 * PSR-ish autoloader (explicit class map — matches the VM Studio pattern)
 * ---------------------------------------------------------------------- */
require_once VMIA_DIR . 'includes/class-vmia-autoloader.php';
VMIA_Autoloader::register();

/* -------------------------------------------------------------------------
 * Activation / Deactivation
 * ---------------------------------------------------------------------- */
register_activation_hook( __FILE__, array( 'VMIA_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'VMIA_Activator', 'deactivate' ) );

/* -------------------------------------------------------------------------
 * Boot
 * ---------------------------------------------------------------------- */
add_action( 'plugins_loaded', static function () {
	load_plugin_textdomain( 'vm-image-ai', false, dirname( VMIA_BASENAME ) . '/languages' );
	VMIA_Plugin::instance()->run();
} );

/**
 * Convenience accessor.
 *
 * @return VMIA_Plugin
 */
function vmia() {
	return VMIA_Plugin::instance();
}
