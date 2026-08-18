<?php
/**
 * Settings store + defaults.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Settings {

	/** @var array|null */
	protected static $cache = null;

	/**
	 * Hard defaults. Anything the user has not set falls back here.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// -- Text/vision AI engine (fallback chain, first success wins) --
			'ai_order'            => array( 'aipuffer', 'openai', 'gemini', 'openrouter' ),
			'vision_order'        => array( 'openai', 'gemini', 'openrouter' ),

			// AI Puffer (AIPKit) — talks to its own REST API on this site.
			'aipuffer_base'       => rtrim( home_url(), '/' ) . '/wp-json/aipkit/v1',
			'aipuffer_key'        => '',
			'aipuffer_bot_id'     => '',

			// OpenAI.
			'openai_key'          => '',
			'openai_model'        => 'gpt-4o-mini',

			// Gemini.
			'gemini_key'          => '',
			'gemini_model'        => 'gemini-2.0-flash',
			'gemini_vision_model' => 'gemini-2.0-flash',

			// OpenRouter.
			'openrouter_key'      => '',
			'openrouter_model'    => 'auto',                     // "auto" = live-sync pick.
			'openrouter_vision'   => 'auto',

			// xAI (Grok).
			'xai_key'             => '',
			'xai_model'           => 'grok-2-latest',

			// -- Image generation engine (fallback chain) --
			'image_order'         => array( 'aipuffer', 'google', 'openai', 'pollinations', 'huggingface', 'cloudflare', 'pexels' ),

			// Google Imagen (via Gemini API).
			'gemini_image_model'  => 'imagen-3.0-generate-001',

			// OpenAI DALL-E.
			'openai_image_model'  => 'dall-e-3',

			// Pollinations (no key, free).
			'pollinations_model'  => 'flux',
			'pollinations_nologo' => true,

			// Hugging Face (free inference API).
			'huggingface_key'     => '',
			'huggingface_model'   => 'black-forest-labs/FLUX.1-schnell',

			// Cloudflare Workers AI.
			'cloudflare_account_id' => '',
			'cloudflare_key'        => '',
			'cloudflare_model'      => '@cf/black-forest-labs/flux-1-schnell',

			// ComfyUI (user's own server).
			'comfyui_base'        => '',                          // e.g. http://127.0.0.1:8188
			'comfyui_ckpt'        => 'sd_xl_base_1.0.safetensors',
			'comfyui_steps'       => 25,

			// Pexels (stock fallback).
			'pexels_key'          => '',

			// Google Search (stock fallback).
			'google_search_key'   => '',
			'google_search_cx'    => '',

			// AI Puffer image endpoint (optional).
			'aipuffer_img_path'   => '/image',                    // appended to aipuffer_base
			'aipuffer_img_engine' => 'openai',                    // openai | google | azure

			// -- Sizing / optimisation --
			'featured_w'          => 1200,
			'featured_h'          => 630,
			'blog_max_w'          => 1600,
			'convert_webp'        => true,
			'webp_quality'        => 82,
			'jpeg_quality'        => 82,
			'max_kb'              => 300,                          // audit flags files above this.
			'max_dim'             => 2560,                         // audit flags width/height above this.

			// -- SEO writer --
			'alt_min_len'         => 12,
			'alt_max_len'         => 125,
			'brand_context'       => '',                           // optional brand/business context injected into prompts.
			'style_preset'        => 'editorial',                  // cinematic | editorial | minimalist | digital_art
			'negative_prompt'     => 'blurry, distorted, low quality, text, watermark',
			'locale_hint'         => 'en',

			// -- Automation --
			'auto_scan'           => true,
			'scan_frequency'      => 'daily',                      // hourly | twicedaily | daily
			'auto_alt_on_upload'  => true,                         // write alt/title on new uploads.
			'auto_resize_upload'  => false,                        // resize/compress on upload.
			'god_fix_batch'       => 15,                           // items per God Fix batch.
			'autopilot_enabled'   => false,                        // automatically fix issues in background.
			'scan_post_types'     => array( 'post', 'page' ),
		);
	}

	/**
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored     = get_option( VMIA_OPTION, array() );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * @param string $key
	 * @param mixed  $fallback
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$all = self::all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $fallback;
	}

	/**
	 * Persist a partial/whole settings array (merged over current).
	 *
	 * @param array $patch
	 */
	public static function update( array $patch ) {
		$current      = self::all();
		$merged       = array_merge( $current, $patch );
		self::$cache  = $merged;
		update_option( VMIA_OPTION, $merged, false );
	}

	/**
	 * True if at least one text provider is usable.
	 *
	 * @return bool
	 */
	public static function has_text_ai() {
		return self::get( 'aipuffer_key' ) || self::get( 'gemini_key' ) || self::get( 'openrouter_key' );
	}
}
