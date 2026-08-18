<?php
/**
 * Dashboard view.
 *
 * @package VM_Image_AI
 * @var array  $summary
 * @var int    $score
 * @var int    $total
 * @var string $last
 * @var array  $log
 */

defined( 'ABSPATH' ) || exit;

$open   = array_sum( $summary );
$labels = VMIA_Auditor::ISSUES;
$grade  = $score >= 90 ? 'A' : ( $score >= 75 ? 'B' : ( $score >= 55 ? 'C' : ( $score >= 35 ? 'D' : 'F' ) ) );
?>
<div class="wrap vmia">
	<div class="vmia-topbar">
		<div class="vmia-brand">
			<span class="vmia-logo">VM</span>
			<div>
				<h1><?php esc_html_e( 'Image AI', 'vm-image-ai' ); ?></h1>
				<p class="vmia-sub"><?php esc_html_e( 'AI image generation + intelligent Image-SEO command centre', 'vm-image-ai' ); ?></p>
			</div>
		</div>
		<div class="vmia-actions">
			<button class="vmia-btn vmia-btn-ghost" id="vmia-autopilot-run"><?php esc_html_e( '🚀 Run Autopilot', 'vm-image-ai' ); ?></button>
			<button class="vmia-btn vmia-btn-ghost" id="vmia-scan"><?php esc_html_e( 'Run Full Scan', 'vm-image-ai' ); ?></button>
			<button class="vmia-btn vmia-btn-primary" id="vmia-godfix"><?php esc_html_e( '⚡ God Fix', 'vm-image-ai' ); ?></button>
		</div>
	</div>

	<div class="vmia-grid vmia-grid-3">
		<div class="vmia-card vmia-score">
			<div class="vmia-ring" style="--score: <?php echo (int) $score; ?>">
				<div class="vmia-ring-inner">
					<span class="vmia-grade"><?php echo esc_html( $grade ); ?></span>
					<span class="vmia-score-num"><?php echo (int) $score; ?><small>/100</small></span>
				</div>
			</div>
			<p class="vmia-card-label"><?php esc_html_e( 'Image-SEO Health', 'vm-image-ai' ); ?></p>
		</div>

		<div class="vmia-card">
			<p class="vmia-card-title"><?php esc_html_e( 'Missing Assets', 'vm-image-ai' ); ?></p>
			<?php
			$broken_count = $summary['broken_file'] ?? 0;
			$missing_feat = $summary['no_featured'] ?? 0;
			$no_images    = $summary['no_images'] ?? 0;
			$total_crit   = $broken_count + $missing_feat + $no_images;
			?>
			<span class="vmia-stat-big <?php echo $total_crit ? 'vmia-danger' : ''; ?>"><?php echo (int) $total_crit; ?></span>
			<p class="vmia-card-label"><?php esc_html_e( 'Critical missing or broken assets', 'vm-image-ai' ); ?></p>

			<div class="vmia-mt">
				<div class="vmia-muted"><?php printf( esc_html__( 'Blank/Broken: %d', 'vm-image-ai' ), $broken_count ); ?></div>
				<div class="vmia-muted"><?php printf( esc_html__( 'No Featured: %d', 'vm-image-ai' ), $missing_feat ); ?></div>
				<div class="vmia-muted"><?php printf( esc_html__( 'Pure Text Posts: %d', 'vm-image-ai' ), $no_images ); ?></div>
			</div>
		</div>

		<div class="vmia-card">
			<p class="vmia-card-title"><?php esc_html_e( 'Engine status', 'vm-image-ai' ); ?></p>
			<?php
			$autopilot = VMIA_Settings::get( 'autopilot_enabled' );
			$providers = array(
				'Autopilot'  => $autopilot,
				'OpenAI'     => (bool) VMIA_Settings::get( 'openai_key' ),
				'Gemini'     => (bool) VMIA_Settings::get( 'gemini_key' ),
				'OpenRouter' => (bool) VMIA_Settings::get( 'openrouter_key' ),
				'Google Imagen' => (bool) VMIA_Settings::get( 'gemini_key' ),
				'Pollinations' => true,
				'Hugging Face' => (bool) VMIA_Settings::get( 'huggingface_key' ),
				'Cloudflare' => (bool) VMIA_Settings::get( 'cloudflare_key' ),
				'ComfyUI'    => (bool) VMIA_Settings::get( 'comfyui_base' ),
				'Pexels'     => (bool) VMIA_Settings::get( 'pexels_key' ),
				'Google Search' => (bool) VMIA_Settings::get( 'google_search_key' ),
			);

			// AI Puffer gets its own row: a truthy key is not proof of a
			// working bot, so this reflects the last real /aipuffer/test
			// result (run from Settings, or from this dashboard).
			$key_set   = (bool) VMIA_Settings::get( 'aipuffer_key' );
			$ap_status = get_transient( 'vmia_aipuffer_status' );
			if ( ! $key_set ) {
				$ap_state = 'off';
				$ap_label = esc_html__( 'off', 'vm-image-ai' );
			} elseif ( ! is_array( $ap_status ) ) {
				$ap_state = 'unknown';
				$ap_label = esc_html__( 'untested', 'vm-image-ai' );
			} elseif ( empty( $ap_status['ok'] ) ) {
				$ap_state = 'off';
				$ap_label = esc_html__( 'unreachable', 'vm-image-ai' );
			} elseif ( false === ( $ap_status['bot_valid'] ?? null ) ) {
				$ap_state = 'off';
				$ap_label = esc_html__( 'bot not found', 'vm-image-ai' );
			} else {
				$ap_state = 'on';
				$ap_label = esc_html__( 'ready', 'vm-image-ai' );
			}
			?>
			<div class="vmia-engine-row">
				<span class="vmia-dot <?php echo esc_attr( $ap_state ); ?>"></span>
				<span>AI Puffer</span>
				<span class="vmia-engine-state"><?php echo $ap_label; ?></span>
			</div>
			<?php foreach ( $providers as $name => $on ) : ?>
				<div class="vmia-engine-row">
					<span class="vmia-dot <?php echo $on ? 'on' : 'off'; ?>"></span>
					<span><?php echo esc_html( $name ); ?></span>
					<span class="vmia-engine-state"><?php echo $on ? esc_html__( 'ready', 'vm-image-ai' ) : esc_html__( 'off', 'vm-image-ai' ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="vmia-grid vmia-grid-2">
		<div class="vmia-card">
			<p class="vmia-card-title"><?php esc_html_e( 'Issue breakdown', 'vm-image-ai' ); ?></p>
			<?php if ( $open ) : ?>
				<table class="vmia-table">
					<?php
					arsort( $summary );
					foreach ( $summary as $code => $n ) :
						$sev = $labels[ $code ][0] ?? 1;
						?>
						<tr>
							<td><span class="vmia-sev sev-<?php echo (int) $sev; ?>"></span><?php echo esc_html( $labels[ $code ][1] ?? $code ); ?></td>
							<td class="vmia-num"><?php echo (int) $n; ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . VMIA_SLUG . '-audit' ) ); ?>" class="vmia-btn vmia-btn-ghost vmia-mt"><?php esc_html_e( 'Open Auditor', 'vm-image-ai' ); ?></a>
			<?php else : ?>
				<div class="vmia-empty">
					<span class="vmia-empty-ico">✓</span>
					<p><?php echo $total ? esc_html__( 'No open issues. Your images are clean.', 'vm-image-ai' ) : esc_html__( 'Run a scan to analyse your library.', 'vm-image-ai' ); ?></p>
				</div>
			<?php endif; ?>
		</div>

		<div class="vmia-card">
			<p class="vmia-card-title"><?php esc_html_e( 'Recent activity', 'vm-image-ai' ); ?></p>
			<div class="vmia-log">
				<?php if ( $log ) : ?>
					<?php foreach ( $log as $entry ) : ?>
						<div class="vmia-log-row">
							<span class="vmia-log-kind kind-<?php echo esc_attr( $entry['kind'] ); ?>"><?php echo esc_html( $entry['kind'] ); ?></span>
							<span class="vmia-log-msg"><?php echo esc_html( $entry['message'] ); ?></span>
							<span class="vmia-log-time"><?php echo esc_html( human_time_diff( strtotime( $entry['created_at'] ), current_time( 'timestamp' ) ) ); ?></span>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="vmia-muted"><?php esc_html_e( 'No activity yet.', 'vm-image-ai' ); ?></p>
				<?php endif; ?>
			</div>
			<p class="vmia-muted vmia-mt"><?php echo $last ? esc_html( sprintf( __( 'Last scan: %s', 'vm-image-ai' ), $last ) ) : esc_html__( 'Never scanned.', 'vm-image-ai' ); ?></p>
		</div>
	</div>

	<div id="vmia-toast" class="vmia-toast"></div>
	<div id="vmia-godfix-progress" class="vmia-progress" hidden>
		<div class="vmia-progress-bar"><span></span></div>
		<p class="vmia-progress-label"></p>
	</div>
</div>
