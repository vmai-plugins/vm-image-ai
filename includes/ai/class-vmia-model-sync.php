<?php
/**
 * Live model sync. Pulls current model catalogues so "auto" always resolves to
 * a model that actually exists on the provider today (fallback resilience).
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Model_Sync {

	const TRANSIENT = 'vmia_model_catalogue';

	/**
	 * Preference order for auto-picking a free/cheap capable text model on OpenRouter.
	 *
	 * @var string[]
	 */
	protected static $text_pref = array(
		'meta-llama/llama-3.3-70b-instruct:free',
		'deepseek/deepseek-chat-v3:free',
		'google/gemini-2.0-flash-exp:free',
		'qwen/qwen-2.5-72b-instruct:free',
		'mistralai/mistral-nemo:free',
		'openai/gpt-4o-mini',
	);

	/** @var string[] */
	protected static $vision_pref = array(
		'google/gemini-2.0-flash-exp:free',
		'qwen/qwen2.5-vl-72b-instruct:free',
		'meta-llama/llama-3.2-11b-vision-instruct:free',
		'openai/gpt-4o-mini',
	);

	/** @var string[] */
	protected static $hf_image_pref = array(
		'black-forest-labs/FLUX.1-schnell',
		'stabilityai/stable-diffusion-3.5-large',
		'stabilityai/stable-diffusion-xl-base-1.0',
	);

	/** @var string[] */
	protected static $cf_image_pref = array(
		'@cf/black-forest-labs/flux-1-schnell',
		'@cf/stabilityai/stable-diffusion-xl-base-1.0',
	);

	/**
	 * Cron/manual refresh of the model catalogue.
	 *
	 * @return array
	 */
	public static function refresh() {
		$catalogue = array(
			'openai'       => array(),
			'openrouter'   => array(),
			'huggingface'  => array(),
			'cloudflare'   => array(),
			'gemini'       => array(),
			'xai'          => array(),
			'aipuffer'     => array(),
			'synced_at'    => current_time( 'mysql' )
		);

		// 0. OpenAI (including DALL-E)
		$oa_key = VMIA_Settings::get( 'openai_key' );
		if ( $oa_key ) {
			$resp = VMIA_HTTP::post_json( 'https://api.openai.com/v1/models', null, array( 'Authorization' => 'Bearer ' . $oa_key ), 20, 'GET' );
			if ( ! is_wp_error( $resp ) && ! empty( $resp['data'] ) ) {
				$catalogue['openai'] = wp_list_pluck( $resp['data'], 'id' );
			}
		}

		// 1. OpenRouter
		$key = VMIA_Settings::get( 'openrouter_key' );
		if ( $key ) {
			$resp = VMIA_HTTP::post_json( 'https://openrouter.ai/api/v1/models', null, array( 'Authorization' => 'Bearer ' . $key ), 30, 'GET' );
			if ( ! is_wp_error( $resp ) && ! empty( $resp['data'] ) ) {
				$catalogue['openrouter'] = wp_list_pluck( $resp['data'], 'id' );
			}
		}

		// 2. Hugging Face (Image models)
		$hf_key = VMIA_Settings::get( 'huggingface_key' );
		if ( $hf_key ) {
			$resp = VMIA_HTTP::post_json( 'https://huggingface.co/api/models?pipeline_tag=text-to-image&sort=downloads&direction=-1&limit=20', null, array( 'Authorization' => 'Bearer ' . $hf_key ), 20, 'GET' );
			if ( ! is_wp_error( $resp ) && is_array( $resp ) ) {
				$catalogue['huggingface'] = wp_list_pluck( $resp, 'modelId' );
			}
		}

		// 3. Cloudflare Workers AI
		$cf_id = VMIA_Settings::get( 'cloudflare_account_id' );
		$cf_key = VMIA_Settings::get( 'cloudflare_key' );
		if ( $cf_id && $cf_key ) {
			$resp = VMIA_HTTP::post_json( "https://api.cloudflare.com/client/v4/accounts/{$cf_id}/ai/models/search?task=Text-to-Image", null, array( 'Authorization' => 'Bearer ' . $cf_key ), 20, 'GET' );
			if ( ! is_wp_error( $resp ) && ! empty( $resp['result'] ) ) {
				$catalogue['cloudflare'] = wp_list_pluck( $resp['result'], 'name' );
			}
		}

		// 4. Gemini
		$gemini_key = VMIA_Settings::get( 'gemini_key' );
		if ( $gemini_key ) {
			$resp = VMIA_HTTP::post_json( "https://generativelanguage.googleapis.com/v1beta/models?key=" . rawurlencode( $gemini_key ), null, array(), 20, 'GET' );
			if ( ! is_wp_error( $resp ) && ! empty( $resp['models'] ) ) {
				$catalogue['gemini'] = wp_list_pluck( $resp['models'], 'name' );
				$catalogue['gemini'] = array_map( function( $n ) { return str_replace( 'models/', '', $n ); }, $catalogue['gemini'] );
			}
		}

		// 5. xAI (Grok)
		$xai_key = VMIA_Settings::get( 'xai_key' );
		if ( $xai_key ) {
			$resp = VMIA_HTTP::post_json( 'https://api.x.ai/v1/models', null, array( 'Authorization' => 'Bearer ' . $xai_key ), 20, 'GET' );
			if ( ! is_wp_error( $resp ) && ! empty( $resp['data'] ) ) {
				$catalogue['xai'] = wp_list_pluck( $resp['data'], 'id' );
			}
		}

		// 6. AI Puffer (Bot Sync)
		$ap_base = VMIA_Settings::get( 'aipuffer_base' );
		$ap_key  = VMIA_Settings::get( 'aipuffer_key' );
		$ap_res  = VMIA_Aipuffer_Client::list_bots( $ap_base, $ap_key );
		if ( ! empty( $ap_res['ok'] ) ) {
			$catalogue['aipuffer'] = $ap_res['bots'];
		}

		set_transient( self::TRANSIENT, $catalogue, 7 * DAY_IN_SECONDS );

		VMIA_Logger::add( 'ai', 0, 'system', 'Live model sync complete.' );
		return $catalogue;
	}

	/**
	 * @return array
	 */
	public static function catalogue() {
		$cat = get_transient( self::TRANSIENT );
		return is_array( $cat ) ? $cat : array();
	}

	public static function resolve_text_model() {
		$set = VMIA_Settings::get( 'openrouter_model', 'auto' );
		if ( $set && 'auto' !== $set ) return $set;
		return self::pick( 'openrouter', self::$text_pref, 'meta-llama/llama-3.3-70b-instruct:free' );
	}

	public static function resolve_vision_model() {
		$set = VMIA_Settings::get( 'openrouter_vision', 'auto' );
		if ( $set && 'auto' !== $set ) return $set;
		return self::pick( 'openrouter', self::$vision_pref, 'google/gemini-2.0-flash-exp:free' );
	}

	public static function resolve_huggingface_model() {
		$set = VMIA_Settings::get( 'huggingface_model' );
		if ( $set && ( 'auto' !== $set && '' !== $set ) ) return $set;
		return self::pick( 'huggingface', self::$hf_image_pref, 'black-forest-labs/FLUX.1-schnell' );
	}

	public static function resolve_cloudflare_model() {
		$set = VMIA_Settings::get( 'cloudflare_model' );
		if ( $set && ( 'auto' !== $set && '' !== $set ) ) return $set;
		return self::pick( 'cloudflare', self::$cf_image_pref, '@cf/black-forest-labs/flux-1-schnell' );
	}

	public static function resolve_google_model() {
		$set = VMIA_Settings::get( 'gemini_image_model' );
		if ( $set && ( 'auto' !== $set && '' !== $set ) ) return $set;
		$cat = self::catalogue();
		$available = $cat['gemini'] ?? array();
		if ( ! is_array( $available ) ) $available = array();

		// Look for any imagen model in the list.
		foreach ( $available as $model ) {
			if ( stripos( (string) $model, 'imagen' ) !== false ) return (string) $model;
		}
		return 'imagen-3.0-generate-001';
	}

	/**
	 * First preference present in the live catalogue, else first preference.
	 */
	protected static function pick( $provider, $prefs, $default ) {
		$available = self::catalogue()[ $provider ] ?? array();
		if ( $available ) {
			foreach ( $prefs as $model ) {
				if ( in_array( $model, $available, true ) ) {
					return $model;
				}
			}
		}
		return $prefs[0] ?? $default;
	}
}
