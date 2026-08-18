<?php
/**
 * Cloudflare Workers AI image provider.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Provider_Cloudflare {

	/**
	 * @param string $prompt
	 * @param array  $args
	 * @return array
	 */
	public function generate( $prompt, $args ) {
		$account_id = VMIA_Settings::get( 'cloudflare_account_id' );
		$key        = VMIA_Settings::get( 'cloudflare_key' );
		$model      = VMIA_Model_Sync::resolve_cloudflare_model();

		if ( ! $account_id || ! $key ) {
			return array( 'ok' => false, 'provider' => 'cloudflare', 'error' => 'Cloudflare credentials not set' );
		}

		$url  = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/ai/run/{$model}";
		$resp = VMIA_HTTP::post(
			$url,
			array( 'prompt' => $prompt ),
			array( 'Authorization' => 'Bearer ' . $key ),
			90
		);

		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'provider' => 'cloudflare', 'error' => $resp->get_error_message() );
		}

		$type = wp_remote_retrieve_header( $resp, 'content-type' );
		$img  = wp_remote_retrieve_body( $resp );

		if ( strpos( $type, 'application/json' ) !== false ) {
			$data = json_decode( $img, true );
			$err  = $data['error']['message'] ?? ( $data['message'] ?? 'Unknown Cloudflare error' );
			return array( 'ok' => false, 'provider' => 'cloudflare', 'error' => $err );
		}

		if ( strlen( $img ) < 100 ) {
			return array( 'ok' => false, 'provider' => 'cloudflare', 'error' => 'Invalid image data returned' );
		}

		$file = wp_tempnam( 'vmia-cf.png' );
		file_put_contents( $file, $img );

		return array(
			'ok'       => true,
			'path'     => $file,
			'provider' => 'cloudflare',
		);
	}
}
