<?php
/**
 * OmniRoute provider.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Provider_Omniroute {

	/**
	 * @param string $prompt
	 * @param array  $args
	 * @return array
	 */
	public function generate( $prompt, $args ) {
		$base  = rtrim( (string) VMIA_Settings::get( 'omniroute_base' ), '/' );
		$key   = VMIA_Settings::get( 'omniroute_key' );
		$model = VMIA_Settings::get( 'omniroute_image_model', 'cheaperinference/grok-imagine' );

		if ( ! $base ) {
			return array( 'ok' => false, 'provider' => 'omniroute', 'error' => 'OmniRoute base URL not set' );
		}

		$w = (int) ( $args['width'] ?? 1024 );
		$h = (int) ( $args['height'] ?? 1024 );
		$size = "{$w}x{$h}";

		$url  = $base . "/images/generations";
		$body = array(
			'model'  => $model,
			'prompt' => $prompt,
			'n'      => 1,
			'size'   => $size,
		);

		$headers = array(
			'Accept' => 'application/json',
		);
		if ( $key ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}

		$data = VMIA_HTTP::post_json( $url, $body, $headers, 120 );

		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'provider' => 'omniroute', 'error' => $data->get_error_message() );
		}

		$img_url = $data['data'][0]['url'] ?? '';
		if ( $img_url ) {
			return array( 'ok' => true, 'url' => $img_url, 'provider' => 'omniroute' );
		}

		return array( 'ok' => false, 'provider' => 'omniroute', 'error' => 'No image URL in response' );
	}

	/**
	 * @param string $prompt
	 * @param array  $args
	 * @return array
	 */
	public function generate_video( $prompt, $args ) {
		$base  = rtrim( (string) VMIA_Settings::get( 'omniroute_base' ), '/' );
		$key   = VMIA_Settings::get( 'omniroute_key' );
		$model = VMIA_Settings::get( 'omniroute_video_model', 'novita/video-model-name' );

		if ( ! $base ) {
			return array( 'ok' => false, 'provider' => 'omniroute', 'error' => 'OmniRoute base URL not set' );
		}

		// Some OpenAI-compatible gateways use images/generations for video too,
		// or video/generations. We'll try images/generations first as it's the most common "OpenAI-compatible" media endpoint.
		$url  = $base . "/images/generations";
		$body = array(
			'model'  => $model,
			'prompt' => $prompt,
			'n'      => 1,
		);

		$headers = array(
			'Accept' => 'application/json',
		);
		if ( $key ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}

		$data = VMIA_HTTP::post_json( $url, $body, $headers, 180 ); // Videos take longer

		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'provider' => 'omniroute', 'error' => $data->get_error_message() );
		}

		$video_url = $data['data'][0]['url'] ?? '';
		if ( $video_url ) {
			return array( 'ok' => true, 'url' => $video_url, 'provider' => 'omniroute' );
		}

		return array( 'ok' => false, 'provider' => 'omniroute', 'error' => 'No video URL in response' );
	}
}
