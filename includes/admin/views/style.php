<?php
/**
 * Style Manager view.
 *
 * @package VM_Image_AI
 * @var array $s Current settings.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap vmia">
	<div class="vmia-topbar">
		<div class="vmia-brand">
			<span class="vmia-logo">VM</span>
			<div>
				<h1><?php esc_html_e( 'Style Manager', 'vm-image-ai' ); ?></h1>
				<p class="vmia-sub"><?php esc_html_e( 'Enforce brand consistency across all AI generated images', 'vm-image-ai' ); ?></p>
			</div>
		</div>
		<div class="vmia-actions">
			<button class="vmia-btn vmia-btn-primary" id="vmia-save"><?php esc_html_e( 'Save Styles', 'vm-image-ai' ); ?></button>
		</div>
	</div>

	<div class="vmia-grid vmia-grid-2">
		<div class="vmia-card">
			<p class="vmia-card-title"><?php esc_html_e( 'Art Direction', 'vm-image-ai' ); ?></p>

			<label class="vmia-label"><?php esc_html_e( 'Visual Preset', 'vm-image-ai' ); ?></label>
			<select class="vmia-input" data-key="style_preset">
				<option value="editorial" <?php selected( $s['style_preset'], 'editorial' ); ?>><?php esc_html_e( 'Clean Editorial (Recommended)', 'vm-image-ai' ); ?></option>
				<option value="cinematic" <?php selected( $s['style_preset'], 'cinematic' ); ?>><?php esc_html_e( 'Cinematic / Dramatic', 'vm-image-ai' ); ?></option>
				<option value="minimalist" <?php selected( $s['style_preset'], 'minimalist' ); ?>><?php esc_html_e( 'Minimalist / Modern', 'vm-image-ai' ); ?></option>
				<option value="digital_art" <?php selected( $s['style_preset'], 'digital_art' ); ?>><?php esc_html_e( 'Digital Illustration', 'vm-image-ai' ); ?></option>
			</select>

			<label class="vmia-label"><?php esc_html_e( 'Brand Context / Style Guide', 'vm-image-ai' ); ?></label>
			<textarea class="vmia-input" data-key="brand_context" rows="5" placeholder="<?php esc_attr_e( 'e.g. Use a pastel blue and white color palette. Avoid dark shadows. Keep compositions centered and balanced.', 'vm-image-ai' ); ?>"><?php echo esc_textarea( $s['brand_context'] ); ?></textarea>
			<p class="vmia-muted"><?php esc_html_e( 'This context is injected into every image prompt to ensure consistency.', 'vm-image-ai' ); ?></p>
		</div>

		<div class="vmia-card">
			<p class="vmia-card-title"><?php esc_html_e( 'Advanced Filtering', 'vm-image-ai' ); ?></p>

			<label class="vmia-label"><?php esc_html_e( 'Negative Prompt (Global)', 'vm-image-ai' ); ?></label>
			<textarea class="vmia-input" data-key="negative_prompt" rows="3"><?php echo esc_textarea( $s['negative_prompt'] ); ?></textarea>
			<p class="vmia-muted"><?php esc_html_e( 'Things the AI should ALWAYS avoid (e.g. text, blurry, low resolution, multiple limbs).', 'vm-image-ai' ); ?></p>

			<div class="vmia-mt">
				<p class="vmia-card-label"><strong><?php esc_html_e( 'Pro Tip:', 'vm-image-ai' ); ?></strong> <?php esc_html_e( 'Use your brand colors in the Brand Context to make your blog look unified.', 'vm-image-ai' ); ?></p>
			</div>
		</div>
	</div>
</div>
