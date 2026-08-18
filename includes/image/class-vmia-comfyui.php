<?php
/**
 * ComfyUI image provider. Talks to the user's own ComfyUI server via its
 * /prompt + /history API with a basic txt2img graph. Free (self-hosted).
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Provider_Comfyui {

	/**
	 * @param string $prompt
	 * @param array  $args
	 * @return array
	 */
	public function generate( $prompt, $args ) {
		$base = rtrim( (string) VMIA_Settings::get( 'comfyui_base' ), '/' );
		if ( ! $base ) {
			return array( 'ok' => false, 'provider' => 'comfyui', 'error' => 'ComfyUI server URL not set' );
		}
		$w     = (int) ( $args['width'] ?? 1024 );
		$h     = (int) ( $args['height'] ?? 1024 );
		$ckpt  = VMIA_Settings::get( 'comfyui_ckpt', 'sd_xl_base_1.0.safetensors' );
		$steps = (int) VMIA_Settings::get( 'comfyui_steps', 25 );
		$seed  = isset( $args['seed'] ) ? (int) $args['seed'] : wp_rand( 1, PHP_INT_MAX );

		$graph = $this->build_graph( $prompt, $w, $h, $ckpt, $steps, $seed );
		$cid   = wp_generate_uuid4();

		$resp = wp_remote_post(
			$base . '/prompt',
			array(
				'timeout' => 30,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'prompt' => $graph, 'client_id' => $cid ) ),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'provider' => 'comfyui', 'error' => $resp->get_error_message() );
		}
		$queued = json_decode( wp_remote_retrieve_body( $resp ), true );
		$pid    = $queued['prompt_id'] ?? '';
		if ( ! $pid ) {
			return array( 'ok' => false, 'provider' => 'comfyui', 'error' => 'no prompt_id from ComfyUI' );
		}

		// Poll history for the rendered image (up to ~120s).
		for ( $i = 0; $i < 40; $i++ ) {
			sleep( 3 );
			$h_resp = wp_remote_get( $base . '/history/' . rawurlencode( $pid ), array( 'timeout' => 20 ) );
			if ( is_wp_error( $h_resp ) ) {
				continue;
			}
			$hist = json_decode( wp_remote_retrieve_body( $h_resp ), true );
			if ( empty( $hist[ $pid ]['outputs'] ) ) {
				continue;
			}
			foreach ( $hist[ $pid ]['outputs'] as $out ) {
				if ( ! empty( $out['images'][0]['filename'] ) ) {
					$img = $out['images'][0];
					$url = $base . '/view?' . http_build_query(
						array(
							'filename'  => $img['filename'],
							'subfolder' => $img['subfolder'] ?? '',
							'type'      => $img['type'] ?? 'output',
						)
					);
					return array( 'ok' => true, 'url' => $url, 'provider' => 'comfyui' );
				}
			}
		}
		return array( 'ok' => false, 'provider' => 'comfyui', 'error' => 'timed out waiting for render' );
	}

	/**
	 * Minimal SDXL txt2img graph.
	 */
	protected function build_graph( $prompt, $w, $h, $ckpt, $steps, $seed ) {
		return array(
			'4'  => array( 'class_type' => 'CheckpointLoaderSimple', 'inputs' => array( 'ckpt_name' => $ckpt ) ),
			'5'  => array( 'class_type' => 'EmptyLatentImage', 'inputs' => array( 'width' => $w, 'height' => $h, 'batch_size' => 1 ) ),
			'6'  => array( 'class_type' => 'CLIPTextEncode', 'inputs' => array( 'text' => $prompt, 'clip' => array( '4', 1 ) ) ),
			'7'  => array( 'class_type' => 'CLIPTextEncode', 'inputs' => array( 'text' => 'lowres, blurry, watermark, text, deformed', 'clip' => array( '4', 1 ) ) ),
			'3'  => array(
				'class_type' => 'KSampler',
				'inputs'     => array(
					'seed' => $seed, 'steps' => $steps, 'cfg' => 7, 'sampler_name' => 'euler',
					'scheduler' => 'normal', 'denoise' => 1,
					'model' => array( '4', 0 ), 'positive' => array( '6', 0 ),
					'negative' => array( '7', 0 ), 'latent_image' => array( '5', 0 ),
				),
			),
			'8'  => array( 'class_type' => 'VAEDecode', 'inputs' => array( 'samples' => array( '3', 0 ), 'vae' => array( '4', 2 ) ) ),
			'9'  => array( 'class_type' => 'SaveImage', 'inputs' => array( 'filename_prefix' => 'vmia', 'images' => array( '8', 0 ) ) ),
		);
	}
}
