<?php
/**
 * Pexels stock provider — free API key. Used as a "real photo" fallback when
 * generative providers are unavailable or a photographic result is preferred.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Provider_Pexels {

	/**
	 * @param string $prompt Treated as a search query.
	 * @param array  $args
	 * @return array
	 */
	public function generate( $prompt, $args ) {
		$key = VMIA_Settings::get( 'pexels_key' );
		if ( ! $key ) {
			return array( 'ok' => false, 'provider' => 'pexels', 'error' => 'Pexels API key not set' );
		}
		$query = $this->to_query( $prompt );
		$url   = 'https://api.pexels.com/v1/search?' . http_build_query(
			array( 'query' => $query, 'per_page' => 5, 'orientation' => 'landscape' )
		);

		$resp = wp_remote_get( $url, array( 'timeout' => 30, 'headers' => array( 'Authorization' => $key ) ) );
		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'provider' => 'pexels', 'error' => $resp->get_error_message() );
		}
		$data   = json_decode( wp_remote_retrieve_body( $resp ), true );
		$photos = $data['photos'] ?? array();
		if ( ! $photos ) {
			return array( 'ok' => false, 'provider' => 'pexels', 'error' => 'no results for "' . $query . '"' );
		}
		$pick = $photos[ array_rand( $photos ) ];
		$src  = $pick['src']['large2x'] ?? ( $pick['src']['large'] ?? ( $pick['src']['original'] ?? '' ) );
		if ( ! $src ) {
			return array( 'ok' => false, 'provider' => 'pexels', 'error' => 'no usable src' );
		}
		return array(
			'ok'       => true,
			'url'      => $src,
			'provider' => 'pexels',
			'credit'   => sprintf( 'Photo by %s on Pexels', $pick['photographer'] ?? 'Pexels' ),
		);
	}

	/**
	 * Reduce a rich generation prompt to a short stock-search query.
	 */
	protected function to_query( $prompt ) {
		$prompt = wp_strip_all_tags( $prompt );
		$words  = preg_split( '/\s+/', $prompt );
		$words  = array_slice( array_filter( $words ), 0, 6 );
		return trim( implode( ' ', $words ) );
	}
}
