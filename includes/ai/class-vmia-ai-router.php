<?php
/**
 * Text AI router. Tries providers in the configured order; first success wins.
 *
 * Providers:
 *   - aipuffer   : AIPKit REST on this site ({base}/generate, Bearer key) -> `content`
 *   - gemini     : Google Generative Language API
 *   - openrouter : OpenRouter chat completions (model resolved via live sync)
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_AI_Router {

	/**
	 * Generate text from a prompt using the fallback chain.
	 *
	 * @param string $prompt
	 * @param array  $args  { system?:string, max_tokens?:int, temperature?:float, json?:bool }
	 * @return array { ok:bool, text:string, provider:string, error?:string }
	 */
	public function complete( $prompt, $args = array() ) {
		$brand  = VMIA_Settings::get( 'brand_context' );
		$system = 'You are an expert Image-SEO copywriter. Reply concisely and never add commentary.';
		if ( $brand ) {
			$system .= ' Brand/Style Guidelines: ' . $brand;
		}

		$args  = wp_parse_args(
			$args,
			array(
				'system'      => $system,
				'max_tokens'  => 512,
				'temperature' => 0.5,
				'json'        => false,
			)
		);
		$order = (array) VMIA_Settings::get( 'ai_order' );
		$last  = 'no provider configured';

		foreach ( $order as $provider ) {
			$method = 'call_' . $provider;
			if ( ! method_exists( $this, $method ) ) {
				continue;
			}
			$res = $this->$method( $prompt, $args );
			if ( ! empty( $res['ok'] ) && '' !== trim( (string) $res['text'] ) ) {
				return $res;
			}
			$last = isset( $res['error'] ) ? $res['error'] : ( $provider . ' returned empty' );
		}

		VMIA_Logger::add( 'error', 0, 'ai', 'All text providers failed: ' . $last );
		return array( 'ok' => false, 'text' => '', 'provider' => '', 'error' => $last );
	}

	/**
	 * Ask for JSON and decode it defensively.
	 *
	 * @param string $prompt
	 * @param array  $args
	 * @return array|null
	 */
	public function complete_json( $prompt, $args = array() ) {
		$args['json'] = true;
		$res          = $this->complete( $prompt, $args );
		if ( empty( $res['ok'] ) ) {
			return null;
		}
		return self::extract_json( $res['text'] );
	}

	/* --------------------------------------------------------------------- */
	/* Providers                                                             */
	/* --------------------------------------------------------------------- */

	protected function call_openai( $prompt, $args ) {
		$key = VMIA_Settings::get( 'openai_key' );
		if ( ! $key ) {
			return array( 'ok' => false, 'error' => 'openai not configured' );
		}
		$model = VMIA_Settings::get( 'openai_model', 'gpt-4o-mini' );

		$body = array(
			'model'       => $model,
			'temperature' => (float) $args['temperature'],
			'max_tokens'  => (int) $args['max_tokens'],
			'messages'    => array(
				array( 'role' => 'system', 'content' => $args['system'] ),
				array( 'role' => 'user', 'content' => $prompt ),
			),
		);
		if ( ! empty( $args['json'] ) ) {
			$body['response_format'] = array( 'type' => 'json_object' );
		}

		$data = VMIA_HTTP::post_json(
			'https://api.openai.com/v1/chat/completions',
			$body,
			array( 'Authorization' => 'Bearer ' . $key )
		);

		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'error' => 'openai: ' . $data->get_error_message() );
		}

		$text = $data['choices'][0]['message']['content'] ?? '';
		if ( '' === trim( (string) $text ) ) {
			return array( 'ok' => false, 'error' => 'openai: empty response' );
		}
		return array( 'ok' => true, 'text' => (string) $text, 'provider' => 'openai' );
	}

	protected function call_aipuffer( $prompt, $args ) {
		$key    = VMIA_Settings::get( 'aipuffer_key' );
		$base   = rtrim( (string) VMIA_Settings::get( 'aipuffer_base' ), '/' );
		$bot_id = (string) VMIA_Settings::get( 'aipuffer_bot_id' );
		if ( ! $key || ! $base ) {
			return array( 'ok' => false, 'error' => 'aipuffer not configured' );
		}

		// AIPKit public REST Text Handler expects:
		// provider (openai, azure, etc), model, messages, system_instruction
		$body = array(
			'provider'           => 'openai',
			'model'              => 'gpt-4o-mini',
			'messages'           => array(
				array( 'role' => 'user', 'content' => $prompt ),
			),
			'system_instruction' => $args['system'],
			'ai_params'          => array(
				'max_completion_tokens' => (int) $args['max_tokens'],
				'temperature'           => (float) $args['temperature'],
			),
		);

		if ( '' !== $bot_id ) {
			$body['bot_id'] = $bot_id;
		}

		$data = VMIA_HTTP::post_json(
			$base . '/generate',
			$body,
			array( 'Authorization' => 'Bearer ' . $key ),
			45
		);

		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'error' => 'aipuffer: ' . $data->get_error_message() );
		}

		$text = $data['content'] ?? '';
		if ( '' === trim( (string) $text ) ) {
			return array( 'ok' => false, 'error' => 'aipuffer: empty response' );
		}
		return array( 'ok' => true, 'text' => (string) $text, 'provider' => 'aipuffer' );
	}

	protected function call_gemini( $prompt, $args ) {
		$key = VMIA_Settings::get( 'gemini_key' );
		if ( ! $key ) {
			return array( 'ok' => false, 'error' => 'gemini not configured' );
		}
		$model = VMIA_Settings::get( 'gemini_model', 'gemini-2.0-flash' );
		$url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . rawurlencode( $key );

		$body = array(
			'system_instruction' => array( 'parts' => array( array( 'text' => $args['system'] ) ) ),
			'contents'           => array(
				array( 'parts' => array( array( 'text' => $prompt ) ) ),
			),
			'generationConfig'   => array(
				'temperature'     => (float) $args['temperature'],
				'maxOutputTokens' => (int) $args['max_tokens'],
			),
		);
		if ( ! empty( $args['json'] ) ) {
			$body['generationConfig']['responseMimeType'] = 'application/json';
		}

		$data = VMIA_HTTP::post_json( $url, $body );

		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'error' => 'gemini: ' . $data->get_error_message() );
		}

		$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
		if ( '' === trim( (string) $text ) ) {
			return array( 'ok' => false, 'error' => 'gemini: empty response' );
		}
		return array( 'ok' => true, 'text' => (string) $text, 'provider' => 'gemini' );
	}

	protected function call_openrouter( $prompt, $args ) {
		$key = VMIA_Settings::get( 'openrouter_key' );
		if ( ! $key ) {
			return array( 'ok' => false, 'error' => 'openrouter not configured' );
		}
		$model = VMIA_Model_Sync::resolve_text_model();

		$body = array(
			'model'       => $model,
			'temperature' => (float) $args['temperature'],
			'max_tokens'  => (int) $args['max_tokens'],
			'messages'    => array(
				array( 'role' => 'system', 'content' => $args['system'] ),
				array( 'role' => 'user', 'content' => $prompt ),
			),
		);
		if ( ! empty( $args['json'] ) ) {
			$body['response_format'] = array( 'type' => 'json_object' );
		}

		$data = VMIA_HTTP::post_json(
			'https://openrouter.ai/api/v1/chat/completions',
			$body,
			array(
				'Authorization' => 'Bearer ' . $key,
				'HTTP-Referer'  => home_url(),
				'X-Title'       => 'VM Image AI',
			)
		);

		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'error' => 'openrouter: ' . $data->get_error_message() );
		}

		$text = $data['choices'][0]['message']['content'] ?? '';
		if ( '' === trim( (string) $text ) ) {
			return array( 'ok' => false, 'error' => 'openrouter: empty response' );
		}
		return array( 'ok' => true, 'text' => (string) $text, 'provider' => 'openrouter' );
	}

	/* --------------------------------------------------------------------- */
	/* Helpers                                                               */
	/* --------------------------------------------------------------------- */

	/**
	 * Pull the first JSON object/array out of a model response.
	 *
	 * @param string $raw
	 * @return array|null
	 */
	public static function extract_json( $raw ) {
		$raw = trim( (string) $raw );
		$raw = preg_replace( '/^```(json)?|```$/m', '', $raw );
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}
		// Fallback: grab first {...} or [...] block.
		if ( preg_match( '/(\{.*\}|\[.*\])/s', $raw, $m ) ) {
			$decoded = json_decode( $m[1], true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}
		return null;
	}
}
