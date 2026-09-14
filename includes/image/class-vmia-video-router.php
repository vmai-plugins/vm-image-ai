<?php
/**
 * Video generation router.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Video_Router {

	/**
	 * @param string $subject
	 * @param array  $args
	 * @return array
	 */
	public function generate( $subject, $args = array() ) {
		$prompt = $subject; // Simplification for now, could use build_prompt similar to Image_Router
		$order  = array( 'omniroute' ); // Video support currently only for OmniRoute

		$providers = array(
			'omniroute' => 'VMIA_Provider_Omniroute',
		);

		$last = 'no video provider configured';
		foreach ( $order as $name ) {
			if ( empty( $providers[ $name ] ) || ! class_exists( $providers[ $name ] ) ) {
				continue;
			}
			$provider = new $providers[ $name ]();
			if ( ! method_exists( $provider, 'generate_video' ) ) {
				continue;
			}

			$res = $provider->generate_video( $prompt, $args );
			if ( ! empty( $res['ok'] ) && ! empty( $res['url'] ) ) {
				$res['prompt'] = $prompt;
				VMIA_Logger::add( 'generate_video', 0, $name, 'Generated video', array( 'subject' => $subject ) );
				return $res;
			}
			$last = $res['error'] ?? ( $name . ' failed' );
		}

		return array( 'ok' => false, 'provider' => '', 'prompt' => $prompt, 'error' => $last );
	}

	/**
	 * @param string $subject
	 * @param array  $args
	 * @return array
	 */
	public function generate_to_library( $subject, $args = array() ) {
		$gen = $this->generate( $subject, $args );
		if ( empty( $gen['ok'] ) ) {
			return array( 'ok' => false, 'error' => $gen['error'] ?? 'generation failed' );
		}

		$media   = new VMIA_Media();
		$post_id = (int) ( $args['post_id'] ?? 0 );
		$desc    = ! empty( $args['title'] ) ? $args['title'] : $subject;

		$attach_id = $media->sideload_url( $gen['url'], $post_id, $desc );

		if ( is_wp_error( $attach_id ) ) {
			return array( 'ok' => false, 'error' => $attach_id->get_error_message() );
		}

		if ( ! empty( $args['set_featured'] ) && $post_id ) {
			set_post_thumbnail( $post_id, $attach_id );
		}

		return array(
			'ok'        => true,
			'attach_id' => $attach_id,
			'url'       => wp_get_attachment_url( $attach_id ),
			'provider'  => $gen['provider'],
		);
	}
}
