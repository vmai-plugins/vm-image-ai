<?php
/**
 * Pollinations image provider — free, no API key. Returns a direct image URL.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Provider_Pollinations {

	/**
	 * @param string $prompt
	 * @param array  $args { width:int, height:int, seed?:int }
	 * @return array { ok:bool, url?:string, provider:string, error?:string }
	 */
	public function generate( $prompt, $args ) {
		$model  = VMIA_Settings::get( 'pollinations_model', 'flux' );
		$nologo = VMIA_Settings::get( 'pollinations_nologo', true ) ? 'true' : 'false';
		$w      = (int) ( $args['width'] ?? 1200 );
		$h      = (int) ( $args['height'] ?? 630 );
		$seed   = isset( $args['seed'] ) ? (int) $args['seed'] : wp_rand( 1, 999999 );

		$url = 'https://image.pollinations.ai/prompt/' . rawurlencode( $prompt )
			. '?' . http_build_query(
				array(
					'width'   => $w,
					'height'  => $h,
					'seed'    => $seed,
					'model'   => $model,
					'nologo'  => $nologo,
					'enhance' => 'true',
				)
			);

		// Warm the URL; Pollinations renders on first GET.
		$resp = wp_remote_get( $url, array( 'timeout' => 90 ) );
		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'provider' => 'pollinations', 'error' => $resp->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $resp );
		if ( $code < 200 || $code >= 300 ) {
			return array( 'ok' => false, 'provider' => 'pollinations', 'error' => 'HTTP ' . $code );
		}
		return array( 'ok' => true, 'url' => $url, 'provider' => 'pollinations' );
	}
}
