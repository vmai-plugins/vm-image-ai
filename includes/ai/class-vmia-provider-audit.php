<?php
/**
 * Utility to audit/test all image generation providers.
 *
 * @package VM_Image_AI
 */

defined( 'ABSPATH' ) || exit;

class VMIA_Provider_Audit {

	/**
	 * Test all enabled providers with a simple prompt.
	 *
	 * @return array Results per provider.
	 */
	public function run_test() {
		$prompt = 'A simple, clean icon of a blue robot representing AI.';
		$args   = array( 'width' => 512, 'height' => 512, 'enrich' => false );
		$order  = (array) VMIA_Settings::get( 'image_order' );
		$results = array();

		$providers = array(
			'pollinations' => 'VMIA_Provider_Pollinations',
			'google'       => 'VMIA_Provider_Google',
			'huggingface'  => 'VMIA_Provider_Huggingface',
			'cloudflare'   => 'VMIA_Provider_Cloudflare',
			'comfyui'      => 'VMIA_Provider_Comfyui',
			'pexels'       => 'VMIA_Provider_Pexels',
			'aipuffer'     => 'VMIA_Provider_Aipuffer',
		);

		foreach ( $providers as $name => $class ) {
			if ( ! class_exists( $class ) ) {
				$results[ $name ] = array( 'ok' => false, 'error' => 'Class not found' );
				continue;
			}

			// Some providers might be enabled but not in the current $order.
			// We test them anyway if they have credentials.
			$provider = new $class();
			$res = $provider->generate( $prompt, $args );

			$results[ $name ] = array(
				'ok'    => $res['ok'],
				'error' => $res['error'] ?? '',
				'url'   => $res['url'] ?? ( isset( $res['path'] ) ? 'File generated locally' : '' ),
			);
		}

		return $results;
	}
}
