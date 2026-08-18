<?php
/**
 * Google Search image provider — stock photo fallback.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Provider_Google_Search {

	/**
	 * @param string $prompt Treated as a search query.
	 * @param array  $args
	 * @return array
	 */
	public function generate( $prompt, $args ) {
		$key = VMIA_Settings::get( 'google_search_key' );
		$cx  = VMIA_Settings::get( 'google_search_cx' );

		if ( ! $key || ! $cx ) {
			return array( 'ok' => false, 'provider' => 'google_search', 'error' => 'Google Search API key or CX not set' );
		}

		$query = $this->to_query( $prompt );
		$url   = 'https://www.googleapis.com/customsearch/v1?' . http_build_query(
			array(
				'key'        => $key,
				'cx'         => $cx,
				'q'          => $query,
				'searchType' => 'image',
				'num'        => 5,
				'imgSize'    => 'large',
				'safe'       => 'active'
			)
		);

		$resp = wp_remote_get( $url, array( 'timeout' => 30 ) );
		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'provider' => 'google_search', 'error' => $resp->get_error_message() );
		}

		$data   = json_decode( wp_remote_retrieve_body( $resp ), true );
		$items  = $data['items'] ?? array();
		if ( ! $items ) {
			return array( 'ok' => false, 'provider' => 'google_search', 'error' => 'no results for "' . $query . '"' );
		}

		$pick = $items[ array_rand( $items ) ];
		$src  = $pick['link'] ?? '';
		if ( ! $src ) {
			return array( 'ok' => false, 'provider' => 'google_search', 'error' => 'no usable link' );
		}

		return array(
			'ok'       => true,
			'url'      => $src,
			'provider' => 'google_search',
			'credit'   => sprintf( 'Image from %s via Google Search', $pick['displayLink'] ?? 'Google' ),
		);
	}

	/**
	 * Reduce a rich generation prompt to a short search query.
	 */
	protected function to_query( $prompt ) {
		$prompt = wp_strip_all_tags( $prompt );
		$words  = preg_split( '/\s+/', $prompt );
		$words  = array_slice( array_filter( $words ), 0, 8 );
		return trim( implode( ' ', $words ) );
	}
}
