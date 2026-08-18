<?php
/**
 * Hugging Face image provider.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Provider_Huggingface {

	/**
	 * @param string $prompt
	 * @param array  $args
	 * @return array
	 */
	public function generate( $prompt, $args ) {
		$key   = VMIA_Settings::get( 'huggingface_key' );
		$model = VMIA_Model_Sync::resolve_huggingface_model();

		if ( ! $key ) {
			return array( 'ok' => false, 'provider' => 'huggingface', 'error' => 'Hugging Face API key not set' );
		}

		$url  = "https://api-inference.huggingface.co/models/{$model}";
		$resp = VMIA_HTTP::post(
			$url,
			array( 'inputs' => $prompt ),
			array( 'Authorization' => 'Bearer ' . $key ),
			90
		);

		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'provider' => 'huggingface', 'error' => $resp->get_error_message() );
		}

		$type = wp_remote_retrieve_header( $resp, 'content-type' );
		$img  = wp_remote_retrieve_body( $resp );

		if ( strpos( $type, 'application/json' ) !== false ) {
			$data = json_decode( $img, true );
			$err  = $data['error'] ?? ( $data['message'] ?? 'Unknown Hugging Face error' );
			if ( is_array( $err ) ) $err = $err['message'] ?? wp_json_encode( $err );
			return array( 'ok' => false, 'provider' => 'huggingface', 'error' => $err );
		}

		if ( strlen( $img ) < 100 ) {
			return array( 'ok' => false, 'provider' => 'huggingface', 'error' => 'Invalid image data returned' );
		}

		$file = wp_tempnam( 'vmia-hf.png' );
		file_put_contents( $file, $img );

		return array(
			'ok'       => true,
			'path'     => $file,
			'provider' => 'huggingface',
		);
	}
}
