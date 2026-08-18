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
		$mime = wp_check_filetype( $path )['type'] ?? 'image/jpeg';
		$b64  = base64_encode( (string) file_get_contents( $path ) ); // phpcs:ignore
		$instruction = 'Describe the visible subject of this image in one factual sentence (max 20 words). '
			. 'Focus on concrete objects, people, setting and mood. No preamble.'
			. ( $hint ? ' Page context: ' . $hint : '' );

		foreach ( (array) VMIA_Settings::get( 'vision_order' ) as $provider ) {
			$text = 'gemini' === $provider
				? $this->gemini( $b64, $mime, $instruction )
				: ( 'openrouter' === $provider ? $this->openrouter( $b64, $mime, $instruction ) : '' );
			if ( '' !== trim( $text ) ) {
				return trim( $text );
			}
		}
		return '';
	}

	protected function gemini( $b64, $mime, $instruction ) {
		$key = VMIA_Settings::get( 'gemini_key' );
		if ( ! $key ) {
			return '';
		}
		$model = VMIA_Settings::get( 'gemini_vision_model', 'gemini-2.0-flash' );
		$url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . rawurlencode( $key );
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
			'headers' => array( 'Content-Type' => 'application/json' ),
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
}
