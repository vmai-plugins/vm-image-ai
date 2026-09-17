/* global VMIA, wp */
( function () {
	'use strict';

	const api = ( path, body = null, method = 'POST' ) => {
		const root = ( ( window.VMIA && VMIA.root ) || '' ).replace( /\/+$/, '' );
		const cleanPath = ( path || '' ).replace( /^\/+/, '' );
		const isGetOrHead = method === 'GET' || method === 'HEAD';

		const options = {
			url: root + '/' + cleanPath,
			method,
			headers: {
				'X-WP-Nonce': ( window.VMIA && VMIA.nonce ) || '',
			},
		};

		if ( ! isGetOrHead && body !== null && body !== undefined ) {
			options.headers['Content-Type'] = 'application/json';
			options.body = typeof body === 'string' ? body : JSON.stringify( body );
		}

		return wp.apiFetch( options );
	};

	function toast( msg, type = 'ok' ) {
		const el = document.getElementById( 'vmia-toast' );
		if ( ! el ) return;
		el.textContent = msg;
		el.className = 'vmia-toast show ' + type;
		clearTimeout( el._t );
		el._t = setTimeout( () => ( el.className = 'vmia-toast' ), 3800 );
	}

	function populateAllDatalists( catalogue ) {
		if ( ! catalogue ) return;
		const populate = ( id, items ) => {
			const list = document.getElementById( id );
			if ( ! list ) return;
			list.innerHTML = '';
			( items || [] ).forEach( item => {
				const opt = document.createElement( 'option' );
				if ( typeof item === 'object' && item !== null ) {
					opt.value = item.id;
					opt.label = item.name || item.id;
				} else {
					opt.value = item;
					opt.label = item;
				}
				list.appendChild( opt );
			} );
		};
		populate( 'vmia-list-openai', catalogue.openai );
		populate( 'vmia-list-gemini', catalogue.gemini );
		populate( 'vmia-list-openrouter', catalogue.openrouter );
		populate( 'vmia-list-huggingface', catalogue.huggingface );
		populate( 'vmia-list-cloudflare', catalogue.cloudflare );
		populate( 'vmia-list-xai', catalogue.xai );
		populate( 'vmia-list-omniroute', catalogue.omniroute );
		populate( 'vmia-list-aipuffer', catalogue.aipuffer );
	}

	function busy( btn, on, label ) {
		if ( ! btn ) return;
		if ( on ) {
			btn._html = btn.innerHTML;
			btn.disabled = true;
			btn.innerHTML = '<span class="vmia-spin"></span>' + ( label || 'Working…' );
		} else {
			btn.disabled = false;
			if ( btn._html ) btn.innerHTML = btn._html;
		}
	}

	/* ---- Theme Engine ---- */
	function bindTheme() {
		const savedTheme = localStorage.getItem( 'vmia_theme' ) || ( window.VMIA && VMIA.theme ) || 'light';
		applyTheme( savedTheme );

		document.addEventListener( 'click', ( e ) => {
			const btn = e.target.closest( '[data-theme-set]' );
			if ( ! btn ) return;
			const theme = btn.dataset.themeSet;
			applyTheme( theme );
			localStorage.setItem( 'vmia_theme', theme );
			const selectEl = document.getElementById( 'vmia-setting-theme' );
			if ( selectEl ) selectEl.value = theme;
			toast( 'Theme set to ' + theme );
		} );

		const selectEl = document.getElementById( 'vmia-setting-theme' );
		if ( selectEl ) {
			selectEl.addEventListener( 'change', () => {
				const theme = selectEl.value;
				applyTheme( theme );
				localStorage.setItem( 'vmia_theme', theme );
			} );
		}
	}

	function applyTheme( theme ) {
		document.querySelectorAll( '.vmia' ).forEach( el => el.setAttribute( 'data-theme', theme ) );
		document.querySelectorAll( '[data-theme-set]' ).forEach( btn => {
			btn.classList.toggle( 'active', btn.dataset.themeSet === theme );
		} );
	}

	/* ---- Scan ---- */
	function bindScan() {
		document.querySelectorAll( '#vmia-scan' ).forEach( ( btn ) =>
			btn.addEventListener( 'click', async () => {
				busy( btn, true, 'Scanning…' );
				try {
					const r = await api( '/scan' );
					toast( `Scan done — ${ r.total } issue(s), health ${ r.score }/100` );
					setTimeout( () => location.reload(), 900 );
				} catch ( e ) {
					toast( 'Scan failed: ' + ( e.message || e ), 'err' );
				} finally {
					busy( btn, false );
				}
			} )
		);

		document.querySelectorAll( '#vmia-autopilot-run' ).forEach( ( btn ) =>
			btn.addEventListener( 'click', async () => {
				busy( btn, true, 'Fixing…' );
				try {
					const r = await api( '/god-fix' );
					toast( `Autopilot batch done: ${ r.fixed } issue(s) fixed` );
					setTimeout( () => location.reload(), 900 );
				} catch ( e ) {
					toast( 'Autopilot failed: ' + ( e.message || e ), 'err' );
				} finally {
					busy( btn, false );
				}
			} )
		);
	}

	/* ---- Single fix ---- */
	function bindFix() {
		document.addEventListener( 'click', async ( e ) => {
			const btn = e.target.closest( '.vmia-fix' );
			if ( ! btn ) return;

			const id = parseInt( btn.dataset.id, 10 );
			const row = btn.closest( 'tr' );
			const labelEl = row ? row.querySelector( 'strong' ) : null;
			const label = labelEl ? labelEl.textContent.toLowerCase() : '';

			const isMetadata = label.includes( 'alt' ) ||
							 label.includes( 'title' ) ||
							 label.includes( 'caption' ) ||
							 label.includes( 'filename' );

			if ( isMetadata ) {
				openFixModal( id, btn );
				return;
			}

			busy( btn, true, 'Fixing…' );
			try {
				const r = await api( '/fix', { id } );
				if ( r.ok ) {
					toast( r.message || 'Fixed' );
					if ( row ) {
						row.style.transition = 'opacity .3s';
						row.style.opacity = '.35';
					}
					btn.textContent = 'Done';
					btn.disabled = true;
				} else {
					toast( r.message || 'Could not fix', 'err' );
				}
			} catch ( err ) {
				toast( 'Fix failed: ' + ( err.message || err ), 'err' );
			} finally {
				busy( btn, false );
			}
		} );

		document.addEventListener( 'click', async ( e ) => {
			const btn = e.target.closest( '.vmia-regen' );
			if ( ! btn ) return;

			const id = parseInt( btn.dataset.id, 10 );
			const row = btn.closest( 'tr' );
			if ( ! confirm( 'This will generate a brand new image to replace the current one. Proceed?' ) ) return;

			busy( btn, true, 'Generating…' );
			try {
				const r = await api( '/regenerate-image', { id } );
				if ( r.ok ) {
					toast( r.message || 'New image ready' );
					if ( row ) {
						row.style.transition = 'opacity .3s';
						row.style.opacity = '.35';
					}
					btn.textContent = 'Done';
					btn.disabled = true;
				} else {
					toast( r.message || 'Generation failed', 'err' );
				}
			} catch ( err ) {
				toast( 'Error: ' + ( err.message || err ), 'err' );
			} finally {
				busy( btn, false );
			}
		} );

		document.addEventListener( 'click', async ( e ) => {
			const btn = e.target.closest( '.vmia-undo' );
			if ( ! btn ) return;

			const oid = parseInt( btn.dataset.oid, 10 );
			busy( btn, true, 'Undoing…' );
			try {
				const r = await api( '/undo', { oid } );
				if ( r.ok ) {
					toast( r.message );
					setTimeout( () => location.reload(), 800 );
				} else {
					toast( r.message, 'err' );
				}
			} catch ( err ) {
				toast( 'Undo failed: ' + err.message, 'err' );
			} finally {
				busy( btn, false );
			}
		} );
	}

	async function openFixModal( id, btn ) {
		const modal = document.getElementById( 'vmia-fix-modal' );
		if ( ! modal ) return;

		const altInput = document.getElementById( 'vmia-fix-alt' );
		const titleInput = document.getElementById( 'vmia-fix-title' );
		const captionInput = document.getElementById( 'vmia-fix-caption' );
		const descInput = document.getElementById( 'vmia-fix-description' );
		const previewImg = document.getElementById( 'vmia-fix-preview-img' );
		const applyBtn = document.getElementById( 'vmia-fix-apply' );
		const regenBtn = document.getElementById( 'vmia-fix-regenerate' );

		// Reset & Show Modal immediately with loading state
		[ altInput, titleInput, captionInput, descInput ].forEach( i => i.value = 'Loading...' );
		previewImg.innerHTML = '<div class="vmia-spin" style="margin:40px auto; display:block;"></div>';
		modal.classList.remove( 'hide' );
		modal.hidden = false;

		const loadSuggestion = async ( isRegen = false ) => {
			const target = isRegen ? regenBtn : btn;
			busy( target, true, isRegen ? 'Thinking…' : 'Analyzing…' );
			try {
				const r = await api( '/suggest-fix', { id } );
				if ( ! r.ok ) throw new Error( r.message || 'Could not get suggestion' );

				altInput.value = r.suggestion.alt || '';
				titleInput.value = r.suggestion.title || '';
				captionInput.value = r.suggestion.caption || '';
				descInput.value = r.suggestion.description || '';

				const row = btn.closest( 'tr' );
				if ( row && ! isRegen ) {
					const thumb = row.querySelector( '.vmia-thumb img' );
					if ( thumb ) {
						previewImg.innerHTML = `<img src="${ thumb.src }" style="width:100%; border-radius:8px;">`;
					}
				}
			} catch ( e ) {
				toast( 'Suggestion failed: ' + e.message, 'err' );
				[ altInput, titleInput, captionInput, descInput ].forEach( i => i.value = '' );
			} finally {
				busy( target, false );
			}
		};

		await loadSuggestion();

		const close = () => {
			modal.classList.add( 'hide' );
			modal.hidden = true;
		};

		modal.querySelectorAll( '.vmia-modal-close' ).forEach( c => c.onclick = close );
		modal.onclick = ( e ) => { if ( e.target === modal ) close(); };
		regenBtn.onclick = ( e ) => { e.preventDefault(); loadSuggestion( true ); };

		applyBtn.onclick = async ( e ) => {
			e.preventDefault();
			busy( applyBtn, true, 'Applying…' );
			try {
				const metadata = {
					alt: altInput.value,
					title: titleInput.value,
					caption: captionInput.value,
					description: descInput.value,
				};
				const res = await api( '/apply-fix', { id, metadata } );
				if ( res.ok ) {
					toast( 'Metadata updated' );
					close();
					const row = btn.closest( 'tr' );
					if ( row ) {
						row.style.transition = 'opacity .3s';
						row.style.opacity = '.35';
					}
					btn.textContent = 'Done';
					btn.disabled = true;
				}
			} catch ( e ) {
				toast( 'Apply failed: ' + e.message, 'err' );
			} finally {
				busy( applyBtn, false );
			}
		};
	}

	/* ---- God Fix (looped batches) ---- */
	function bindGodFix() {
		document.querySelectorAll( '#vmia-godfix' ).forEach( ( btn ) =>
			btn.addEventListener( 'click', async () => {
				const prog = document.getElementById( 'vmia-godfix-progress' );
				const bar = prog ? prog.querySelector( 'span' ) : null;
				const lbl = prog ? prog.querySelector( '.vmia-progress-label' ) : null;
				if ( prog ) prog.hidden = false;
				busy( btn, true, 'Running…' );

				let totalFixed = 0;
				let remaining = Infinity;
				let start = null;
				let stuckCount = 0;
				try {
					do {
						const r = await api( '/god-fix', {} );
						totalFixed += r.fixed;
						remaining = r.remaining;
						if ( start === null ) start = remaining + r.processed;
						const done = start ? Math.round( ( ( start - remaining ) / start ) * 100 ) : 100;
						if ( bar ) bar.style.width = Math.min( 100, done ) + '%';
						if ( lbl ) lbl.textContent = `Fixed ${ totalFixed } · ${ remaining } remaining`;
						if ( r.processed === 0 ) break;

						// Prevent infinite loops if issues fail repeatedly and cannot be fixed.
						if ( r.fixed === 0 && r.processed > 0 ) {
							stuckCount++;
							if ( stuckCount >= 2 ) {
								toast( `God Fix paused: ${ remaining } issue(s) could not be automatically resolved.`, 'warn' );
								break;
							}
						} else {
							stuckCount = 0;
						}
					} while ( remaining > 0 );

					toast( `God Fix complete — ${ totalFixed } issue(s) resolved` );
					setTimeout( () => location.reload(), 1200 );
				} catch ( e ) {
					toast( 'God Fix error: ' + ( e.message || e ), 'err' );
				} finally {
					busy( btn, false );
				}
			} )
		);
	}

	/* ---- Generate ---- */
	function bindGenerate() {
		const btn = document.getElementById( 'vmia-generate' );
		if ( ! btn ) return;

		const mediaTypeSelector = document.getElementById( 'vmia-media-type' );
		const imageOptions = document.getElementById( 'vmia-image-options' );

		if ( mediaTypeSelector && imageOptions ) {
			mediaTypeSelector.addEventListener( 'change', () => {
				imageOptions.hidden = ( mediaTypeSelector.value !== 'image' );
			} );
		}

		btn.addEventListener( 'click', async () => {
			const mediaType = mediaTypeSelector ? mediaTypeSelector.value : 'image';
			const subject = document.getElementById( 'vmia-subject' ).value.trim();
			const post_id = parseInt( document.getElementById( 'vmia-post' ).value, 10 ) || 0;
			const mode = document.getElementById( 'vmia-mode' ).value;
			const set_featured = document.getElementById( 'vmia-featured' ).checked;

			if ( ! subject && ! post_id ) {
				toast( 'Enter a subject or pick a post', 'err' );
				return;
			}
			busy( btn, true, 'Generating…' );
			try {
				const path = mediaType === 'video' ? '/generate-video' : '/generate';
				const r = await api( path, { subject, post_id, mode, set_featured } );
				if ( r.ok ) {
					renderResult( r, mediaType );
					toast( ( mediaType === 'video' ? 'Video' : 'Image' ) + ' generated via ' + r.provider );
				} else {
					toast( 'Generation failed: ' + ( r.error || 'unknown' ), 'err' );
				}
			} catch ( e ) {
				toast( 'Error: ' + ( e.message || e ), 'err' );
			} finally {
				busy( btn, false );
			}
		} );
	}

	function bindBulkGenerateMissing() {
		const bulkGenMissing = document.getElementById( 'vmia-bulk-generate-missing' );
		if ( ! bulkGenMissing ) return;

		bulkGenMissing.addEventListener( 'click', async () => {
			const prog = document.getElementById( 'vmia-gen-progress' );
			const bar = prog ? prog.querySelector( 'span' ) : null;
			const lbl = prog ? prog.querySelector( '.vmia-progress-label' ) : null;
			if ( prog ) {
				prog.hidden = false;
				bar.style.width = '20%';
				lbl.textContent = 'Scanning for missing images...';
			}

			busy( bulkGenMissing, true, 'Generating batch…' );
			try {
				const r = await api( '/bulk-generate-missing' );
				if ( r.count > 0 ) {
					if ( bar ) bar.style.width = '100%';
					if ( lbl ) lbl.textContent = `Success: Generated ${ r.count } images`;
					toast( `Generated ${ r.count } featured images` );
					setTimeout( () => location.reload(), 1500 );
				} else {
					if ( prog ) prog.hidden = true;
					toast( r.message || 'No images to generate', 'err' );
				}
			} catch ( e ) {
				if ( prog ) prog.hidden = true;
				toast( 'Bulk generation failed: ' + e.message, 'err' );
			} finally {
				busy( bulkGenMissing, false );
			}
		} );
	}

	function renderResult( r, type = 'image' ) {
		const box = document.getElementById( 'vmia-result' );
		if ( ! box ) return;

		let mediaHtml = '';
		if ( type === 'video' ) {
			mediaHtml = `<video src="${ r.url }" controls style="width:100%; border-radius:8px;"></video>`;
		} else {
			mediaHtml = `<img src="${ r.url }?t=${ Date.now() }" alt="generated preview">`;
		}

		box.innerHTML =
			mediaHtml +
			`<div class="vmia-meta-line"><b>Provider</b><span>${ r.provider }</span></div>` +
			`<div class="vmia-meta-line"><b>Attachment</b><span>#${ r.attach_id }</span></div>` +
			`<p class="vmia-muted vmia-mt">` +
			( type === 'image' ? `Alt / title / caption / description were written automatically and the image was resized &amp; compressed to spec.` : `The video was imported into your media library.` ) +
			`</p>`;
	}

	/* ---- Quick actions in media modal ---- */
	function bindMediaQuick() {
		document.addEventListener( 'click', async ( ev ) => {
			const seo = ev.target.closest( '.vmia-quick-seo' );
			const opt = ev.target.closest( '.vmia-quick-optimize' );
			if ( seo ) {
				busy( seo, true, 'Writing…' );
				try {
					const r = await api( '/write-seo', { id: parseInt( seo.dataset.id, 10 ) } );
					toast( r.ok ? 'SEO metadata written' : 'Failed', r.ok ? 'ok' : 'err' );
				} catch ( e ) { toast( 'Error: ' + e.message, 'err' ); }
				busy( seo, false );
			}
			if ( opt ) {
				busy( opt, true, 'Optimising…' );
				try {
					const r = await api( '/optimize', { id: parseInt( opt.dataset.id, 10 ) } );
					toast( r.ok ? 'Optimised' : ( r.error || 'Failed' ), r.ok ? 'ok' : 'err' );
				} catch ( e ) { toast( 'Error: ' + e.message, 'err' ); }
				busy( opt, false );
			}
		} );
	}

	/* ---- Settings ---- */
	function bindSettings() {
		const save = document.getElementById( 'vmia-save' );
		if ( save ) {
			save.addEventListener( 'click', async () => {
				const payload = {};
				document.querySelectorAll( '[data-key]' ).forEach( ( el ) => {
					const key = el.dataset.key;
					if ( el.type === 'checkbox' ) {
						payload[ key ] = el.checked;
					} else if ( el.dataset.list ) {
						payload[ key ] = el.value.split( ',' ).map( ( s ) => s.trim() ).filter( Boolean );
					} else if ( el.type === 'number' ) {
						payload[ key ] = parseInt( el.value, 10 ) || 0;
					} else {
						payload[ key ] = el.value;
					}
				} );
				busy( save, true, 'Saving…' );
				try {
					await api( '/settings', payload );
					toast( 'Settings saved' );
				} catch ( e ) {
					toast( 'Save failed: ' + ( e.message || e ), 'err' );
				} finally {
					busy( save, false );
				}
			} );
		}

		const sync = document.getElementById( 'vmia-sync' );
		if ( sync ) {
			sync.addEventListener( 'click', async () => {
				busy( sync, true, 'Syncing…' );
				try {
					const r = await api( '/sync-models' );
					const counts = Object.entries( r.counts || {} ).map( ( [ k, v ] ) => `${ k }: ${ v }` ).join( ', ' );
					toast( `Synced: ${ counts }` );

					if ( r.catalogue ) {
						populateAllDatalists( r.catalogue );
					}
				} catch ( e ) {
					toast( 'Sync failed: ' + ( e.message || e ), 'err' );
				} finally {
					busy( sync, false );
				}
			} );
		}

		bindAipufferTest();
	}

	/* ---- AI Puffer bot test/discovery ---- */
	function bindAipufferTest() {
		const btn = document.getElementById( 'vmia-aipuffer-test' );
		if ( ! btn ) return;

		const val = ( key ) => {
			const el = document.querySelector( `[data-key="${ key }"]` );
			return el ? el.value : '';
		};

		btn.addEventListener( 'click', async () => {
			const status = document.getElementById( 'vmia-aipuffer-status' );
			const list = document.getElementById( 'vmia-aipuffer-bots' );
			const payload = {
				base: val( 'aipuffer_base' ),
				key: val( 'aipuffer_key' ),
				bot_id: val( 'aipuffer_bot_id' ),
			};
			busy( btn, true, 'Testing…' );
			try {
				const r = await api( '/aipuffer/test', payload );

				if ( r.real_base && r.real_base !== payload.base ) {
					const baseEl = document.querySelector( '[data-key="aipuffer_base"]' );
					if ( baseEl ) {
						baseEl.value = r.real_base;
						toast( 'AI Puffer base URL corrected' );
					}
				}

				if ( list ) {
					list.innerHTML = '';
					( r.bots || [] ).forEach( ( b ) => {
						const opt = document.createElement( 'option' );
						opt.value = b.id;
						opt.label = b.name || b.id;
						list.appendChild( opt );
					} );
				}
				if ( status ) {
					const localSuffix = r.local ? ' (Local site detection)' : '';
					if ( ! payload.bot_id ) {
						status.textContent = `Connected · ${ ( r.bots || [] ).length } bot(s) available${ localSuffix }. Pick one above to target it.`;
					} else if ( r.bot_valid === false ) {
						status.textContent = `Connected${ localSuffix }, but that bot ID was not found — pick one from the list.`;
					} else {
						status.textContent = `Connected · bot confirmed${ localSuffix }.`;
					}
				}
				toast( 'AI Puffer connection OK' );
			} catch ( e ) {
				if ( status ) status.textContent = 'Connection failed: ' + ( e.message || e );
				toast( 'AI Puffer test failed: ' + ( e.message || e ), 'err' );
			} finally {
				busy( btn, false );
			}
		} );
	}

	/* ---- GitHub Updater ---- */
	function bindGitHubUpdater() {
		const checkBtn = document.getElementById( 'vmia-check-github-update' );
		const resultBox = document.getElementById( 'vmia-github-update-result' );
		const bannerContainer = document.getElementById( 'vmia-update-banner-container' );

		const renderUpdate = ( r, container ) => {
			if ( ! container ) return;
			if ( r.has_update ) {
				container.innerHTML = `
					<div class="vmia-update-banner">
						<div class="vmia-update-text">
							<span class="vmia-update-badge">Update Available</span>
							<span><strong>v${ r.new_version }</strong> is available! (Current: v${ r.current_version })</span>
						</div>
						<div style="display:flex; gap:10px; align-items:center;">
							<a href="${ r.release_url }" target="_blank" class="vmia-btn vmia-btn-ghost vmia-btn-sm">Changelog ↗</a>
							<a href="${ r.update_url }" class="vmia-btn vmia-btn-primary vmia-btn-sm">Update Now</a>
						</div>
					</div>
				`;
			} else {
				container.innerHTML = `<p class="vmia-muted" style="color:var(--good); margin:6px 0;">✓ You are on the latest version (v${ r.current_version }).</p>`;
			}
		};

		if ( checkBtn ) {
			checkBtn.addEventListener( 'click', async () => {
				busy( checkBtn, true, 'Checking…' );
				try {
					const r = await api( '/check-update', null, 'GET' );
					if ( r && r.ok ) {
						renderUpdate( r, resultBox );
						if ( r.has_update ) {
							toast( `Update v${ r.new_version } available!` );
						} else {
							toast( `Up to date (v${ r.current_version })` );
						}
					} else {
						const errMsg = ( r && r.message ) || 'Check failed';
						if ( resultBox ) resultBox.innerHTML = `<p class="vmia-muted" style="color:var(--err); margin:6px 0;">✕ ${ errMsg }</p>`;
						toast( errMsg, 'err' );
					}
				} catch ( e ) {
					const msg = ( e && ( e.message || e.code ) ) || 'Could not connect to update service';
					if ( resultBox ) resultBox.innerHTML = `<p class="vmia-muted" style="color:var(--err); margin:6px 0;">✕ ${ msg }</p>`;
					toast( 'Update check failed: ' + msg, 'err' );
				} finally {
					busy( checkBtn, false );
				}
			} );
		}

		// Auto-check on dashboard banner container if present
		if ( bannerContainer ) {
			api( '/check-update', null, 'GET' ).then( r => {
				if ( r && r.ok && r.has_update ) {
					renderUpdate( r, bannerContainer );
				}
			} ).catch( () => {} );
		}
	}

	function bindAuditorFeatures() {
		const filterType = document.getElementById( 'vmia-filter-type' );
		const selectAll = document.getElementById( 'vmia-select-all' );
		const rowCBs = document.querySelectorAll( '.vmia-row-cb' );
		const bulkBar = document.getElementById( 'vmia-bulk-bar' );
		const selectedCount = document.getElementById( 'vmia-selected-count' );
		const bulkApply = document.getElementById( 'vmia-bulk-apply' );
		const bulkCancel = document.getElementById( 'vmia-bulk-cancel' );
		const bulkAction = document.getElementById( 'vmia-bulk-action' );

		if ( filterType ) {
			filterType.addEventListener( 'change', () => {
				const code = filterType.value;
				document.querySelectorAll( '.vmia-audit-table tbody tr' ).forEach( tr => {
					if ( ! code || tr.dataset.code === code ) {
						tr.style.display = '';
					} else {
						tr.style.display = 'none';
					}
				} );
			} );
		}

		const updateBulkBar = () => {
			const selected = document.querySelectorAll( '.vmia-row-cb:checked' );
			if ( selected.length > 0 ) {
				bulkBar.classList.remove( 'hide' );
				selectedCount.textContent = selected.length;
			} else {
				bulkBar.classList.add( 'hide' );
			}
		};

		if ( selectAll ) {
			selectAll.addEventListener( 'change', () => {
				const visibleCBs = document.querySelectorAll( '.vmia-audit-table tbody tr:not([style*="display: none"]) .vmia-row-cb' );
				visibleCBs.forEach( cb => cb.checked = selectAll.checked );
				updateBulkBar();
			} );
		}

		rowCBs.forEach( cb => {
			cb.addEventListener( 'change', updateBulkBar );
		} );

		if ( bulkCancel ) {
			bulkCancel.addEventListener( 'click', () => {
				document.querySelectorAll( '.vmia-row-cb:checked' ).forEach( cb => cb.checked = false );
				if ( selectAll ) selectAll.checked = false;
				updateBulkBar();
			} );
		}

		if ( bulkApply ) {
			bulkApply.addEventListener( 'click', async () => {
				const action = bulkAction.value;
				if ( ! action ) {
					toast( 'Please select an action', 'err' );
					return;
				}
				const ids = Array.from( document.querySelectorAll( '.vmia-row-cb:checked' ) ).map( cb => parseInt( cb.value, 10 ) );
				if ( ids.length === 0 ) return;

				busy( bulkApply, true, 'Applying…' );
				try {
					const r = await api( '/bulk-fix', { ids, action } );
					toast( `Bulk complete: ${ r.fixed } fixed, ${ r.failed } failed` );
					setTimeout( () => location.reload(), 1500 );
				} catch ( e ) {
					toast( 'Bulk failed: ' + e.message, 'err' );
				} finally {
					busy( bulkApply, false );
				}
			} );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		bindTheme();
		populateAllDatalists( VMIA.catalogue );
		bindScan();
		bindFix();
		bindGodFix();
		bindGenerate();
		bindBulkGenerateMissing();
		bindMediaQuick();
		bindSettings();
		bindAuditorFeatures();
		bindGitHubUpdater();

		// Better Datalist behavior: show all options on empty click
		document.addEventListener( 'click', ( e ) => {
			if ( e.target.tagName === 'INPUT' && e.target.getAttribute( 'list' ) ) {
				if ( e.target.value === '' ) {
					e.target.setAttribute( 'placeholder', e.target.getAttribute( 'placeholder' ) || '' );
				}
			}
		} );
	} );
} )();
