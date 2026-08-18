<?php
/**
 * Image generation router. Enriches the prompt with AI, then tries image
 * providers in the configured order until one returns an image.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Image_Router {

	/**
	 * @param string $subject Raw subject/idea (e.g. post title).
	 * @param array  $args    { width, height, style?, enrich?:bool, seed? }
	 * @return array { ok, url?|path?, provider, prompt, error? }
	 */
	public function generate( $subject, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'width'  => 1200,
				'height' => 630,
				'style'  => 'clean modern editorial, high detail, professional lighting',
				'enrich' => true,
			)
		);

		$prompt = $args['enrich'] ? $this->build_prompt( $subject, $args['style'], (int) ( $args['post_id'] ?? 0 ) ) : $subject;
		$order  = (array) VMIA_Settings::get( 'image_order' );
		$last   = 'no image provider configured';

		$providers = array(
			'pollinations' => 'VMIA_Provider_Pollinations',
			'google'        => 'VMIA_Provider_Google',
			'google_search' => 'VMIA_Provider_Google_Search',
			'openai'        => 'VMIA_Provider_Openai',
			'huggingface'  => 'VMIA_Provider_Huggingface',
			'cloudflare'   => 'VMIA_Provider_Cloudflare',
			'comfyui'      => 'VMIA_Provider_Comfyui',
			'pexels'       => 'VMIA_Provider_Pexels',
			'aipuffer'     => 'VMIA_Provider_Aipuffer',
		);

		foreach ( $order as $name ) {
			if ( empty( $providers[ $name ] ) || ! class_exists( $providers[ $name ] ) ) {
				continue;
			}
			$provider = new $providers[ $name ]();
			$res      = $provider->generate( $prompt, $args );
			if ( ! empty( $res['ok'] ) && ( ! empty( $res['url'] ) || ! empty( $res['path'] ) ) ) {
				$res['prompt'] = $prompt;
				VMIA_Logger::add( 'generate', 0, $name, 'Generated image', array( 'subject' => $subject ) );
				return $res;
			}
			$last = $res['error'] ?? ( $name . ' failed' );
			VMIA_Logger::add( 'error', 0, $name, 'Generation failed: ' . $last );
		}

		VMIA_Logger::add( 'error', 0, 'image', 'All image providers failed: ' . $last );
		return array( 'ok' => false, 'provider' => '', 'prompt' => $prompt, 'error' => $last );
	}

	/**
	 * Generate an image and import it into the media library.
	 *
	 * @param string $subject
	 * @param array  $args { post_id?, set_featured?, alt?, title? }
	 * @return array { ok, attach_id?, url?, provider?, error? }
	 */
	public function generate_to_library( $subject, $args = array() ) {
		$gen = $this->generate( $subject, $args );
		if ( empty( $gen['ok'] ) ) {
			return array( 'ok' => false, 'error' => $gen['error'] ?? 'generation failed' );
		}

		$media   = new VMIA_Media();
		$post_id = (int) ( $args['post_id'] ?? 0 );
		$desc    = ! empty( $args['title'] ) ? $args['title'] : $subject;

		$attach_id = ! empty( $gen['url'] )
			? $media->sideload_url( $gen['url'], $post_id, $desc )
			: $media->sideload_path( $gen['path'], $post_id, $desc );

		if ( is_wp_error( $attach_id ) ) {
			return array( 'ok' => false, 'error' => $attach_id->get_error_message() );
		}

		// Resize/compress to target and optionally WebP.
		( new VMIA_Resize() )->optimize_attachment( $attach_id, (int) $args['width'], (int) $args['height'] );

		// Auto-write SEO metadata for the new asset.
		if ( VMIA_Settings::has_text_ai() ) {
			( new VMIA_SEO_Writer() )->write_for_attachment( $attach_id, $post_id, array( 'subject' => $subject ) );
		} elseif ( ! empty( $args['alt'] ) ) {
			update_post_meta( $attach_id, '_wp_attachment_image_alt', sanitize_text_field( $args['alt'] ) );
		}

		if ( ! empty( $args['set_featured'] ) && $post_id ) {
			set_post_thumbnail( $post_id, $attach_id );
		}
		if ( ! empty( $gen['credit'] ) ) {
			update_post_meta( $attach_id, '_vmia_credit', sanitize_text_field( $gen['credit'] ) );
		}

		return array(
			'ok'        => true,
			'attach_id' => $attach_id,
			'url'       => wp_get_attachment_url( $attach_id ),
			'provider'  => $gen['provider'],
		);
	}

	/**
	 * Turn a bare subject into a rich visual prompt via the AI engine.
	 *
	 * @param string $subject
	 * @param string $style
	 * @return string
	 */
	protected function build_prompt( $subject, $style, $post_id = 0 ) {
		if ( ! VMIA_Settings::has_text_ai() ) {
			return trim( $subject . ', ' . $style );
		}

		$brand    = VMIA_Settings::get( 'brand_context' );
		$preset   = VMIA_Settings::get( 'style_preset', 'editorial' );
		$negative = VMIA_Settings::get( 'negative_prompt' );

		// Intelligent context gathering.
		$extra_context = '';
		if ( ! $post_id && isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof WP_Post ) {
			$post_id = $GLOBALS['post']->ID;
		}

		if ( $post_id ) {
			$categories = wp_get_post_categories( $post_id, array( 'fields' => 'names' ) );
			$tags       = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
			if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) $extra_context .= ' Category: ' . implode( ', ', $categories ) . '.';
			if ( ! empty( $tags ) && ! is_wp_error( $tags ) )       $extra_context .= ' Tags: ' . implode( ', ', $tags ) . '.';
		}

		$ask   = "Write ONE concise text-to-image prompt (max 45 words) for a blog image about:\n\"{$subject}\"\n"
			. "Describe concrete visual elements, composition, and mood. "
			. "Target style: {$preset}. "
			. ( $extra_context ? "Content context: {$extra_context} " : '' )
			. ( $brand ? "Brand guidelines: {$brand}. " : '' )
			. "Ensure high quality. No text or logos. Output only the prompt.";

		$res = ( new VMIA_AI_Router() )->complete( $ask, array( 'max_tokens' => 120, 'temperature' => 0.7 ) );
		$out = ! empty( $res['ok'] ) ? trim( $res['text'] ) : '';

		$final = $out !== '' ? $out : trim( $subject . ', ' . $style );

		if ( $negative ) {
			$final .= " --no " . $negative; // Standard format for some models, others ignore it.
		}

		return $final;
	}
}
