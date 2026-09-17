<?php
/**
 * GitHub online auto-updater for VM Image AI.
 * Handles release checking, transient injection, changelog modal,
 * and post-install folder normalization.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_GitHub_Updater {

	const REPO_OWNER = 'vmai-plugins';
	const REPO_NAME  = 'vm-image-ai';
	const CACHE_KEY  = 'vmia_github_release_data';

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_update_transient' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugins_api_handler' ), 20, 3 );
		add_filter( 'upgrader_post_install', array( __CLASS__, 'post_install' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'pre_download' ), 10, 3 );
	}

	/**
	 * Attach GitHub Authorization header for private repository package downloads.
	 */
	public static function pre_download( $reply, $package, $upgrader ) {
		if ( strpos( $package, 'api.github.com/repos/' . self::REPO_OWNER . '/' . self::REPO_NAME ) !== false
			|| strpos( $package, 'github.com/' . self::REPO_OWNER . '/' . self::REPO_NAME ) !== false ) {
			$token = VMIA_Settings::get( 'github_token' );
			if ( ! empty( $token ) ) {
				add_filter( 'http_request_args', array( __CLASS__, 'add_download_auth_header' ), 10, 2 );
			}
		}
		return $reply;
	}

	public static function add_download_auth_header( $args, $url ) {
		$token = VMIA_Settings::get( 'github_token' );
		if ( ! empty( $token ) && ( strpos( $url, 'github.com' ) !== false || strpos( $url, 'api.github.com' ) !== false ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . trim( $token );
			$args['headers']['Accept']        = 'application/octet-stream';
		}
		remove_filter( 'http_request_args', array( __CLASS__, 'add_download_auth_header' ), 10 );
		return $args;
	}

	/**
	 * Get headers for GitHub API requests.
	 *
	 * @return array
	 */
	protected static function get_headers() {
		$headers = array(
			'Accept'     => 'application/vnd.github.v3+json',
			'User-Agent' => 'VM-Image-AI-Updater/' . VMIA_VERSION . '; ' . home_url(),
		);

		$token = VMIA_Settings::get( 'github_token' );
		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . trim( $token );
		}

		return $headers;
	}

	/**
	 * Query latest release from GitHub API.
	 *
	 * @param bool $force Bypass transient cache.
	 * @return array|null
	 */
	public static function get_latest_release( $force = false ) {
		if ( ! $force ) {
			$cached = get_site_transient( self::CACHE_KEY );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$url  = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', self::REPO_OWNER, self::REPO_NAME );
		$resp = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => self::get_headers(),
		) );

		if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
			// Fallback: Check tags if releases/latest is empty or not yet published
			$tags_url  = sprintf( 'https://api.github.com/repos/%s/%s/tags', self::REPO_OWNER, self::REPO_NAME );
			$tags_resp = wp_remote_get( $tags_url, array(
				'timeout' => 15,
				'headers' => self::get_headers(),
			) );

			if ( ! is_wp_error( $tags_resp ) && 200 === wp_remote_retrieve_response_code( $tags_resp ) ) {
				$tags = json_decode( wp_remote_retrieve_body( $tags_resp ), true );
				if ( ! empty( $tags ) && is_array( $tags ) && isset( $tags[0]['name'] ) ) {
					$tag_name = $tags[0]['name'];
					$zip_url  = $tags[0]['zipball_url'] ?? sprintf( 'https://github.com/%s/%s/archive/refs/tags/%s.zip', self::REPO_OWNER, self::REPO_NAME, $tag_name );
					$data     = array(
						'tag_name'     => $tag_name,
						'version'      => ltrim( $tag_name, 'vV' ),
						'name'         => 'Release ' . $tag_name,
						'body'         => 'Latest release from GitHub tag ' . $tag_name,
						'zipball_url'  => $zip_url,
						'html_url'     => sprintf( 'https://github.com/%s/%s/releases/tag/%s', self::REPO_OWNER, self::REPO_NAME, $tag_name ),
						'published_at' => current_time( 'mysql' ),
					);
					set_site_transient( self::CACHE_KEY, $data, 6 * HOUR_IN_SECONDS );
					return $data;
				}
			}
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $body ) || ! isset( $body['tag_name'] ) ) {
			return null;
		}

		// Prefer a zip asset attached to release; fallback to zipball_url
		$package_url = $body['zipball_url'] ?? '';
		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( ! empty( $asset['browser_download_url'] ) && str_ends_with( strtolower( $asset['browser_download_url'] ), '.zip' ) ) {
					$package_url = $asset['browser_download_url'];
					break;
				}
			}
		}

		$data = array(
			'tag_name'     => $body['tag_name'],
			'version'      => ltrim( $body['tag_name'], 'vV' ),
			'name'         => $body['name'] ?? $body['tag_name'],
			'body'         => $body['body'] ?? '',
			'zipball_url'  => $package_url,
			'html_url'     => $body['html_url'] ?? sprintf( 'https://github.com/%s/%s', self::REPO_OWNER, self::REPO_NAME ),
			'published_at' => $body['published_at'] ?? current_time( 'mysql' ),
		);

		set_site_transient( self::CACHE_KEY, $data, 6 * HOUR_IN_SECONDS );
		return $data;
	}

	/**
	 * Hook into WordPress update transient.
	 *
	 * @param object $transient
	 * @return object
	 */
	public static function check_update_transient( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = self::get_latest_release();
		if ( ! $release || empty( $release['version'] ) ) {
			return $transient;
		}

		if ( version_compare( VMIA_VERSION, $release['version'], '<' ) ) {
			$obj              = new stdClass();
			$obj->slug        = VMIA_SLUG;
			$obj->plugin      = VMIA_BASENAME;
			$obj->new_version = $release['version'];
			$obj->url         = $release['html_url'];
			$obj->package     = $release['zipball_url'];
			$obj->icons       = array(
				'1x'  => VMIA_URL . 'assets/css/icon.png',
				'svg' => VMIA_URL . 'assets/css/icon.svg',
			);
			$obj->banners     = array();
			$obj->requires    = '6.0';
			$obj->requires_php= '8.1';

			$transient->response[ VMIA_BASENAME ] = $obj;
		} else {
			$transient->no_update[ VMIA_BASENAME ] = (object) array(
				'slug'        => VMIA_SLUG,
				'plugin'      => VMIA_BASENAME,
				'new_version' => VMIA_VERSION,
				'url'         => $release['html_url'],
				'package'     => '',
			);
		}

		return $transient;
	}

	/**
	 * Hook into plugins_api for view details modal.
	 *
	 * @param false|object|array $result
	 * @param string             $action
	 * @param object             $args
	 * @return false|object
	 */
	public static function plugins_api_handler( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || VMIA_SLUG !== $args->slug ) {
			return $result;
		}

		$release = self::get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$res                = new stdClass();
		$res->name          = 'VM Image AI';
		$res->slug          = VMIA_SLUG;
		$res->version       = $release['version'];
		$res->author        = '<a href="https://vmstudio.digital">VM Studio Creatives</a>';
		$res->homepage      = $release['html_url'];
		$res->requires      = '6.0';
		$res->requires_php  = '8.1';
		$res->download_link = $release['zipball_url'];
		$res->last_updated  = $release['published_at'];
		$res->sections      = array(
			'description' => 'AI-integrated featured & blog image generator with intelligent Image-SEO auditing, auto alt/title/caption/description writing, resizing/compression, gap detection and one-click "God Fix".',
			'changelog'   => ! empty( $release['body'] ) ? nl2br( esc_html( $release['body'] ) ) : 'Latest updates from GitHub.',
		);

		return $res;
	}

	/**
	 * Rename GitHub unzipped folder to target plugin folder name.
	 *
	 * @param bool  $true
	 * @param array $hook_extra
	 * @param array $result
	 * @return array
	 */
	public static function post_install( $true, $hook_extra, $result ) {
		global $wp_filesystem;

		if ( empty( $hook_extra['plugin'] ) || VMIA_BASENAME !== $hook_extra['plugin'] ) {
			return $result;
		}

		$proper_folder = WP_PLUGIN_DIR . '/' . VMIA_SLUG;
		$wp_filesystem->move( $result['destination'], $proper_folder );
		$result['destination'] = $proper_folder;

		// Re-activate if was active
		if ( is_plugin_active( VMIA_BASENAME ) ) {
			activate_plugin( VMIA_BASENAME );
		}

		// Flush transient
		delete_site_transient( self::CACHE_KEY );
		delete_site_transient( 'update_plugins' );

		return $result;
	}

	/**
	 * Check for updates manually (used by REST API).
	 *
	 * @return array
	 */
	public static function check_for_updates() {
		$release = self::get_latest_release( true );
		if ( ! $release ) {
			return array(
				'ok'              => false,
				'current_version' => VMIA_VERSION,
				'message'         => __( 'Could not contact GitHub releases API. Check internet connection or token.', 'vm-image-ai' ),
			);
		}

		$has_update = version_compare( VMIA_VERSION, $release['version'], '<' );

		return array(
			'ok'              => true,
			'has_update'      => $has_update,
			'current_version' => VMIA_VERSION,
			'new_version'     => $release['version'],
			'release_name'    => $release['name'],
			'release_url'     => $release['html_url'],
			'package_url'     => $release['zipball_url'],
			'changelog'       => $release['body'],
			'published_at'    => $release['published_at'],
			'update_url'      => wp_nonce_url( admin_url( 'update.php?action=upgrade-plugin&plugin=' . urlencode( VMIA_BASENAME ) ), 'upgrade-plugin_' . VMIA_BASENAME ),
		);
	}
}
