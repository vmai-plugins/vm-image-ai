<?php
/**
 * Shared AI Puffer (AIPKit) client — bot discovery + connection testing.
 *
 * AIPKit can host multiple bots on one site, so every real request (text
 * completion, image generation) needs to say *which* bot it's targeting.
 * This class centralises that: listing the bots a key can see, and
 * confirming a configured base/key/bot_id combo actually works, so the
 * admin UI and the two aipuffer providers don't each re-implement it.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Aipuffer_Client {

	/**
	 * List bots visible to the configured key.
	 */
	public static function list_bots( $base, $key ) {
		// 1. Try local detection first if on the same site.
		$local_bots = self::detect_local_bots();
		if ( ! empty( $local_bots ) ) {
			return array(
				'ok'        => true,
				'bots'      => $local_bots,
				'real_base' => rtrim( home_url(), '/' ) . '/wp-json/aipkit/v1',
				'local'     => true,
			);
		}

		$base_in = rtrim( (string) $base, '/' );
		if ( ! $base_in ) {
			return array( 'ok' => false, 'bots' => array(), 'real_base' => $base_in, 'error' => 'AI Puffer base URL not set' );
		}

		// Candidates: direct routes, and standard WP-JSON routes in case the user
		// only provided the site root.
		$candidates = array(
			array( 'base' => $base_in, 'route' => '/bots' ),
			array( 'base' => $base_in, 'route' => '/models' ),
			array( 'base' => $base_in, 'route' => '/v1/bots' ),
			array( 'base' => $base_in, 'route' => '/v1/models' ),
			array( 'base' => $base_in, 'route' => '/chatbots' ),
			array( 'base' => $base_in . '/wp-json/aipkit/v1', 'route' => '/bots' ),
			array( 'base' => $base_in . '/wp-json/aipkit/v1', 'route' => '/models' ),
			array( 'base' => $base_in . '/wp-json/aipkit/v1', 'route' => '/chatbots' ),
			array( 'base' => $base_in, 'route' => '' ),
		);

		$errors = array();

		foreach ( $candidates as $c ) {
			$url = rtrim( $c['base'], '/' ) . $c['route'];
			if ( '' === $url ) continue;

			$resp = wp_remote_get(
				$url,
				array(
					'timeout' => 15,
					'headers' => array(
						'Authorization' => 'Bearer ' . $key,
						'Accept'        => 'application/json',
					),
				)
			);

			if ( is_wp_error( $resp ) ) {
				$errors[] = $c['route'] . ': ' . $resp->get_error_message();
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $resp );

			if ( 401 === $code || 403 === $code ) {
				return array( 'ok' => false, 'bots' => array(), 'real_base' => $base_in, 'error' => "Authentication failed (HTTP {$code}). Check your API Key." );
			}

			if ( $code >= 200 && $code < 300 ) {
				$body = wp_remote_retrieve_body( $resp );
				$data = json_decode( $body, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$list = self::extract_bot_list( $data );
					if ( null !== $list ) {
						return array(
							'ok'        => true,
							'bots'      => $list,
							'real_base' => $c['base'],
						);
					}
				}
				$errors[] = $c['route'] . ': unrecognised JSON shape';
			} else {
				$errors[] = $c['route'] . ': HTTP ' . $code;
			}
		}

		$err_msg = ! empty( $errors ) ? implode( '; ', array_slice( array_unique( $errors ), 0, 3 ) ) : 'no valid route found';

		return array(
			'ok'        => false,
			'bots'      => array(),
			'real_base' => $base_in,
			'error'     => 'Discovery failed. ' . $err_msg,
		);
	}

	/**
	 * Detect bots from a local AI Puffer (AIPKit) installation.
	 */
	protected static function detect_local_bots() {
		$types = array( 'aipkit_chatbot', 'wpaicg_chatbots' );
		$valid = array();
		foreach ( $types as $t ) {
			if ( post_type_exists( $t ) ) {
				$valid[] = $t;
			}
		}
		if ( empty( $valid ) ) {
			return array();
		}
		$posts = get_posts( array(
			'post_type'      => $valid,
			'posts_per_page' => 100,
			'post_status'    => array( 'publish', 'private', 'inherit' ),
		) );
		$out = array();
		foreach ( $posts as $p ) {
			$out[] = array( 'id' => (string) $p->ID, 'name' => $p->post_title );
		}
		return $out;
	}

	/**
	 * Normalise a decoded JSON payload into a flat [{id,name}] list.
	 */
	protected static function extract_bot_list( $data ) {
		if ( ! is_array( $data ) ) {
			return null;
		}

		$raw = $data['bots'] ?? ( $data['data'] ?? ( $data['models'] ?? ( array_is_list_safe( $data ) ? $data : null ) ) );
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$out = array();
		foreach ( $raw as $item ) {
			if ( is_array( $item ) ) {
				$id   = (string) ( $item['id'] ?? ( $item['bot_id'] ?? ( $item['slug'] ?? '' ) ) );
				$name = (string) ( $item['name'] ?? ( $item['title'] ?? $id ) );
				if ( '' !== $id ) {
					$out[] = array( 'id' => $id, 'name' => $name );
				}
			} elseif ( is_string( $item ) && '' !== $item ) {
				$out[] = array( 'id' => $item, 'name' => $item );
			}
		}
		return $out;
	}

	/**
	 * Test the currently configured connection end-to-end.
	 */
	public static function test_connection() {
		$base   = VMIA_Settings::get( 'aipuffer_base' );
		$key    = VMIA_Settings::get( 'aipuffer_key' );
		$bot_id = (string) VMIA_Settings::get( 'aipuffer_bot_id' );

		$res = self::list_bots( $base, $key );
		if ( empty( $res['ok'] ) ) {
			return array(
				'ok'        => false,
				'bots'      => array(),
				'bot_id'    => $bot_id,
				'bot_valid' => null,
				'error'     => $res['error'] ?? 'connection failed',
			);
		}

		$bot_valid = null;
		if ( '' !== $bot_id ) {
			$bot_valid = false;
			foreach ( $res['bots'] as $bot ) {
				if ( (string) $bot['id'] === (string) $bot_id ) {
					$bot_valid = true;
					break;
				}
			}
		}

		return array(
			'ok'        => true,
			'bots'      => $res['bots'],
			'bot_id'    => $bot_id,
			'bot_valid' => $bot_valid,
			'local'     => ! empty( $res['local'] ),
			'real_base' => $res['real_base'] ?? $base,
		);
	}
}

if ( ! function_exists( 'array_is_list_safe' ) ) {
	function array_is_list_safe( array $arr ) {
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $arr );
		}
		$i = 0;
		foreach ( $arr as $k => $_v ) {
			if ( $k !== $i++ ) {
				return false;
			}
		}
		return true;
	}
}
