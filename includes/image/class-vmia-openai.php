<?php
/**
 * OpenAI DALL-E provider.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Provider_Openai {

	/**
	 * @param string $prompt
	 * @param array  $args
	 * @return array
	 */
	public function generate( $prompt, $args ) {
		$key   = VMIA_Settings::get( 'openai_key' );
		$model = VMIA_Settings::get( 'openai_image_model', 'dall-e-3' );

		if ( ! $key ) {
			return array( 'ok' => false, 'provider' => 'openai', 'error' => 'OpenAI API key not set' );
		}

		$w = (int) ( $args['width'] ?? 1024 );
		$h = (int) ( $args['height'] ?? 1024 );

		// Intelligent size mapping for DALL-E 3
		if ( strpos( $model, 'dall-e-3' ) !== false ) {
			$ratio = $w / $h;
			if ( $ratio > 1.3 ) {
				$size = '1792x1024';
			} elseif ( $ratio < 0.7 ) {
				$size = '1024x1792';
			} else {
				$size = '1024x1024';
			}
		} else {
			// DALL-E 2
			$size = '1024x1024';
		}

		$url  = "https://api.openai.com/v1/images/generations";
		$body = array(
			'model'  => $model,
			'prompt' => $prompt,
			'n'      => 1,
			'size'   => $size,
		);

		$data = VMIA_HTTP::post_json( $url, $body, array( 'Authorization' => 'Bearer ' . $key ), 90 );

		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'provider' => 'openai', 'error' => $data->get_error_message() );
		}

		$img_url = $data['data'][0]['url'] ?? '';
		if ( $img_url ) {
			return array( 'ok' => true, 'url' => $img_url, 'provider' => 'openai' );
		}

		return array( 'ok' => false, 'provider' => 'openai', 'error' => 'No image URL in response' );
	}
}
