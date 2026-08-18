=== VM Image AI ===
Contributors: vmstudio
Tags: images, seo, ai, image generation, alt text, webp, featured image
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-integrated featured & blog image generator with intelligent Image-SEO auditing, auto alt/title/caption/description, resizing/WebP, gap detection and one-click God Fix.

== Description ==

VM Image AI is a complete, free-stack image intelligence tool for WordPress. It
generates featured and in-blog images, imports them into the media library,
writes fully optimised SEO metadata, detects image-SEO problems across your whole
site, and fixes them — individually or all at once.

**Free engine stack**

Text + vision AI (fallback chain, first success wins):
* AI Puffer (AIPKit REST on your own site)
* Google Gemini
* OpenRouter — with live model sync so "auto" always resolves to a model that
  currently exists (resilient fallback).

Image generation (fallback chain):
* Pollinations (free, keyless — default)
* AI Puffer image generation
* ComfyUI (your own self-hosted server)
* Pexels (free stock fallback)

**What it does**

* Generate featured images (1200×630) and wide blog images from a subject or a post.
* AI enriches your idea into a full text-to-image prompt before generating.
* Vision reads the actual image so alt text is accurate, not guessed.
* Writes alt, title, caption and description — length-bounded and keyword-aware.
* Auto-writes SEO metadata and (optionally) optimises on every new upload.
* Resizes, compresses and converts to WebP; regenerates thumbnail metadata.
* Audits the whole library + post content for 12 issue types, ranked by severity.
* God Fix: bulk autopilot that resolves the entire queue in batches.
* WP-CLI (`wp vmia scan|god-fix|featured|seo|sync`) for agency multi-site use.

**Detected issues**

Missing alt, thin alt, filename-as-alt, duplicate alt, filename titles, missing
caption/description, non-descriptive filenames, oversized files, oversized
dimensions, non-WebP format, posts with no featured image, and in-content images
missing alt.

== Installation ==

1. Upload the `vm-image-ai` folder to `/wp-content/plugins/` (or install the zip
   via Plugins → Add New → Upload).
2. Activate the plugin.
3. Go to **VM Image AI → Settings** and add whichever API keys you want to use.
   Pollinations works with no key at all, so image generation works out of the box.
4. Run a scan from the Dashboard, then God Fix.

== Frequently Asked Questions ==

= Is it really free? =
The default image provider (Pollinations) needs no key. Gemini and OpenRouter have
free tiers/models, and ComfyUI is self-hosted. AI Puffer runs on your own site.

= Does it need a paid AI to write alt text? =
It works best with at least one text provider configured. Without any, it degrades
gracefully to filename/context-derived alt text.

== Changelog ==

= 1.0.0 =
* Initial release: generation engine, vision-grounded SEO writer, auditor, God Fix,
  resize/WebP, live model sync, WP-CLI, REST admin, Titan dark UI.
