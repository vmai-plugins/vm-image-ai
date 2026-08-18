<?php
/**
 * Unified HTTP client for AI requests.
 * Handles timeouts, structured error logging, and standard headers.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_HTTP {

	/**
	 * Perform a structured request.
	 *
	 * @param string $url
	 * @param array  $body
	 * @param array  $headers
	 * @param int    $timeout
	 * @param string $method
	 * @return array|WP_Error Decoded JSON or binary body on success.
	 */
	public static function post( $url, $body = null, $headers = array(), $timeout = 60, $method = 'POST' ) {
		$args = array(
			'method'      => $method,
			'timeout'     => $timeout,
			'headers'     => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
			'body'        => is_array( $body ) ? wp_json_encode( $body ) : $body,
			'user-agent'  => 'VM-Image-AI/' . VMIA_VERSION . '; ' . home_url(),
		);

		if ( 'GET' === $method ) {
			unset( $args['body'] );
		}

		$resp = wp_remote_request( $url, $args );

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = wp_remote_retrieve_response_code( $resp );
		$data = wp_remote_retrieve_body( $resp );

		if ( $code >= 400 ) {
			$err = json_decode( $data, true );
			$msg = $err['error']['message'] ?? ( $err['message'] ?? 'HTTP ' . $code );
			return new WP_Error( 'http_' . $code, $msg, array( 'body' => $data ) );
		}

		return $resp;
	}

	/**
	 * Helper to handle JSON responses specifically.
	 */
	public static function post_json( $url, $body = null, $headers = array(), $timeout = 60, $method = 'POST' ) {
		$resp = self::post( $url, $body, $headers, $timeout, $method );
		if ( is_wp_error( $resp ) ) return $resp;

		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'invalid_json', 'Failed to parse JSON response' );
		}
		return $data;
	}
}
