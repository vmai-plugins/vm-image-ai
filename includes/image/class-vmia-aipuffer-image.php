<?php
/**
 * AI Puffer (AIPKit) image provider. AIPKit exposes image generation on this
 * site; the exact route can vary by build, so the sub-path is configurable
 * (defaults to {aipuffer_base}/image). Accepts either a URL or base64 payload.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Provider_Aipuffer {

	/**
	 * @param string $prompt
	 * @param array  $args
	 * @return array
	 */
	public function generate( $prompt, $args ) {
		$key    = VMIA_Settings::get( 'aipuffer_key' );
		$base   = rtrim( (string) VMIA_Settings::get( 'aipuffer_base' ), '/' );
		$path   = '/' . ltrim( (string) VMIA_Settings::get( 'aipuffer_img_path', '/image' ), '/' );
		$bot_id = (string) VMIA_Settings::get( 'aipuffer_bot_id' );
		if ( ! $key || ! $base ) {
			return array( 'ok' => false, 'provider' => 'aipuffer', 'error' => 'AI Puffer not configured' );
		}

		$w      = (int) ( $args['width'] ?? 1024 );
		$h      = (int) ( $args['height'] ?? 1024 );
		$body   = array(
			'prompt'   => $prompt,
			'provider' => VMIA_Settings::get( 'aipuffer_img_engine', 'openai' ),
			'width'    => $w,
			'height'   => $h,
			'size'     => $w . 'x' . $h,
			'n'        => 1,
		);
		// Same reasoning as the text provider: only target a specific bot
		// when one is configured, since AIPKit can host several.
		if ( '' !== $bot_id ) {
			$body['bot_id'] = $bot_id;
		}

		$resp = wp_remote_post(
			$base . $path,
			array(
				'timeout' => 90,
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'provider' => 'aipuffer', 'error' => $resp->get_error_message() );
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );

		// Tolerate several response shapes.
		$url = $data['url']
			?? ( $data['data'][0]['url']
			?? ( $data['image_url']
			?? ( $data['images'][0] ?? '' ) ) );
		if ( $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return array( 'ok' => true, 'url' => $url, 'provider' => 'aipuffer' );
		}

		$b64 = $data['b64_json'] ?? ( $data['data'][0]['b64_json'] ?? ( $data['image'] ?? '' ) );
		if ( $b64 ) {
			$b64  = preg_replace( '#^data:image/\w+;base64,#', '', $b64 );
			$file = wp_tempnam( 'vmia-aipuffer.png' );
			file_put_contents( $file, base64_decode( $b64 ) ); // phpcs:ignore
			return array( 'ok' => true, 'path' => $file, 'provider' => 'aipuffer' );
		}

		return array( 'ok' => false, 'provider' => 'aipuffer', 'error' => 'no image in response (HTTP ' . wp_remote_retrieve_response_code( $resp ) . ')' );
	}
}
