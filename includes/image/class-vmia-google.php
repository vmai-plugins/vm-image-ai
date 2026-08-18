<?php
/**
 * Google Imagen provider (via Gemini API / Google AI Studio).
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Provider_Google {

	/**
	 * @param string $prompt
	 * @param array  $args
	 * @return array
	 */
	public function generate( $prompt, $args ) {
		$key   = VMIA_Settings::get( 'gemini_key' );
		$model = VMIA_Model_Sync::resolve_google_model();

		if ( ! $key ) {
			return array( 'ok' => false, 'provider' => 'google', 'error' => 'Gemini API key not set (required for Google Imagen)' );
		}

		// Google AI Studio Imagen endpoint
		$url  = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:predict?key={$key}";

		$body = array(
			'instances' => array(
				array( 'prompt' => $prompt ),
			),
			'parameters' => array(
				'sampleCount' => 1,
			),
		);

		$data = VMIA_HTTP::post_json( $url, $body, array(), 90 );

		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'provider' => 'google', 'error' => $data->get_error_message() );
		}

		// Shape: { predictions: [ { bytesBase64Encoded: "...", mimeType: "image/png" } ] }
		// or potentially { predictions: [ { url: "..." } ] }
		$prediction = $data['predictions'][0] ?? array();
		$b64        = $prediction['bytesBase64Encoded'] ?? '';
		$url        = $prediction['url'] ?? '';

		if ( $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return array( 'ok' => true, 'url' => $url, 'provider' => 'google' );
		}

		if ( ! $b64 ) {
			return array( 'ok' => false, 'provider' => 'google', 'error' => 'No image data in response' );
		}

		$b64 = preg_replace( '#^data:image/\w+;base64,#', '', $b64 );

		$file = wp_tempnam( 'vmia-google.png' );
		file_put_contents( $file, base64_decode( $b64 ) );

		return array(
			'ok'       => true,
			'path'     => $file,
			'provider' => 'google',
		);
	}
}
