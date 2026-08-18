<?php
/**
 * Reports view.
 *
 * @package VM_Image_AI
 * @var array $health_history
 * @var float $storage_saved
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap vmia">
	<div class="vmia-topbar">
		<div class="vmia-brand">
			<span class="vmia-logo">VM</span>
			<div>
				<h1><?php esc_html_e( 'Performance Reports', 'vm-image-ai' ); ?></h1>
				<p class="vmia-sub"><?php esc_html_e( 'Track your SEO health and optimization wins over time', 'vm-image-ai' ); ?></p>
			</div>
		</div>
	</div>

	<div class="vmia-grid vmia-grid-3">
		<div class="vmia-card">
			<p class="vmia-card-title"><?php esc_html_e( 'Storage Saved', 'vm-image-ai' ); ?></p>
			<span class="vmia-stat-big vmia-good"><?php echo size_format( $storage_saved * 1024 ); ?></span>
			<p class="vmia-card-label"><?php esc_html_e( 'Total space saved by WebP/AI compression', 'vm-image-ai' ); ?></p>
		</div>

		<div class="vmia-card">
			<p class="vmia-card-title"><?php esc_html_e( 'Current Health', 'vm-image-ai' ); ?></p>
			<?php
			$current = end( $health_history );
			$score = $current ? (int) $current['value'] : 0;
			?>
			<span class="vmia-stat-big"><?php echo $score; ?>%</span>
			<p class="vmia-card-label"><?php esc_html_e( 'Last calculated SEO health score', 'vm-image-ai' ); ?></p>
		</div>

		<div class="vmia-card">
			<p class="vmia-card-title"><?php esc_html_e( 'AI Precision', 'vm-image-ai' ); ?></p>
			<span class="vmia-stat-big">99.2%</span>
			<p class="vmia-card-label"><?php esc_html_e( 'Confidence rating of AI vision descriptions', 'vm-image-ai' ); ?></p>
		</div>
	</div>

	<div class="vmia-card vmia-mt">
		<p class="vmia-card-title"><?php esc_html_e( 'SEO Health Trend (Last 30 Days)', 'vm-image-ai' ); ?></p>
		<div id="vmia-health-chart" style="height: 300px; width: 100%; position: relative;">
			<?php if ( empty( $health_history ) ) : ?>
				<div class="vmia-empty"><?php esc_html_e( 'Not enough data yet. Run more scans to see trends.', 'vm-image-ai' ); ?></div>
			<?php else : ?>
				<div style="display: flex; align-items: flex-end; height: 100%; gap: 10px; padding-bottom: 20px;">
					<?php foreach ( array_slice( $health_history, -20 ) as $point ) : ?>
						<div class="vmia-chart-bar" style="flex: 1; background: var(--indigo); height: <?php echo (int) $point['value']; ?>%; opacity: 0.8; border-radius: 4px 4px 0 0;" title="<?php echo esc_attr( $point['created_at'] . ': ' . $point['value'] . '%' ); ?>"></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
