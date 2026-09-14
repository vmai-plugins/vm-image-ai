<?php
/**
 * Image generator view.
 *
 * @package VM_Image_AI
 * @var WP_Post[] $posts
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap vmia" data-theme="<?php echo esc_attr( VMIA_Settings::get( 'theme', 'light' ) ); ?>">
	<div class="vmia-topbar">
		<div class="vmia-brand">
			<span class="vmia-logo">VM</span>
			<div>
				<h1><?php esc_html_e( 'Generate Images', 'vm-image-ai' ); ?></h1>
				<p class="vmia-sub"><?php esc_html_e( 'AI-generate featured & blog images, auto-imported and SEO-optimised', 'vm-image-ai' ); ?></p>
			</div>
		</div>
		<div class="vmia-actions">
			<div class="vmia-theme-toggle">
				<button type="button" class="vmia-theme-btn <?php echo VMIA_Settings::get( 'theme', 'light' ) === 'light' ? 'active' : ''; ?>" data-theme-set="light" title="<?php esc_attr_e( 'Light mode', 'vm-image-ai' ); ?>">☀️</button>
				<button type="button" class="vmia-theme-btn <?php echo VMIA_Settings::get( 'theme', 'light' ) === 'dark' ? 'active' : ''; ?>" data-theme-set="dark" title="<?php esc_attr_e( 'Dark mode', 'vm-image-ai' ); ?>">🌙</button>
				<button type="button" class="vmia-theme-btn <?php echo VMIA_Settings::get( 'theme', 'light' ) === 'auto' ? 'active' : ''; ?>" data-theme-set="auto" title="<?php esc_attr_e( 'System preference', 'vm-image-ai' ); ?>">⚙️</button>
			</div>
			<button class="vmia-btn vmia-btn-ghost" id="vmia-bulk-generate-missing"><?php esc_html_e( '🚀 Bulk Generate Missing Featured', 'vm-image-ai' ); ?></button>
		</div>
	</div>

	<div class="vmia-grid vmia-grid-2">
		<div class="vmia-card">
			<p class="vmia-card-title"><?php esc_html_e( 'New image', 'vm-image-ai' ); ?></p>

			<label class="vmia-label"><?php esc_html_e( 'Subject / idea', 'vm-image-ai' ); ?></label>
			<input type="text" id="vmia-subject" class="vmia-input" placeholder="<?php esc_attr_e( 'e.g. Hyperlocal grocery delivery in an Indian city', 'vm-image-ai' ); ?>">

			<div class="vmia-row">
				<div style="flex: 1;">
					<label class="vmia-label"><?php esc_html_e( 'Media type', 'vm-image-ai' ); ?></label>
					<select id="vmia-media-type" class="vmia-input">
						<option value="image"><?php esc_html_e( 'Image', 'vm-image-ai' ); ?></option>
						<option value="video"><?php esc_html_e( 'Video (OmniRoute)', 'vm-image-ai' ); ?></option>
					</select>
				</div>
				<div style="flex: 2;">
					<label class="vmia-label"><?php esc_html_e( 'Attach to post (optional)', 'vm-image-ai' ); ?></label>
					<select id="vmia-post" class="vmia-input">
						<option value="0"><?php esc_html_e( '— none —', 'vm-image-ai' ); ?></option>
						<?php foreach ( $posts as $p ) : ?>
							<option value="<?php echo (int) $p->ID; ?>"><?php echo esc_html( get_the_title( $p ) ? get_the_title( $p ) : '#' . $p->ID ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div id="vmia-image-options">
				<div class="vmia-row">
					<div>
						<label class="vmia-label"><?php esc_html_e( 'Target size', 'vm-image-ai' ); ?></label>
						<select id="vmia-mode" class="vmia-input">
							<option value="featured"><?php printf( esc_html__( 'SEO Optimized (OG) — %d×%d', 'vm-image-ai' ), (int) VMIA_Settings::get( 'featured_w' ), (int) VMIA_Settings::get( 'featured_h' ) ); ?></option>
							<option value="blog"><?php printf( esc_html__( 'Blog Content — Max %d px wide', 'vm-image-ai' ), (int) VMIA_Settings::get( 'blog_max_w' ) ); ?></option>
						</select>
					</div>
					<div class="vmia-check">
						<label><input type="checkbox" id="vmia-featured" checked> <?php esc_html_e( 'Set as featured', 'vm-image-ai' ); ?></label>
					</div>
				</div>
			</div>

			<button class="vmia-btn vmia-btn-primary vmia-mt" id="vmia-generate"><?php esc_html_e( 'Generate & Import', 'vm-image-ai' ); ?></button>
			<p class="vmia-muted"><?php esc_html_e( 'Falls through your image engine chain until one succeeds, then writes alt / title / caption / description automatically.', 'vm-image-ai' ); ?></p>
		</div>

		<div class="vmia-card">
			<p class="vmia-card-title"><?php esc_html_e( 'Result', 'vm-image-ai' ); ?></p>
			<div id="vmia-result" class="vmia-result">
				<div class="vmia-empty">
					<span class="vmia-empty-ico">🖼</span>
					<p><?php esc_html_e( 'Your generated image and its SEO metadata will appear here.', 'vm-image-ai' ); ?></p>
				</div>
			</div>
		</div>
	</div>

	<div id="vmia-toast" class="vmia-toast"></div>

	<div id="vmia-gen-progress" class="vmia-progress" hidden>
		<div class="vmia-progress-bar"><span></span></div>
		<p class="vmia-progress-label"></p>
	</div>
</div>
