<?php
/**
 * Writes SEO metadata for an image attachment: alt, title, caption, description.
 * Uses vision (to see the image) + the text AI engine (to phrase copy), grounded
 * in the parent post's context so alt text is relevant, not generic.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_SEO_Writer {

	/**
	 * Generate metadata for an attachment and persist it.
	 *
	 * @param int   $attach_id
	 * @param int   $post_id   Parent/hosting post for context (0 = none).
	 * @param array $args      { overwrite?:bool, subject?:string }
	 * @return array The written fields.
	 */
	public function write_for_attachment( $attach_id, $post_id = 0, $args = array() ) {
		$args = wp_parse_args( $args, array( 'overwrite' => false, 'subject' => '' ) );

		$data = $this->generate( $attach_id, $post_id, $args['subject'] );
		if ( empty( $data ) ) {
			return array();
		}

		$current_alt = get_post_meta( $attach_id, '_wp_attachment_image_alt', true );
		if ( $args['overwrite'] || '' === trim( (string) $current_alt ) ) {
			VMIA_Undo::record( $attach_id, '_wp_attachment_image_alt', $current_alt, $data['alt'] );
			update_post_meta( $attach_id, '_wp_attachment_image_alt', sanitize_text_field( $data['alt'] ) );
		}

		$update = array( 'ID' => $attach_id );
		$att    = get_post( $attach_id );

		if ( $args['overwrite'] || $this->looks_like_filename( $att->post_title, $attach_id ) ) {
			VMIA_Undo::record( $attach_id, 'post_title', $att->post_title, $data['title'] );
			$update['post_title'] = sanitize_text_field( $data['title'] );
		}
		if ( $args['overwrite'] || '' === trim( (string) $att->post_excerpt ) ) {
			VMIA_Undo::record( $attach_id, 'post_excerpt', $att->post_excerpt, $data['caption'] );
			$update['post_excerpt'] = sanitize_text_field( $data['caption'] ); // caption.
		}
		if ( $args['overwrite'] || '' === trim( (string) $att->post_content ) ) {
			VMIA_Undo::record( $attach_id, 'post_content', $att->post_content, $data['description'] );
			$update['post_content'] = sanitize_textarea_field( $data['description'] ); // description.
		}
		if ( count( $update ) > 1 ) {
			wp_update_post( $update );
		}

		VMIA_Logger::add( 'fix', $attach_id, $data['provider'] ?? 'ai', 'Wrote image SEO metadata' );
		return $data;
	}

	/**
	 * Produce (but do not save) the metadata set.
	 *
	 * @param int    $attach_id
	 * @param int    $post_id
	 * @param string $subject
	 * @return array { alt,title,caption,description,provider }|array()
	 */
	public function generate( $attach_id, $post_id = 0, $subject = '' ) {
		$path     = get_attached_file( $attach_id );
		$filename = $path ? basename( $path ) : '';
		$context  = $this->post_context( $post_id );
		if ( $subject ) {
			$context = trim( $subject . '. ' . $context );
		}

		// See the image where possible.
		$visual = '';
		if ( $path && is_readable( $path ) ) {
			$visual = ( new VMIA_Vision() )->describe( $path, $context );
		}

		if ( ! VMIA_Settings::has_text_ai() ) {
			// Degrade gracefully to a filename-derived alt.
			return $this->fallback_from_filename( $filename, $context, $visual );
		}

		$min   = (int) VMIA_Settings::get( 'alt_min_len', 12 );
		$max   = (int) VMIA_Settings::get( 'alt_max_len', 125 );
		$loc   = VMIA_Settings::get( 'locale_hint', 'en' );
		$brand = VMIA_Settings::get( 'brand_context' );

		$prompt = "Create SEO metadata for an image. Respond as strict JSON with keys: "
			. "alt, title, caption, description.\n"
			. "- alt: {$min}-{$max} chars, describe the image factually for accessibility + SEO, include the main keyword naturally, no \"image of\"/\"picture of\".\n"
			. "- title: short human title (3-8 words).\n"
			. "- caption: one friendly sentence suitable to show under the image.\n"
			. "- description: 1-2 sentence longer description.\n"
			. "Language: {$loc}.\n"
			. ( $brand ? "Brand/business context: {$brand}.\n" : '' )
			. ( $context ? "Page context: {$context}.\n" : '' )
			. ( $visual ? "What the image actually shows: {$visual}.\n" : '' )
			. ( $filename ? "Original filename: {$filename}.\n" : '' )
			. 'Output only JSON.';

		$json = ( new VMIA_AI_Router() )->complete_json( $prompt, array( 'max_tokens' => 320, 'temperature' => 0.5 ) );
		if ( ! is_array( $json ) || empty( $json['alt'] ) ) {
			return $this->fallback_from_filename( $filename, $context, $visual );
		}

		return array(
			'alt'         => $this->clamp( $json['alt'], $max ),
			'title'       => sanitize_text_field( $json['title'] ?? $this->title_from( $filename ) ),
			'caption'     => sanitize_text_field( $json['caption'] ?? '' ),
			'description' => sanitize_textarea_field( $json['description'] ?? '' ),
			'provider'    => 'ai',
		);
	}

	/* ------------------------------------------------------------------ */

	protected function post_context( $post_id ) {
		if ( ! $post_id ) {
			return '';
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$keyword = '';
		// RankMath Focus Keyword.
		if ( class_exists( 'RankMath' ) ) {
			$keyword = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
		}
		// Yoast Focus Keyword.
		if ( ! $keyword && defined( 'WPSEO_VERSION' ) ) {
			$keyword = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
		}

		$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 40, '' );
		$context = trim( get_the_title( $post_id ) . '. ' . $excerpt );

		if ( $keyword ) {
			$context = "TARGET SEO KEYWORD: \"{$keyword}\". " . $context;
		}

		return $context;
	}

	protected function fallback_from_filename( $filename, $context, $visual = '' ) {
		$base = $visual ?: ( $context ?: $this->title_from( $filename ) );
		$base = trim( wp_strip_all_tags( $base ) );
		if ( '' === $base ) {
			$base = 'Descriptive image';
		}
		return array(
			'alt'         => $this->clamp( $base, (int) VMIA_Settings::get( 'alt_max_len', 125 ) ),
			'title'       => $this->title_from( $filename ),
			'caption'     => '',
			'description' => '',
			'provider'    => 'fallback',
		);
	}

	protected function title_from( $filename ) {
		$name = preg_replace( '/\.\w+$/', '', (string) $filename );
		$name = preg_replace( '/[-_]+/', ' ', $name );
		return ucwords( trim( $name ) );
	}

	protected function clamp( $text, $max ) {
		$text = trim( wp_strip_all_tags( (string) $text ) );
		if ( mb_strlen( $text ) > $max ) {
			$text = rtrim( mb_substr( $text, 0, $max - 1 ) ) . '';
		}
		return sanitize_text_field( $text );
	}

	/**
	 * Does an attachment title still look like a raw filename?
	 */
	public function looks_like_filename( $title, $attach_id ) {
		$title = trim( (string) $title );
		if ( '' === $title ) {
			return true;
		}
		// Common camera / screenshot / hash patterns.
		if ( preg_match( '/^(img[-_ ]?\d+|dsc[-_ ]?\d+|screenshot|photo[-_ ]?\d+|image[-_ ]?\d+|[a-f0-9]{8,})/i', $title ) ) {
			return true;
		}
		if ( preg_match( '/^\d+$/', $title ) ) {
			return true;
		}
		return false;
	}
}
