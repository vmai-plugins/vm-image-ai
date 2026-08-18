<?php
/**
 * Auditor view.
 *
 * @package VM_Image_AI
 * @var array $issues
 * @var array $labels
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap vmia">
	<div class="vmia-topbar">
		<div class="vmia-brand">
			<span class="vmia-logo">VM</span>
			<div>
				<h1><?php esc_html_e( 'SEO Auditor', 'vm-image-ai' ); ?></h1>
				<p class="vmia-sub"><?php esc_html_e( 'Every detected image-SEO gap, ranked by severity — fix one or fix all', 'vm-image-ai' ); ?></p>
			</div>
		</div>
		<div class="vmia-actions">
			<select id="vmia-filter-type" class="vmia-input vmia-input-sm" style="width:200px; margin-right:10px;">
				<option value=""><?php esc_html_e( 'All Issue Types', 'vm-image-ai' ); ?></option>
				<?php foreach ( $labels as $code => $data ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $data[1] ); ?></option>
				<?php endforeach; ?>
			</select>
			<button class="vmia-btn vmia-btn-ghost" id="vmia-scan"><?php esc_html_e( 'Re-scan', 'vm-image-ai' ); ?></button>
			<button class="vmia-btn vmia-btn-primary" id="vmia-godfix"><?php esc_html_e( '⚡ God Fix all', 'vm-image-ai' ); ?></button>
		</div>
	</div>

	<!-- Bulk Actions Bar -->
	<div id="vmia-bulk-bar" class="vmia-bulk-bar hide">
		<div class="vmia-bulk-info">
			<span id="vmia-selected-count">0</span> <?php esc_html_e( 'items selected', 'vm-image-ai' ); ?>
		</div>
		<div class="vmia-bulk-actions">
			<select id="vmia-bulk-action" class="vmia-input vmia-input-sm" style="width:180px;">
				<option value=""><?php esc_html_e( 'Bulk Action', 'vm-image-ai' ); ?></option>
				<option value="fix"><?php esc_html_e( 'AI Metadata Fix', 'vm-image-ai' ); ?></option>
				<option value="optimize"><?php esc_html_e( 'Optimize / WebP', 'vm-image-ai' ); ?></option>
			</select>
			<button id="vmia-bulk-apply" class="vmia-btn vmia-btn-primary vmia-btn-sm"><?php esc_html_e( 'Apply', 'vm-image-ai' ); ?></button>
			<button id="vmia-bulk-cancel" class="vmia-btn vmia-btn-ghost vmia-btn-sm"><?php esc_html_e( 'Cancel', 'vm-image-ai' ); ?></button>
		</div>
	</div>

	<div class="vmia-card">
		<?php if ( empty( $issues ) ) : ?>
			<div class="vmia-empty">
				<span class="vmia-empty-ico">✓</span>
				<p><?php esc_html_e( 'No open issues. Run a scan if you have added new images.', 'vm-image-ai' ); ?></p>
			</div>
		<?php else : ?>
			<table class="vmia-table vmia-audit-table">
				<thead>
					<tr>
						<th style="width:40px;"><input type="checkbox" id="vmia-select-all"></th>
						<th><?php esc_html_e( 'Sev', 'vm-image-ai' ); ?></th>
						<th><?php esc_html_e( 'Preview', 'vm-image-ai' ); ?></th>
						<th><?php esc_html_e( 'Issue', 'vm-image-ai' ); ?></th>
						<th><?php esc_html_e( 'Detail', 'vm-image-ai' ); ?></th>
						<th><?php esc_html_e( 'Object', 'vm-image-ai' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $issues as $row ) :
						$sev   = (int) $row['severity'];
						$code  = $row['issue_code'];
						$label = $labels[ $code ][1] ?? $code;
						$oid   = (int) $row['object_id'];
						$thumb = 'attachment' === $row['object_type'] ? wp_get_attachment_image_url( $oid, 'thumbnail' ) : '';
						if ( ! $thumb && (int) $row['post_id'] ) {
							$thumb = get_the_post_thumbnail_url( (int) $row['post_id'], 'thumbnail' );
						}
						$edit  = 'attachment' === $row['object_type']
							? get_edit_post_link( $oid )
							: get_edit_post_link( (int) $row['post_id'] );
						?>
						<tr data-id="<?php echo (int) $row['id']; ?>" data-code="<?php echo esc_attr( $code ); ?>">
							<td><input type="checkbox" class="vmia-row-cb" value="<?php echo (int) $row['id']; ?>"></td>
							<td><span class="vmia-sev sev-<?php echo $sev; ?>" title="<?php echo esc_attr( 'Severity ' . $sev ); ?>"></span></td>
							<td class="vmia-thumb">
								<?php if ( $thumb ) : ?>
									<img src="<?php echo esc_url( $thumb ); ?>" width="44" height="44" style="object-fit: cover;" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0NCIgaGVpZ2h0PSI0NCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiM0NDQiIHN0cm9rZS13aWR0aD0iMSIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48cmVjdCB4PSIzIiB5PSIzIiB3aWR0aD0iMTgiIGhlaWdodD0iMTgiIHJ4PSIyIiByeT0iMiIvPjxjaXJjbGUgY3g9IjguNSIgY3k9IjguNSIgcj0iMS41Ii8+PHBhdGggZD0iTTIxIDE1bC01LTUtNCA0LTQtNC04IDgiLz48L3N2Zz4=';">
								<?php else : ?>
									<span class="vmia-noimg">—</span>
								<?php endif; ?>
							</td>
							<td><strong><?php echo esc_html( $label ); ?></strong></td>
							<td class="vmia-detail"><?php echo esc_html( $row['detail'] ); ?></td>
							<td>
								<?php if ( $edit ) : ?>
									<a href="<?php echo esc_url( $edit ); ?>" target="_blank">#<?php echo esc_html( 'attachment' === $row['object_type'] ? $oid : (int) $row['post_id'] ); ?></a>
								<?php else : ?>
									#<?php echo esc_html( $oid ); ?>
								<?php endif; ?>
							</td>
							<td>
								<div class="vmia-row-actions">
									<button class="vmia-btn vmia-btn-sm vmia-fix" data-id="<?php echo (int) $row['id']; ?>"><?php esc_html_e( 'Fix', 'vm-image-ai' ); ?></button>
									<button class="vmia-btn vmia-btn-sm vmia-btn-ghost vmia-regen" data-id="<?php echo (int) $row['id']; ?>" title="<?php esc_attr_e( 'Regenerate Image', 'vm-image-ai' ); ?>">✨</button>
									<button class="vmia-btn vmia-btn-sm vmia-btn-ghost vmia-undo" data-oid="<?php echo (int) $oid; ?>" title="<?php esc_attr_e( 'Undo last AI change', 'vm-image-ai' ); ?>">↩</button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div id="vmia-toast" class="vmia-toast"></div>
	<div id="vmia-godfix-progress" class="vmia-progress" hidden>
		<div class="vmia-progress-bar"><span></span></div>
		<p class="vmia-progress-label"></p>
	</div>

	<!-- Advanced Fix Modal -->
	<div id="vmia-fix-modal" class="vmia-modal hide" hidden>
		<div class="vmia-modal-content">
			<div class="vmia-modal-header">
				<h3><?php esc_html_e( 'Review AI Suggestion', 'vm-image-ai' ); ?></h3>
				<button type="button" class="vmia-modal-close">&times;</button>
			</div>
			<div class="vmia-modal-body">
				<div class="vmia-modal-grid">
					<div class="vmia-modal-preview">
						<div id="vmia-fix-preview-img"></div>
						<p class="vmia-muted"><?php esc_html_e( 'The AI has analyzed the image and page context to suggest these improvements.', 'vm-image-ai' ); ?></p>
					</div>
					<div class="vmia-modal-fields">
						<label class="vmia-label"><?php esc_html_e( 'Alt Text', 'vm-image-ai' ); ?></label>
						<input type="text" id="vmia-fix-alt" class="vmia-input">

						<label class="vmia-label"><?php esc_html_e( 'Title', 'vm-image-ai' ); ?></label>
						<input type="text" id="vmia-fix-title" class="vmia-input">

						<label class="vmia-label"><?php esc_html_e( 'Caption', 'vm-image-ai' ); ?></label>
						<textarea id="vmia-fix-caption" class="vmia-input" rows="2"></textarea>

						<label class="vmia-label"><?php esc_html_e( 'Description', 'vm-image-ai' ); ?></label>
						<textarea id="vmia-fix-description" class="vmia-input" rows="3"></textarea>
					</div>
				</div>
			</div>
			<div class="vmia-modal-footer">
				<button type="button" id="vmia-fix-regenerate" class="vmia-btn vmia-btn-ghost"><?php esc_html_e( 'Regenerate', 'vm-image-ai' ); ?></button>
				<div style="flex:1"></div>
				<button type="button" class="vmia-btn vmia-btn-ghost vmia-modal-close"><?php esc_html_e( 'Cancel', 'vm-image-ai' ); ?></button>
				<button type="button" id="vmia-fix-apply" class="vmia-btn vmia-btn-primary"><?php esc_html_e( 'Apply Changes', 'vm-image-ai' ); ?></button>
			</div>
		</div>
	</div>
</div>
