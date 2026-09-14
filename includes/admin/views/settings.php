<?php
/**
 * Settings view.
 *
 * @package VM_Image_AI
 * @var array $s Current settings.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Small helpers to render controls bound to a settings key (data-key).
 */
$text = function ( $key, $val, $ph = '', $type = 'text' ) {
	printf(
		'<input class="vmia-input" type="%s" data-key="%s" value="%s" placeholder="%s">',
		esc_attr( $type ),
		esc_attr( $key ),
		esc_attr( is_scalar( $val ) ? $val : '' ),
		esc_attr( $ph )
	);
};
$check = function ( $key, $val, $label ) {
	printf(
		'<label class="vmia-switch"><input type="checkbox" data-key="%s" %s><span></span>%s</label>',
		esc_attr( $key ),
		checked( (bool) $val, true, false ),
		esc_html( $label )
	);
};
$list = function ( $key, $arr ) {
	printf(
		'<input class="vmia-input" type="text" data-key="%s" data-list="1" value="%s">',
		esc_attr( $key ),
		esc_attr( implode( ', ', (array) $arr ) )
	);
};
?>
<div class="wrap vmia" data-theme="<?php echo esc_attr( $s['theme'] ?? 'light' ); ?>">
	<div class="vmia-topbar">
		<div class="vmia-brand">
			<span class="vmia-logo">VM</span>
			<div>
				<h1><?php esc_html_e( 'Settings', 'vm-image-ai' ); ?></h1>
				<p class="vmia-sub"><?php esc_html_e( 'Configure the free AI + image engine stack and optimisation rules', 'vm-image-ai' ); ?></p>
			</div>
		</div>
		<div class="vmia-actions">
			<div class="vmia-theme-toggle">
				<button type="button" class="vmia-theme-btn <?php echo ( $s['theme'] ?? 'light' ) === 'light' ? 'active' : ''; ?>" data-theme-set="light" title="<?php esc_attr_e( 'Light mode', 'vm-image-ai' ); ?>">☀️ Light</button>
				<button type="button" class="vmia-theme-btn <?php echo ( $s['theme'] ?? 'light' ) === 'dark' ? 'active' : ''; ?>" data-theme-set="dark" title="<?php esc_attr_e( 'Dark mode', 'vm-image-ai' ); ?>">🌙 Dark</button>
				<button type="button" class="vmia-theme-btn <?php echo ( $s['theme'] ?? 'light' ) === 'auto' ? 'active' : ''; ?>" data-theme-set="auto" title="<?php esc_attr_e( 'System preference', 'vm-image-ai' ); ?>">⚙️ Auto</button>
			</div>
			<button class="vmia-btn vmia-btn-ghost" id="vmia-sync"><?php esc_html_e( '↻ Sync live models', 'vm-image-ai' ); ?></button>
			<button class="vmia-btn vmia-btn-primary" id="vmia-save"><?php esc_html_e( 'Save settings', 'vm-image-ai' ); ?></button>
		</div>
	</div>

	<div class="vmia-grid vmia-grid-2">

		<!-- Text / vision AI -->
		<div class="vmia-card">
			<p class="vmia-card-title"><?php esc_html_e( 'AI text engine (fallback chain)', 'vm-image-ai' ); ?></p>
			<label class="vmia-label"><?php esc_html_e( 'Provider order', 'vm-image-ai' ); ?> <em><?php esc_html_e( '(omniroute, aipuffer, openai, gemini, openrouter, xai)', 'vm-image-ai' ); ?></em></label>
			<?php $list( 'ai_order', $s['ai_order'] ); ?>

			<div class="vmia-subcard">
				<p class="vmia-subcard-title"><?php esc_html_e( 'OmniRoute (Self-hosted)', 'vm-image-ai' ); ?></p>
				<div class="vmia-row">
					<div style="flex:1">
						<label class="vmia-label"><?php esc_html_e( 'Text Model', 'vm-image-ai' ); ?></label>
						<input class="vmia-input" type="text" data-key="omniroute_text_model" value="<?php echo esc_attr( $s['omniroute_text_model'] ?? 'openai/gpt-4o-mini' ); ?>" list="vmia-list-omniroute">
					</div>
					<div style="flex:1">
						<label class="vmia-label"><?php esc_html_e( 'Vision Model', 'vm-image-ai' ); ?></label>
						<input class="vmia-input" type="text" data-key="omniroute_vision_model" value="<?php echo esc_attr( $s['omniroute_vision_model'] ?? 'openai/gpt-4o-mini' ); ?>" list="vmia-list-omniroute">
					</div>
				</div>
				<p class="vmia-muted" style="margin-bottom:0"><?php esc_html_e( 'Configuration is shared with the Image/Video section below.', 'vm-image-ai' ); ?></p>
			</div>

			<label class="vmia-label">AI Puffer — <?php esc_html_e( 'REST base', 'vm-image-ai' ); ?></label>
			<?php $text( 'aipuffer_base', $s['aipuffer_base'], home_url( '/wp-json/aipkit/v1' ) ); ?>
			<label class="vmia-label">AI Puffer — <?php esc_html_e( 'REST API key', 'vm-image-ai' ); ?></label>
			<?php $text( 'aipuffer_key', $s['aipuffer_key'], 'sk-...', 'password' ); ?>

			<?php if ( post_type_exists( 'aipkit_chatbot' ) ) : ?>
				<p class="vmia-muted" style="color:var(--good); background:rgba(34,197,94,0.1); padding:8px; border-radius:8px; margin-top:10px;">
					<span class="vmia-sev sev-1" style="background:var(--good)"></span>
					<?php esc_html_e( 'Local AI Puffer (AIPKit) detected on this site. REST API key might not be required.', 'vm-image-ai' ); ?>
				</p>
			<?php endif; ?>

			<label class="vmia-label">AI Puffer — <?php esc_html_e( 'Bot', 'vm-image-ai' ); ?></label>
			<div class="vmia-row" style="align-items:center;gap:8px">
				<input class="vmia-input" type="text" id="vmia-aipuffer-bot-id" data-key="aipuffer_bot_id"
					value="<?php echo esc_attr( $s['aipuffer_bot_id'] ); ?>"
					list="vmia-list-aipuffer" placeholder="<?php esc_attr_e( 'Bot ID (blank = AIPKit default)', 'vm-image-ai' ); ?>">
				<button type="button" class="vmia-btn vmia-btn-ghost" id="vmia-aipuffer-test"><?php esc_html_e( 'Test connection', 'vm-image-ai' ); ?></button>
			</div>

			<label class="vmia-label">OpenAI — <?php esc_html_e( 'API key', 'vm-image-ai' ); ?></label>
			<?php $text( 'openai_key', $s['openai_key'], 'sk-...', 'password' ); ?>
			<label class="vmia-label">OpenAI — <?php esc_html_e( 'Model', 'vm-image-ai' ); ?></label>
			<input class="vmia-input" type="text" data-key="openai_model" value="<?php echo esc_attr( $s['openai_model'] ); ?>" list="vmia-list-openai">

			<label class="vmia-label">Gemini — <?php esc_html_e( 'API key', 'vm-image-ai' ); ?></label>
			<?php $text( 'gemini_key', $s['gemini_key'], 'AIza...', 'password' ); ?>
			<label class="vmia-label">Gemini — <?php esc_html_e( 'Model', 'vm-image-ai' ); ?></label>
			<input class="vmia-input" type="text" data-key="gemini_model" value="<?php echo esc_attr( $s['gemini_model'] ); ?>" list="vmia-list-gemini">

			<label class="vmia-label">OpenRouter — <?php esc_html_e( 'API key', 'vm-image-ai' ); ?></label>
			<?php $text( 'openrouter_key', $s['openrouter_key'], 'sk-or-...', 'password' ); ?>
			<label class="vmia-label">OpenRouter — <?php esc_html_e( 'Model (or "auto" for live sync)', 'vm-image-ai' ); ?></label>
			<input class="vmia-input" type="text" data-key="openrouter_model" value="<?php echo esc_attr( $s['openrouter_model'] ); ?>" list="vmia-list-openrouter">

			<label class="vmia-label">xAI (Grok) — <?php esc_html_e( 'API key', 'vm-image-ai' ); ?></label>
			<?php $text( 'xai_key', $s['xai_key'], 'xai-...', 'password' ); ?>
			<label class="vmia-label">xAI (Grok) — <?php esc_html_e( 'Model', 'vm-image-ai' ); ?></label>
			<input class="vmia-input" type="text" data-key="xai_model" value="<?php echo esc_attr( $s['xai_model'] ); ?>" list="vmia-list-xai">

			<!-- Dynamic Lists -->
			<datalist id="vmia-list-aipuffer"></datalist>
			<datalist id="vmia-list-openai"></datalist>
			<datalist id="vmia-list-gemini"></datalist>
			<datalist id="vmia-list-openrouter"></datalist>
			<datalist id="vmia-list-xai"></datalist>
			<datalist id="vmia-list-omniroute"></datalist>
		</div>

		<!-- Vision -->
		<div class="vmia-card">
			<p class="vmia-card-title"><?php esc_html_e( 'Vision engine (image → description)', 'vm-image-ai' ); ?></p>
			<label class="vmia-label"><?php esc_html_e( 'Vision provider order', 'vm-image-ai' ); ?> <em>(gemini, openrouter)</em></label>
			<?php $list( 'vision_order', $s['vision_order'] ); ?>
			<label class="vmia-label">Gemini <?php esc_html_e( 'vision model', 'vm-image-ai' ); ?></label>
			<input class="vmia-input" type="text" data-key="gemini_vision_model" value="<?php echo esc_attr( $s['gemini_vision_model'] ); ?>" list="vmia-list-gemini">
			<label class="vmia-label">OpenRouter <?php esc_html_e( 'vision model (or "auto")', 'vm-image-ai' ); ?></label>
			<input class="vmia-input" type="text" data-key="openrouter_vision" value="<?php echo esc_attr( $s['openrouter_vision'] ); ?>" list="vmia-list-openrouter">
			<p class="vmia-muted"><?php esc_html_e( 'Vision lets the plugin actually see each image so alt text is accurate, not guessed from the filename.', 'vm-image-ai' ); ?></p>

			<p class="vmia-card-title vmia-mt"><?php esc_html_e( 'SEO writer', 'vm-image-ai' ); ?></p>
			<div class="vmia-row">
				<div><label class="vmia-label"><?php esc_html_e( 'Alt min length', 'vm-image-ai' ); ?></label><?php $text( 'alt_min_len', $s['alt_min_len'], '12', 'number' ); ?></div>
				<div><label class="vmia-label"><?php esc_html_e( 'Alt max length', 'vm-image-ai' ); ?></label><?php $text( 'alt_max_len', $s['alt_max_len'], '125', 'number' ); ?></div>
			</div>
			<label class="vmia-label"><?php esc_html_e( 'Language hint', 'vm-image-ai' ); ?></label>
			<?php $text( 'locale_hint', $s['locale_hint'], 'en' ); ?>
			<label class="vmia-label"><?php esc_html_e( 'Brand / business context', 'vm-image-ai' ); ?></label>
			<textarea class="vmia-input" data-key="brand_context" rows="3" placeholder="<?php esc_attr_e( 'Injected into image + SEO prompts', 'vm-image-ai' ); ?>"><?php echo esc_textarea( $s['brand_context'] ); ?></textarea>
		</div>

		<!-- Image engine -->
		<div class="vmia-card">
			<p class="vmia-card-title"><?php esc_html_e( 'Image & Video generation engine', 'vm-image-ai' ); ?></p>
			<label class="vmia-label"><?php esc_html_e( 'Image Provider order', 'vm-image-ai' ); ?> <em><?php esc_html_e( '(omniroute, aipuffer, google, openai, pollinations, huggingface, cloudflare, google_search, pexels)', 'vm-image-ai' ); ?></em></label>
			<?php $list( 'image_order', $s['image_order'] ); ?>

			<div class="vmia-subcard">
				<p class="vmia-subcard-title"><?php esc_html_e( 'OmniRoute (Self-hosted)', 'vm-image-ai' ); ?></p>
				<label class="vmia-label"><?php esc_html_e( 'Base URL', 'vm-image-ai' ); ?></label>
				<?php $text( 'omniroute_base', $s['omniroute_base'] ?? '', 'http://localhost:20128/v1' ); ?>
				<label class="vmia-label"><?php esc_html_e( 'API Key (Optional)', 'vm-image-ai' ); ?></label>
				<?php $text( 'omniroute_key', $s['omniroute_key'] ?? '', 'sk-...', 'password' ); ?>
				<div class="vmia-row">
					<div style="flex:1">
						<label class="vmia-label"><?php esc_html_e( 'Image Model', 'vm-image-ai' ); ?></label>
						<input class="vmia-input" type="text" data-key="omniroute_image_model" value="<?php echo esc_attr( $s['omniroute_image_model'] ?? 'cheaperinference/grok-imagine' ); ?>" list="vmia-list-omniroute">
					</div>
					<div style="flex:1">
						<label class="vmia-label"><?php esc_html_e( 'Video Model', 'vm-image-ai' ); ?></label>
						<input class="vmia-input" type="text" data-key="omniroute_video_model" value="<?php echo esc_attr( $s['omniroute_video_model'] ?? 'novita/video-model-name' ); ?>" list="vmia-list-omniroute">
					</div>
				</div>
				<p class="vmia-muted"><?php esc_html_e( 'OmniRoute is a unified OpenAI-compatible API for various media providers.', 'vm-image-ai' ); ?></p>
			</div>

			<label class="vmia-label">Google Imagen — <?php esc_html_e( 'Model (or "auto")', 'vm-image-ai' ); ?> <em>(uses Gemini API key)</em></label>
			<input class="vmia-input" type="text" data-key="gemini_image_model" value="<?php echo esc_attr( $s['gemini_image_model'] ); ?>" list="vmia-list-gemini" placeholder="auto">

			<label class="vmia-label">OpenAI DALL-E — <?php esc_html_e( 'Model (or "auto")', 'vm-image-ai' ); ?> <em>(uses OpenAI API key)</em></label>
			<input class="vmia-input" type="text" data-key="openai_image_model" value="<?php echo esc_attr( $s['openai_image_model'] ?? 'dall-e-3' ); ?>" list="vmia-list-openai" placeholder="dall-e-3">

			<label class="vmia-label">Pollinations — <?php esc_html_e( 'model', 'vm-image-ai' ); ?></label>
			<?php $text( 'pollinations_model', $s['pollinations_model'], 'flux' ); ?>
			<?php $check( 'pollinations_nologo', $s['pollinations_nologo'], __( 'Request no watermark', 'vm-image-ai' ) ); ?>

			<label class="vmia-label">Hugging Face — <?php esc_html_e( 'API key', 'vm-image-ai' ); ?></label>
			<?php $text( 'huggingface_key', $s['huggingface_key'], 'hf_...', 'password' ); ?>
			<label class="vmia-label">Hugging Face — <?php esc_html_e( 'Model (or "auto")', 'vm-image-ai' ); ?></label>
			<input class="vmia-input" type="text" data-key="huggingface_model" value="<?php echo esc_attr( $s['huggingface_model'] ); ?>" list="vmia-list-huggingface" placeholder="auto">
			<datalist id="vmia-list-huggingface"></datalist>

			<label class="vmia-label">Cloudflare — <?php esc_html_e( 'Account ID', 'vm-image-ai' ); ?></label>
			<?php $text( 'cloudflare_account_id', $s['cloudflare_account_id'], '' ); ?>
			<label class="vmia-label">Cloudflare — <?php esc_html_e( 'API Token', 'vm-image-ai' ); ?></label>
			<?php $text( 'cloudflare_key', $s['cloudflare_key'], '', 'password' ); ?>
			<label class="vmia-label">Cloudflare — <?php esc_html_e( 'Model (or "auto")', 'vm-image-ai' ); ?></label>
			<input class="vmia-input" type="text" data-key="cloudflare_model" value="<?php echo esc_attr( $s['cloudflare_model'] ); ?>" list="vmia-list-cloudflare" placeholder="auto">
			<datalist id="vmia-list-cloudflare"></datalist>

			<label class="vmia-label">Pexels — <?php esc_html_e( 'API key', 'vm-image-ai' ); ?></label>
			<?php $text( 'pexels_key', $s['pexels_key'], '', 'password' ); ?>

			<label class="vmia-label">Google Search (Stock) — <?php esc_html_e( 'API key', 'vm-image-ai' ); ?></label>
			<?php $text( 'google_search_key', $s['google_search_key'], '', 'password' ); ?>
			<label class="vmia-label">Google Search (Stock) — <?php esc_html_e( 'Search Engine ID (CX)', 'vm-image-ai' ); ?></label>
			<?php $text( 'google_search_cx', $s['google_search_cx'], '' ); ?>

			<label class="vmia-label">AI Puffer — <?php esc_html_e( 'image sub-path', 'vm-image-ai' ); ?></label>
			<?php $text( 'aipuffer_img_path', $s['aipuffer_img_path'], '/image' ); ?>

			<label class="vmia-label">AI Puffer — <?php esc_html_e( 'Internal engine', 'vm-image-ai' ); ?></label>
			<select class="vmia-input" data-key="aipuffer_img_engine">
				<option value="openai" <?php selected( $s['aipuffer_img_engine'], 'openai' ); ?>>OpenAI (DALL-E)</option>
				<option value="google" <?php selected( $s['aipuffer_img_engine'], 'google' ); ?>>Google (Imagen)</option>
				<option value="azure" <?php selected( $s['aipuffer_img_engine'], 'azure' ); ?>>Azure OpenAI</option>
				<option value="replicate" <?php selected( $s['aipuffer_img_engine'], 'replicate' ); ?>>Replicate</option>
			</select>

			<label class="vmia-label">ComfyUI — <?php esc_html_e( 'server URL', 'vm-image-ai' ); ?></label>
			<?php $text( 'comfyui_base', $s['comfyui_base'], 'http://127.0.0.1:8188' ); ?>
			<div class="vmia-row">
				<div><label class="vmia-label"><?php esc_html_e( 'Checkpoint', 'vm-image-ai' ); ?></label><?php $text( 'comfyui_ckpt', $s['comfyui_ckpt'], 'sd_xl_base_1.0.safetensors' ); ?></div>
				<div><label class="vmia-label"><?php esc_html_e( 'Steps', 'vm-image-ai' ); ?></label><?php $text( 'comfyui_steps', $s['comfyui_steps'], '25', 'number' ); ?></div>
			</div>
		</div>

		<!-- Sizing + automation -->
		<div class="vmia-card">
			<p class="vmia-card-title"><?php esc_html_e( 'Sizing & optimisation', 'vm-image-ai' ); ?></p>
			<div class="vmia-row">
				<div><label class="vmia-label"><?php esc_html_e( 'Featured width', 'vm-image-ai' ); ?></label><?php $text( 'featured_w', $s['featured_w'], '1200', 'number' ); ?></div>
				<div><label class="vmia-label"><?php esc_html_e( 'Featured height', 'vm-image-ai' ); ?></label><?php $text( 'featured_h', $s['featured_h'], '630', 'number' ); ?></div>
			</div>
			<label class="vmia-label"><?php esc_html_e( 'Blog image max width', 'vm-image-ai' ); ?></label>
			<?php $text( 'blog_max_w', $s['blog_max_w'], '1600', 'number' ); ?>
			<?php $check( 'convert_webp', $s['convert_webp'], __( 'Convert to WebP', 'vm-image-ai' ) ); ?>
			<div class="vmia-row">
				<div><label class="vmia-label"><?php esc_html_e( 'WebP quality', 'vm-image-ai' ); ?></label><?php $text( 'webp_quality', $s['webp_quality'], '82', 'number' ); ?></div>
				<div><label class="vmia-label"><?php esc_html_e( 'JPEG quality', 'vm-image-ai' ); ?></label><?php $text( 'jpeg_quality', $s['jpeg_quality'], '82', 'number' ); ?></div>
			</div>
			<div class="vmia-row">
				<div><label class="vmia-label"><?php esc_html_e( 'Flag files over (KB)', 'vm-image-ai' ); ?></label><?php $text( 'max_kb', $s['max_kb'], '300', 'number' ); ?></div>
				<div><label class="vmia-label"><?php esc_html_e( 'Flag dimension over (px)', 'vm-image-ai' ); ?></label><?php $text( 'max_dim', $s['max_dim'], '2560', 'number' ); ?></div>
			</div>

			<p class="vmia-card-title vmia-mt"><?php esc_html_e( 'Automation', 'vm-image-ai' ); ?></p>
			<?php $check( 'auto_scan', $s['auto_scan'], __( 'Scheduled library scans', 'vm-image-ai' ) ); ?>
			<label class="vmia-label"><?php esc_html_e( 'Scan frequency', 'vm-image-ai' ); ?></label>
			<select class="vmia-input" data-key="scan_frequency">
				<?php foreach ( array( 'hourly', 'twicedaily', 'daily' ) as $f ) : ?>
					<option value="<?php echo esc_attr( $f ); ?>" <?php selected( $s['scan_frequency'], $f ); ?>><?php echo esc_html( $f ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php $check( 'auto_alt_on_upload', $s['auto_alt_on_upload'], __( 'Auto-write SEO metadata on upload', 'vm-image-ai' ) ); ?>
			<?php $check( 'auto_resize_upload', $s['auto_resize_upload'], __( 'Auto-optimise images on upload', 'vm-image-ai' ) ); ?>
			<?php $check( 'autopilot_enabled', $s['autopilot_enabled'], __( 'Enable Background Autopilot (Auto-Fix issues)', 'vm-image-ai' ) ); ?>
			<label class="vmia-label"><?php esc_html_e( 'God Fix batch size', 'vm-image-ai' ); ?></label>
			<?php $text( 'god_fix_batch', $s['god_fix_batch'], '15', 'number' ); ?>
			<label class="vmia-label"><?php esc_html_e( 'Post types to audit', 'vm-image-ai' ); ?> <em>(post, page)</em></label>
			<?php $list( 'scan_post_types', $s['scan_post_types'] ); ?>

			<p class="vmia-card-title vmia-mt"><?php esc_html_e( 'Interface Theme', 'vm-image-ai' ); ?></p>
			<label class="vmia-label"><?php esc_html_e( 'Color Mode', 'vm-image-ai' ); ?></label>
			<select class="vmia-input" data-key="theme" id="vmia-setting-theme">
				<option value="light" <?php selected( $s['theme'] ?? 'light', 'light' ); ?>><?php esc_html_e( 'Light (Seamless WP Admin)', 'vm-image-ai' ); ?></option>
				<option value="dark" <?php selected( $s['theme'] ?? 'light', 'dark' ); ?>><?php esc_html_e( 'Dark (Titan Dark)', 'vm-image-ai' ); ?></option>
				<option value="auto" <?php selected( $s['theme'] ?? 'light', 'auto' ); ?>><?php esc_html_e( 'Auto (System Preference)', 'vm-image-ai' ); ?></option>
			</select>

			<p class="vmia-card-title vmia-mt"><?php esc_html_e( 'GitHub Online Updates', 'vm-image-ai' ); ?></p>
			<div class="vmia-subcard" style="margin-top:8px;">
				<div class="vmia-meta-line" style="padding-top:0;">
					<b><?php esc_html_e( 'Installed', 'vm-image-ai' ); ?>:</b>
					<span>v<?php echo esc_html( VMIA_VERSION ); ?></span>
				</div>
				<div class="vmia-meta-line">
					<b><?php esc_html_e( 'Repository', 'vm-image-ai' ); ?>:</b>
					<span><a href="https://github.com/vmai-plugins/vm-image-ai" target="_blank" style="color:var(--indigo-2); text-decoration:none;">vmai-plugins/vm-image-ai ↗</a></span>
				</div>
				<label class="vmia-label" style="margin-top:10px;"><?php esc_html_e( 'GitHub Token (Optional for private repos/rate limit)', 'vm-image-ai' ); ?></label>
				<?php $text( 'github_token', $s['github_token'] ?? '', 'ghp_...', 'password' ); ?>
				<div style="margin-top:12px; display:flex; gap:10px; align-items:center;">
					<button type="button" class="vmia-btn vmia-btn-ghost vmia-btn-sm" id="vmia-check-github-update"><?php esc_html_e( '🔄 Check for Updates Now', 'vm-image-ai' ); ?></button>
				</div>
				<div id="vmia-github-update-result" style="margin-top:10px;"></div>
			</div>
		</div>
	</div>

	<div id="vmia-toast" class="vmia-toast"></div>
</div>
