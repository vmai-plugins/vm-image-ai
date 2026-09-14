<?php
/**
 * Image-SEO auditor. Scans the media library and post content, detects issues,
 * scores them and stores them in the vmia_audit table.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Auditor {

	/** Human labels + severities for each issue code. */
	const ISSUES = array(
		'missing_alt'        => array( 3, 'Missing alt text' ),
		'short_alt'          => array( 2, 'Alt text too short / thin' ),
		'filename_alt'       => array( 2, 'Alt text is just the filename' ),
		'duplicate_alt'      => array( 1, 'Duplicate alt text across images' ),
		'filename_title'     => array( 1, 'Title is a raw filename' ),
		'missing_caption'    => array( 1, 'No caption/description' ),
		'bad_filename'       => array( 1, 'Non-descriptive filename' ),
		'oversized_file'     => array( 2, 'File size too large' ),
		'oversized_dim'      => array(2, 'Dimensions too large'),
		'legacy_format'      => array(1, 'Not next-gen (WebP) format'),
		'no_featured'        => array(3, 'Post has no featured image'),
		'content_img_no_alt' => array(2, 'In-content image missing alt'),
		'broken_file'        => array(3, 'File missing or blank'),
		'keyword_gap'        => array(2, 'Alt text missing focus keyword'),
		'no_images'          => array(2, 'Post has no images at all'),
		'content_404'        => array(3, 'Broken external/missing link in content'),
	);

	/**
	 * Run a full (or chunked) scan.
	 *
	 * @param int  $limit  Attachments to scan this run (0 = all).
	 * @param int  $offset
	 * @param bool $fresh  Clear previous open rows before scanning (first chunk only).
	 * @return array Summary counts by issue code.
	 */
	public function scan( $limit = 0, $offset = 0, $fresh = true ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vmia_audit';

		if ( $fresh ) {
			$wpdb->query( "DELETE FROM {$table} WHERE status = 'open'" ); // phpcs:ignore
		}

		$summary = array();

		// ---- Attachments ----
		$q = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => $limit > 0 ? $limit : -1,
				'offset'         => $offset,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$alt_index = array(); // alt => [ids] for duplicate detection.

		foreach ( $q->posts as $attach_id ) {
			$issues = $this->audit_attachment( $attach_id, $alt_index );
			foreach ( $issues as $code => $detail ) {
				$this->record( 'attachment', $attach_id, 0, $code, $detail );
				$summary[ $code ] = ( $summary[ $code ] ?? 0 ) + 1;
			}
		}

		// Duplicate-alt pass.
		foreach ( $alt_index as $alt => $ids ) {
			if ( count( $ids ) > 1 && '' !== $alt ) {
				foreach ( $ids as $dup_id ) {
					$this->record( 'attachment', $dup_id, 0, 'duplicate_alt', 'Alt "' . $alt . '" used on ' . count( $ids ) . ' images' );
					$summary['duplicate_alt'] = ( $summary['duplicate_alt'] ?? 0 ) + 1;
				}
			}
		}

		// ---- Posts (featured image + in-content) — only on a full scan ----
		if ( 0 === $offset ) {
			$post_types = (array) VMIA_Settings::get( 'scan_post_types', array( 'post', 'page' ) );
			$pq         = new WP_Query(
				array(
					'post_type'      => $post_types,
					'post_status'    => 'publish',
					'posts_per_page' => 300,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			foreach ( $pq->posts as $pid ) {
				foreach ( $this->audit_post( $pid ) as $code => $detail ) {
					$this->record( 'post', $pid, $pid, $code, $detail );
					$summary[ $code ] = ( $summary[ $code ] ?? 0 ) + 1;
				}
			}
		}

		update_option( 'vmia_last_scan', current_time( 'mysql' ), false );
		VMIA_Logger::add( 'scan', 0, 'local', sprintf( 'Scan complete: %d issues', array_sum( $summary ) ), $summary );

		// Record health snapshot.
		VMIA_Stats::record( 'health_score', self::health_score() );

		// Clean up old records.
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}vmia_log WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - ( 14 * DAY_IN_SECONDS ) ) ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}vmia_undo WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) ) ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}vmia_stats WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - ( 90 * DAY_IN_SECONDS ) ) ) );

		return $summary;
	}

	/**
	 * @param int   $attach_id
	 * @param array $alt_index by-reference alt collector.
	 * @return array code => detail
	 */
	public function audit_attachment( $attach_id, &$alt_index = array() ) {
		$issues = array();
		$alt    = trim( (string) get_post_meta( $attach_id, '_wp_attachment_image_alt', true ) );
		$att    = get_post( $attach_id );
		$path   = get_attached_file( $attach_id );
		$file   = $path ? basename( $path ) : '';
		$min    = (int) VMIA_Settings::get( 'alt_min_len', 12 );

		// Alt checks.
		if ( '' === $alt ) {
			$issues['missing_alt'] = 'No alt text set';
		} else {
			$alt_index[ mb_strtolower( $alt ) ][] = $attach_id;
			if ( mb_strlen( $alt ) < $min ) {
				$issues['short_alt'] = sprintf( 'Alt is %d chars (min %d)', mb_strlen( $alt ), $min );
			}
			$namebase = strtolower( preg_replace( '/\.\w+$/', '', $file ) );
			if ( $namebase && ( sanitize_title( $alt ) === sanitize_title( $namebase ) ) ) {
				$issues['filename_alt'] = 'Alt duplicates the filename';
			}

			// Keyword Gap check.
			$post_id = (int) wp_get_post_parent_id($attach_id);
			if ($post_id) {
				$keyword = $this->get_focus_keyword($post_id);
				if ($keyword && stripos($alt, $keyword) === false) {
					$issues['keyword_gap'] = sprintf('Keyword "%s" not in alt', $keyword);
				}
			}
		}

		// Title check.
		if ( ( new VMIA_SEO_Writer() )->looks_like_filename( $att->post_title ?? '', $attach_id ) ) {
			$issues['filename_title'] = 'Title: "' . ( $att->post_title ?? '' ) . '"';
		}

		// Caption/description.
		if ( '' === trim( (string) ( $att->post_excerpt ?? '' ) ) && '' === trim( (string) ( $att->post_content ?? '' ) ) ) {
			$issues['missing_caption'] = 'No caption or description';
		}

		// Filename quality.
		if ( $file && preg_match( '/^(img[-_]?\d+|dsc[-_]?\d+|screenshot|photo[-_]?\d+|untitled|[a-f0-9]{12,}|\d{6,})/i', $file ) ) {
			$issues['bad_filename'] = 'Filename: ' . $file;
		}

		// File weight / dimensions / format.
		if ($path) {
			if (!file_exists($path)) {
				$issues['broken_file'] = 'File does not exist on disk';
			} else {
				if ( ! is_writable( dirname( $path ) ) ) {
					$issues['broken_file'] = 'Uploads folder not writable';
				}
				$kb  = filesize($path) / 1024;
				if ($kb < 0.1) {
					$issues['broken_file'] = 'File is blank (0 bytes)';
				}

				$cap = (int) VMIA_Settings::get('max_kb', 300);
				if ($kb > $cap) {
					$issues['oversized_file'] = sprintf('%s (cap %d KB)', size_format(filesize($path)), $cap);
				}
				$meta   = wp_get_attachment_metadata($attach_id);
				$maxdim = (int) VMIA_Settings::get('max_dim', 2560);
				if (!empty($meta['width']) && ($meta['width'] > $maxdim || ($meta['height'] ?? 0) > $maxdim)) {
					$issues['oversized_dim'] = sprintf('%d×%d (cap %d)', $meta['width'], $meta['height'] ?? 0, $maxdim);
				}
				$mime = get_post_mime_type($attach_id);
				if (VMIA_Settings::get('convert_webp') && 'image/webp' !== $mime && $kb > 80) {
					$issues['legacy_format'] = 'Format: ' . $mime;
				}
			}
		} else {
			$issues['broken_file'] = 'No physical file path found';
		}

		return $issues;
	}

	protected function get_focus_keyword($post_id) {
		$keyword = '';
		if (class_exists('RankMath')) {
			$keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
		}
		if (!$keyword && defined('WPSEO_VERSION')) {
			$keyword = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
		}
		return $keyword;
	}

	/**
	 * @param int $post_id
	 * @return array code => detail
	 */
	public function audit_post( $post_id ) {
		$issues = array();
		$has_featured = has_post_thumbnail( $post_id );

		if ( post_type_supports( get_post_type( $post_id ), 'thumbnail' ) && ! $has_featured ) {
			$issues['no_featured'] = 'No featured image on "' . get_the_title( $post_id ) . '"';
		}

		$content = get_post_field( 'post_content', $post_id );
		$has_in_content = false;
		if ( $content && preg_match_all( '/<img\b[^>]*src\s*=\s*(["\'])(.*?)\1[^>]*>/i', $content, $imgs ) ) {
			$has_in_content = true;
			$missing_alt = 0;
			$broken_links = 0;

			foreach ( $imgs[0] as $i => $tag ) {
				$src = $imgs[2][$i];

				// Alt check.
				if ( ! preg_match( '/\balt\s*=\s*(["\'])(.*?)\1/i', $tag, $m ) || '' === trim( $m[2] ) ) {
					$missing_alt++;
				}

				// Verified broken/missing link check (local file or live HTTP status).
				if ( $this->url_is_broken( $src ) ) {
					$broken_links++;
				}
			}

			if ( $missing_alt > 0 ) {
				$issues['content_img_no_alt'] = sprintf( '%d in-content image(s) missing alt', $missing_alt );
			}
			if ( $broken_links > 0 ) {
				$issues['content_404'] = sprintf( '%d image(s) in content have broken or placeholder links', $broken_links );
			}
		}

		if ( ! $has_featured && ! $has_in_content ) {
			$issues['no_images'] = 'This post is purely text; no visual assets found';
		}

		return $issues;
	}

	/**
	 * Determine whether an in-content <img> src is missing/dead. Local uploads
	 * are checked on disk; remote URLs get a cached HEAD/ranged-GET request so
	 * repeated scans don't hammer external hosts.
	 *
	 * @param string $src
	 * @return bool
	 */
	public function url_is_broken( $src ) {
		$src = trim( (string) $src );
		if ( '' === $src || str_contains( $src, 'placeholder' ) ) {
			return true;
		}

		if ( str_starts_with( $src, 'data:' ) ) {
			return false;
		}

		$upload_dir = wp_get_upload_dir();
		if ( str_starts_with( $src, $upload_dir['baseurl'] ) ) {
			$path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $src );
			$path = strtok( $path, '?' );
			return ! file_exists( $path );
		}

		if ( ! preg_match( '#^https?://#i', $src ) ) {
			return false; // Can't resolve a relative path reliably; don't false-flag it.
		}

		$cache_key = 'vmia_404_' . md5( $src );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return 'broken' === $cached;
		}

		$resp = wp_remote_head( $src, array( 'timeout' => 5, 'redirection' => 3 ) );
		$code = is_wp_error( $resp ) ? 0 : wp_remote_retrieve_response_code( $resp );

		// Some hosts reject HEAD; retry with a minimal ranged GET before giving up.
		if ( is_wp_error( $resp ) || 0 === $code || 405 === $code ) {
			$resp = wp_remote_get( $src, array( 'timeout' => 6, 'redirection' => 3, 'headers' => array( 'Range' => 'bytes=0-0' ) ) );
			$code = is_wp_error( $resp ) ? 0 : wp_remote_retrieve_response_code( $resp );
		}

		$broken = is_wp_error( $resp ) || 0 === $code || $code >= 400;
		set_transient( $cache_key, $broken ? 'broken' : 'ok', DAY_IN_SECONDS );
		return $broken;
	}

	/**
	 * Insert an issue row (deduped by object+code while still open).
	 */
	protected function record( $object_type, $object_id, $post_id, $code, $detail ) {
		global $wpdb;
		$table    = $wpdb->prefix . 'vmia_audit';
		$severity = self::ISSUES[ $code ][0] ?? 1;

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE object_id=%d AND issue_code=%s AND status='open' LIMIT 1", // phpcs:ignore
				$object_id,
				$code
			)
		);
		if ( $exists ) {
			return;
		}

		$wpdb->insert(
			$table,
			array(
				'object_type' => $object_type,
				'object_id'   => $object_id,
				'post_id'     => $post_id,
				'issue_code'  => $code,
				'severity'    => $severity,
				'detail'      => $detail,
				'status'      => 'open',
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/* ------------------------------------------------------------------ */
	/* Read helpers used by the dashboard / REST                          */
	/* ------------------------------------------------------------------ */

	public static function summary() {
		global $wpdb;
		$table = $wpdb->prefix . 'vmia_audit';
		$rows  = $wpdb->get_results( "SELECT issue_code, COUNT(*) AS n FROM {$table} WHERE status='open' GROUP BY issue_code", ARRAY_A ); // phpcs:ignore
		$out   = array();
		foreach ( $rows as $r ) {
			$out[ $r['issue_code'] ] = (int) $r['n'];
		}
		return $out;
	}

	public static function health_score() {
		$total = self::total_images();
		if ( $total < 1 ) {
			return 100;
		}
		// Weighted by severity.
		global $wpdb;
		$table = $wpdb->prefix . 'vmia_audit';
		$weighted = (int) $wpdb->get_var( "SELECT COALESCE(SUM(severity),0) FROM {$table} WHERE status='open'" ); // phpcs:ignore
		$score    = 100 - min( 100, round( ( $weighted / max( 1, $total * 3 ) ) * 100 ) );
		return max( 0, (int) $score );
	}

	public static function total_images() {
		$counts = (array) wp_count_attachments();
		$n      = 0;
		foreach ( $counts as $mime => $c ) {
			if ( str_starts_with( (string) $mime, 'image/' ) ) {
				$n += (int) $c;
			}
		}
		return $n;
	}

	/**
	 * @param int   $limit
	 * @param array $filter { code?, severity? }
	 * @return array
	 */
	public static function open_issues( $limit = 100, $filter = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vmia_audit';
		$where = "status='open'";
		$args  = array();
		if ( ! empty( $filter['code'] ) ) {
			$where .= ' AND issue_code=%s';
			$args[] = $filter['code'];
		}
		if ( ! empty( $filter['severity'] ) ) {
			$where .= ' AND severity=%d';
			$args[] = (int) $filter['severity'];
		}
		$args[] = (int) $limit;
		$sql    = "SELECT * FROM {$table} WHERE {$where} ORDER BY severity DESC, id ASC LIMIT %d";
		return $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore
	}
}
