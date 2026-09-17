<?php
/**
 * Vision captioning. Describes an actual image so SEO copy is accurate rather
 * than guessed from the filename. Chain: gemini -> openrouter (vision).
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Vision {

	/**
	 * Return a plain-language description of an image file, or '' on failure.
	 *
	 * @param string $path Absolute image path.
	 * @param string $hint Extra context (post title etc.).
	 * @return string
	 */
	public function describe( $path, $hint = '' ) {
		if ( ! is_readable( $path ) ) {
			return '';
		}

		$temp_path = '';
		$load_path = $path;

		// If the file is over 1MB or larger than 1200px, downsample for vision model to save memory & payload.
		if ( filesize( $path ) > 1024 * 1024 && function_exists( 'wp_get_image_editor' ) ) {
			$editor = wp_get_image_editor( $path );
			if ( ! is_wp_error( $editor ) ) {
				$size = $editor->get_size();
				if ( ( $size['width'] ?? 0 ) > 1024 || ( $size['height'] ?? 0 ) > 1024 ) {
					$editor->resize( 1024, 1024, false );
					$editor->set_quality( 75 );
					$temp_file = wp_tempnam( 'vmia_vis_' );
					$saved = $editor->save( $temp_file, 'image/jpeg' );
					if ( ! is_wp_error( $saved ) && ! empty( $saved['path'] ) ) {
						$load_path = $saved['path'];
						$temp_path = $load_path;
					}
				}
			}
		}

		$mime = wp_check_filetype( $load_path )['type'] ?? 'image/jpeg';
		$b64  = base64_encode( (string) file_get_contents( $load_path ) ); // phpcs:ignore

		if ( $temp_path && file_exists( $temp_path ) ) {
			@unlink( $temp_path );
		}

		$instruction = 'Describe the visible subject of this image in one factual sentence (max 20 words). '
			. 'Focus on concrete objects, people, setting and mood. No preamble.'
			. ( $hint ? ' Page context: ' . $hint : '' );

		foreach ( (array) VMIA_Settings::get( 'vision_order' ) as $provider ) {
			if ( 'openai' === $provider ) {
				$text = $this->openai( $b64, $mime, $instruction );
			} elseif ( 'gemini' === $provider ) {
				$text = $this->gemini( $b64, $mime, $instruction );
			} elseif ( 'openrouter' === $provider ) {
				$text = $this->openrouter( $b64, $mime, $instruction );
			} elseif ( 'omniroute' === $provider ) {
				$text = $this->omniroute( $b64, $mime, $instruction );
			} else {
				$text = '';
			}
			if ( '' !== trim( $text ) ) {
				return trim( $text );
			}
		}
		return '';
	}

	protected function openai( $b64, $mime, $instruction ) {
		$key = VMIA_Settings::get( 'openai_key' );
		if ( ! $key ) {
			return '';
		}
		$model = VMIA_Settings::get( 'openai_model', 'gpt-4o-mini' );
		$body  = array(
			'model'      => $model,
			'max_tokens' => 90,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => array(
						array( 'type' => 'text', 'text' => $instruction ),
						array( 'type' => 'image_url', 'image_url' => array( 'url' => "data:{$mime};base64,{$b64}" ) ),
					),
				),
			),
		);
		$resp = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		) );
		if ( is_wp_error( $resp ) ) {
			return '';
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		return (string) ( $data['choices'][0]['message']['content'] ?? '' );
	}

	protected function gemini( $b64, $mime, $instruction ) {
		$key = VMIA_Settings::get( 'gemini_key' );
		if ( ! $key ) {
			return '';
		}
		$model = VMIA_Settings::get( 'gemini_vision_model', 'gemini-2.0-flash' );
		$url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
		$body  = array(
			'contents' => array(
				array(
					'parts' => array(
						array( 'text' => $instruction ),
						array( 'inline_data' => array( 'mime_type' => $mime, 'data' => $b64 ) ),
					),
				),
			),
			'generationConfig' => array( 'temperature' => 0.3, 'maxOutputTokens' => 80 ),
		);
		$resp = wp_remote_post( $url, array(
			'timeout' => 45,
			'headers' => array(
				'Content-Type'   => 'application/json',
				'x-goog-api-key' => $key,
			),
			'body'    => wp_json_encode( $body ),
		) );
		if ( is_wp_error( $resp ) ) {
			return '';
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		return (string) ( $data['candidates'][0]['content']['parts'][0]['text'] ?? '' );
	}

	protected function openrouter( $b64, $mime, $instruction ) {
		$key = VMIA_Settings::get( 'openrouter_key' );
		if ( ! $key ) {
			return '';
		}
		$model = VMIA_Model_Sync::resolve_vision_model();
		$body  = array(
			'model'    => $model,
			'max_tokens' => 90,
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => array(
						array( 'type' => 'text', 'text' => $instruction ),
						array( 'type' => 'image_url', 'image_url' => array( 'url' => "data:{$mime};base64,{$b64}" ) ),
					),
				),
			),
		);
		$resp = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', array(
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'application/json',
				'HTTP-Referer'  => home_url(),
				'X-Title'       => 'VM Image AI',
			),
			'body'    => wp_json_encode( $body ),
		) );
		if ( is_wp_error( $resp ) ) {
			return '';
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		return (string) ( $data['choices'][0]['message']['content'] ?? '' );
	}

	protected function omniroute( $b64, $mime, $instruction ) {
		$base = rtrim( (string) VMIA_Settings::get( 'omniroute_base' ), '/' );
		$key  = VMIA_Settings::get( 'omniroute_key' );
		if ( ! $base ) {
			return '';
		}
		$model = VMIA_Settings::get( 'omniroute_vision_model', 'openai/gpt-4o-mini' );
		$body  = array(
			'model'      => $model,
			'max_tokens' => 90,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => array(
						array( 'type' => 'text', 'text' => $instruction ),
						array( 'type' => 'image_url', 'image_url' => array( 'url' => "data:{$mime};base64,{$b64}" ) ),
					),
				),
			),
		);
		$headers = array( 'Content-Type' => 'application/json' );
		if ( $key ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}

		$resp = wp_remote_post( $base . '/chat/completions', array(
			'timeout' => 60,
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
		) );
		if ( is_wp_error( $resp ) ) {
			return '';
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		return (string) ( $data['choices'][0]['message']['content'] ?? '' );
	}
}
