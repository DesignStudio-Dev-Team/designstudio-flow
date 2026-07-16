# DesignStudio Flow Multilingual Feature Plan

Status: product and architecture proposal only. No implementation has started.

This plan must be used together with `BLOCK-BUILDING-README.md`. Any implementation that changes headers, block settings, AJAX endpoints, forms, frontend output, imports, or snapshots must pass that guide's security and verification gates.

## Approved Product Decisions

The following decisions were approved on July 14, 2026 and replace earlier MVP suggestions in this document:

- The feature covers all DesignStudio Flow visitor-facing systems, not only pages: pages, headers, footers, popups, forms, saved blocks, reusable templates, notification bars, blog/shop/product templates, and the WordPress/WooCommerce content those templates display.
- The SEO-friendly URL policy is approved: preserve existing main-language URLs without a prefix and place every secondary language under a stable language prefix, such as `/about/`, `/es/about/`, and `/fr/about/`.
- On a multilingual site, publishing is blocked until every required language dependency for that item exists, is reviewed, and is publishable. A page cannot silently fall back to a main-language header, footer, popup, form, or other required visitor-facing content.
- Every cloned translation starts as a draft and requires human review.
- A central translation review screen lists missing, draft, machine-prefilled, stale, blocked, and ready translations across every supported content type.
- A translation can be cloned only from the configured main language.
- Every DesignStudio Flow header automatically includes the language switcher when multilingual mode is enabled. Editors may configure its presentation and placement, but cannot accidentally remove the site's only language navigation without an explicit site-level alternative.
- Self-hosted LibreTranslate ships in the first release. Machine-prefilled content is always marked `Needs review`; manual translation remains available.
- DesignStudio Flow is the only multilingual system on the site. Activation/configuration must detect known translation plugins and block multilingual mode until they are disabled. It must never deactivate another plugin automatically.

The URL policy is resolved. Query-parameter and cookie-only language routing are out of scope.

## Goal

Add first-party multilingual content to DesignStudio Flow without requiring AI or consuming AI tokens.

An administrator chooses:

- One main language for the site.
- One or more additional site languages.
- A URL format and fallback behavior.
- Whether machine translation assistance is disabled or connected to an optional non-AI translation service.

An editor can then:

- Open a page in the main language.
- See which language versions exist and their draft/published state.
- clone the page into a missing language.
- Optionally prefill eligible text with machine translation.
- Edit every translation manually as a normal DesignStudio Flow page.
- Switch between language versions in the editor.
- Add a language switcher to DesignStudio Flow headers or render one outside those headers with a shortcode.

## Recommended Architecture

### One real WordPress object per translation

Each translated page should be a separate WordPress `page`, not another language object nested inside the main page's block JSON.

The translations are separate objects with separate public URLs:

- English main-language page: `/about/`.
- Spanish page: `/es/about/`.
- French page: `/fr/about/`.

Each page independently owns its:

- WordPress title, slug, parent, status, author, and revisions.
- `_dsf_blocks`.
- `_dsf_settings`, including SEO and popup selection.
- `_dsf_html_snapshot`.
- Featured image and other appropriate WordPress page data.

The translations are connected with small, indexed metadata:

- `_dsf_language`: a validated site language code such as `en-US`, `es`, or `fr`.
- `_dsf_translation_group`: an opaque group identifier shared by every translation of the same item.
- Optional translation workflow metadata, such as source language, source modified time, and `draft`, `needs-review`, or `reviewed` status.

The group must allow no more than one object of a given type and language. The main-language object is not permanently treated as a master copy after cloning; each language becomes independently editable.

Why this model is recommended:

- It works with the current editor/save/snapshot architecture.
- Every language has an independently indexable URL and independent SEO fields.
- Drafts, previews, revisions, permissions, redirects, and WordPress APIs continue to work normally.
- A translation cannot accidentally overwrite another language when its page is saved.
- New and existing blocks participate without storing every language inside every setting.

### Language configuration

Add a Languages section to DesignStudio Flow Settings with:

- Multilingual feature enabled/disabled.
- Main language.
- Ordered enabled-language list.
- Native label for each language, such as `Español` rather than `Spanish`.
- Language code and HTML `lang` value.
- Optional locale/region, such as `es-MX`.
- Optional flag/icon choice; native text labels must remain available for accessibility.
- URL policy.
- Missing-translation behavior.
- Optional translation provider settings.

Use a curated language/locale registry. Do not accept arbitrary HTML, flags, locale strings, or language selectors.

### Approved language-prefix URL policy

- Preserve the main language at existing unprefixed URLs, such as `/about/`. This avoids an unnecessary migration and protects existing links and search equity.
- Place each secondary language under its configured stable prefix, such as `/es/about/`, `/fr/about/`, or `/es-mx/about/` when a regional distinction is required.
- Prefixes must come from the curated enabled-language configuration, be unique, be sanitized, and avoid collisions with WordPress routes, pages, taxonomies, REST paths, feeds, and reserved endpoints.
- A prefix change is a URL migration. It requires collision checks, permanent redirects from the former language URLs, canonical/`hreflang` updates, sitemap refreshes, and cache invalidation.
- The main language is the `x-default` target unless a future dedicated language-selection page is explicitly configured.
- Query-parameter and cookie-only language URLs are not supported. A preference cookie may improve optional language suggestions, but it must never change what a stable language URL represents.
- Browser language may be used only for a non-blocking suggestion when no explicit preference exists. It must not redirect crawlers, trap users in redirects, or override a selected URL.

The routing layer must support translated page hierarchies, translated slugs, archives, pagination, feeds where supported, previews, and 404 behavior. It must validate path collisions before a translation is created or saved. Standard full-page and CDN caches can key on the distinct prefixed URLs.

### Translation relationships for more than pages

Use the same language/group relationship service for all DesignStudio Flow content that contains visitor-facing text:

- Pages.
- Headers and footers (`dsf_layout`).
- Popups (`dsf_popup`).
- Forms (`dsf_form`), while preserving stable field keys and submission behavior.
- Page templates (`dsf_template`) when editors want language-specific reusable designs.
- Product, shop, and blog templates where the template itself contains visitor-facing text.
- Notification bars and other global visitor-facing messages.
- WordPress posts, categories, tags, and other content rendered by Flow blog templates.
- WooCommerce products, product categories, tags, attributes, variations, and other visitor-facing catalog text rendered by Flow store templates.

Saved blocks need special treatment. A cloned page translation must not remain connected to a source-language `savedBlockId`, because global saved-block sync could later replace the translated settings with the source-language settings. Saved blocks must have their own language groups, and sync must be language-scoped. The clone operation maps a source saved block to its reviewed target-language sibling when available; otherwise publishing is blocked or the instance is explicitly detached for manual translation.

WordPress and WooCommerce objects are separate content systems and require dedicated adapters. Their identity, pricing, inventory, SKU, taxonomy relationships, downloadable files, and commerce behavior remain shared/non-translatable where appropriate; only explicitly visitor-facing content is cloned or translated. Product variations and structured product data must not be duplicated in a way that creates separate inventory or ordering records merely to translate labels and descriptions.

### Clone workflow

The editor should have a language control in the floating dock. It shows the current language and every enabled language with one of these states:

- Current.
- Published.
- Draft.
- Needs review.
- Missing.

Choosing a missing language from the main-language item opens a clone dialog. Non-main translations may be edited and reviewed but cannot be used as clone sources:

1. Confirm the source and target language.
2. Choose `Copy content only` or `Copy and prefill text using the configured translator`.
3. Choose whether to copy SEO text, the popup relationship, and the page's header/footer assignment.
4. Enter or confirm the translated page title and slug.
5. Create the translation as a draft.
6. Open the new language page in the DesignStudio Flow editor.

Cloning should preserve the page structure, media, styling, responsive settings, safe internal anchors, and supported relationships. It should regenerate identifiers only where uniqueness across pages is required, detach unsafe source-language sync relationships, and never publish automatically.

When copying relationships, resolve a target-language sibling when one exists:

- English header -> Spanish header.
- English footer -> Spanish footer.
- English popup -> Spanish popup.
- English parent page -> Spanish parent page.

If any required translated sibling does not exist or has not passed review, the translation remains blocked from publishing. The review screen must identify the exact missing header, footer, popup, form, saved block, notification, template, taxonomy, or other dependency. There is no silent main-language fallback on a multilingual site.

### What can be machine translated

Block registration must explicitly identify translatable setting paths. Do not guess based only on whether a value is a string.

Usually translatable:

- Headings, labels, descriptions, captions, button text, image alt text, accessible names, and plain-text repeater values.
- Text nodes inside allowed WYSIWYG content.
- SEO title and meta description.
- Visible form labels, help text, placeholders, validation messages, and option labels.

Usually not translatable:

- URLs, email addresses, CSS values, colors, media URLs, IDs, enum values, icon names, dates, product SKUs, shortcodes, template tokens, analytics values, HTML tags/attributes, form field keys, and internal anchors.

The translation extractor should produce a bounded list of text segments from known schema paths. Translated segments must be placed back through the same block/page/form sanitizers used by normal saves. A remote response is untrusted input.

Never send secrets, form submissions, customer data, API credentials, unpublished private content without an explicit warning, or entire unsanitized HTML snapshots to a translation provider.

### Self-hosted LibreTranslate in the first release

Machine translation is a convenience, not a publishing authority. Manual cloning must still work if LibreTranslate is offline or disabled, and no machine output may skip human review.

Recommended provider design:

- A provider interface so the content model does not permanently depend on one service.
- LibreTranslate as the first adapter because it is open source and can be self-hosted.
- A setup/status test that verifies the configured self-hosted endpoint and its supported language pairs without sending site content.
- Store provider credentials server-side and encrypt secrets using the plugin's existing credential pattern.
- Make all provider requests from WordPress, never directly from the visitor-facing browser.
- Set strict timeouts, response-size limits, segment and character caps, and rate limits.
- Use an exact administrator-configured endpoint or a provider allowlist; never accept a request-time URL.
- Treat failures as recoverable: create or retain the untranslated draft and report which segments failed.

Only a self-hosted/private LibreTranslate endpoint is supported for the first release. The public managed service and unknown community instances are not production defaults. Its official hosted instance uses purchased API keys, while a self-hosted instance can run without a hosted-service API key.

Do not market machine output as completed translation. Mark it `Needs review` until a human confirms it.

## Header And Standalone Language Switchers

Build one shared language-switcher data resolver and one shared accessible renderer, then reuse them everywhere.

When multilingual mode is enabled, every DesignStudio Flow header renders the shared switcher automatically. Each header exposes settings such as:

- Presentation: dropdown or compact list.
- Label style: native language name, short code, or name plus optional icon.
- Desktop and mobile placement supported by that header.
- Whether to show missing translations to logged-in editors only.

The switcher must resolve the current object's reviewed, published language siblings and use their actual permalinks. It must not construct targets by blindly adding or replacing path text. Missing, draft, stale, blocked, or unreviewed translations stay hidden from public visitors. The current language is announced accessibly and is not a misleading link to itself.

For sites that do not use a DesignStudio Flow header, provide:

`[dsf_language_switcher]`

Proposed safe shortcode attributes:

- `style="dropdown|list|compact"`
- `show_names="true|false"`
- `show_codes="true|false"`
- `show_icons="true|false"`
- `class="..."`, restricted to sanitized class tokens if supported at all.

The shortcode must use the same resolver and renderer as the built-in headers. A later phase may add a widget or Gutenberg block, but it should not duplicate the relationship logic.

## Translation Review And Publishing Gate

Add a DesignStudio Flow translation review screen that can be filtered by language, content type, owner, and status. It must cover pages, layouts, popups, forms, saved blocks, reusable templates, global messages, WordPress content, and WooCommerce content in scope.

Required statuses:

- Missing: no target-language object exists.
- Draft: a human-editable translation exists but is not ready.
- Machine prefilled: LibreTranslate output exists and needs review.
- Source changed: the main-language source changed after the last reviewed translation.
- Blocked: a required translated dependency is missing, stale, unreviewed, invalid, or unpublished.
- Ready for review: content and dependencies exist but a human has not approved it.
- Reviewed: a human with the required capability approved the current source version.
- Published: reviewed content is publicly available.

The screen shows the main-language source, target language, last source change, last translation change, reviewer, blocking dependencies, and direct edit/review actions. Review must be recorded against a source-content fingerprint or source modified version so later source changes automatically return the translation to `Source changed` without overwriting it.

Publishing a translated object requires:

- The object is human-reviewed against the current main-language source version.
- All required translated dependencies are reviewed and publishable.
- Its parent hierarchy and route resolve without collisions.
- Its required forms, headers, footers, popups, reusable content, and dynamic content relationships are language-complete.
- An authorized user passes the normal WordPress and object-level capability checks.

The server enforces this gate. Disabled buttons and client warnings are not sufficient. The main-language content may continue publishing normally, but changing it marks affected translations stale and may remove them from public switchers until re-reviewed according to the final stale-content policy.

## SEO And Frontend Requirements

Each translated URL must:

- Output the correct `<html lang>` and text direction (`ltr` or `rtl`).
- Use its own title, description, social metadata, and self-canonical URL unless the editor deliberately overrides it.
- Output reciprocal `hreflang` links for every published sibling plus itself.
- Output `x-default` according to the selected site policy, normally the main-language URL.
- Exclude drafts and private translations from public switchers and `hreflang`.
- Remain independently eligible for the WordPress sitemap unless marked `noindex`.
- Invalidate and regenerate its own snapshot when translated content changes.

When a dedicated SEO plugin is active, DesignStudio Flow already defers much of its SEO output. Multilingual integration must avoid duplicate canonical or `hreflang` tags and should define compatibility tests for supported SEO plugins.

Avoid automatic browser-language redirect loops. A non-blocking suggestion can remember dismissal without preventing users or crawlers from opening the main language or any addressable translation.

## Import, Export, Deletion, And Recovery

- Export language settings and translation relationships in a portable form that does not depend only on numeric post IDs.
- Import all members of a translation group first, map old IDs to new IDs, then rebuild relationships.
- A single-item export may import as an ungrouped item or create a new group; the behavior must be explicit.
- Deleting one translation must not delete its siblings automatically.
- Changing the main language must not rewrite every page destructively. Require a migration preview and collision report.
- Disabling multilingual mode must leave every page intact and reachable through a documented compatibility behavior.
- Uninstall behavior must preserve content unless the administrator explicitly chooses destructive cleanup.

## Security And Reliability Gates

All translation create/update actions must:

1. Verify an action-specific nonce.
2. Verify the broad edit capability.
3. Resolve and validate the source object and type.
4. Verify `edit_post` for the source and target objects.
5. Validate that source and target languages are enabled site languages.
6. Enforce one object per language per translation group.
7. Cap block counts, nested item counts, segment counts, character counts, and total request/response bytes.
8. Rebuild accepted metadata from known keys and discard unknown keys.
9. Run translated content through existing type-specific sanitizers before saving.
10. Keep new translations as drafts until a human publishes them.

Remote translation additionally requires SSRF protection, exact endpoint validation, safe WordPress HTTP APIs, TLS verification, timeouts, limited redirects, response-size limits, generic user errors, and logs that contain no translated content or credentials.

Race conditions must be handled so two clone requests cannot create two translations for the same group/language pair.

## Suggested Delivery Workstreams

These are implementation stages, not reduced release scope. The first public release is complete only after every approved workstream is delivered and verified.

### Workstream 1: foundation and conflicts

- Language settings, curated locales, and validation.
- Known multilingual-plugin detection and a blocking admin notice/configuration gate.
- Translation group service and indexed lookup for every supported object type.
- Translation status, source fingerprint, reviewer, and dependency graph services.
- Review dashboard shell and secure server-side publishing gate.

### Workstream 2: routing and frontend context

- Implement the approved unprefixed-main/prefixed-secondary URL policy.
- Language resolution, preference storage, cache variation, previews, and redirects.
- Frontend `lang`, direction, self-canonical, reciprocal `hreflang`, `x-default`, and sitemap behavior for prefixed language URLs.
- Shared switcher resolver, renderer, and shortcode.
- Automatic integration in every DesignStudio Flow header.

### Workstream 3: extraction and self-hosted translation

- Explicit translatable-path contracts in block and content schemas.
- Bounded segment extractor/reassembler for blocks, WYSIWYG, forms, templates, WordPress content, and WooCommerce visitor-facing content.
- Self-hosted LibreTranslate configuration, health check, provider adapter, privacy controls, partial failures, and rate limits.
- `Machine prefilled` and `Needs review` workflow.

### Workstream 4: clone, review, and core Flow content

- Main-language-only clone-to-draft workflows.
- Pages, headers, footers, popups, notification bars, reusable templates, and all Flow headers.
- Dependency completeness reporting and publish blocking.
- Snapshot invalidation and safe regeneration.

### Workstream 5: forms, saved blocks, and portability

- Language-aware forms, visible validation messages, confirmation content, and notification emails while preserving stable field keys and submission semantics.
- Saved-block language groups and language-scoped sync.
- Import/export with portable group identity and old-to-new relationship mapping.
- Deletion, disable, uninstall, and recovery behavior.

### Workstream 6: WordPress, blog, and WooCommerce content

- Posts, taxonomies, blog template content, and archive labels.
- Product/shop templates and translated catalog content.
- Product/category/tag/attribute/variation visitor-facing translation without duplicating inventory, pricing, SKUs, orders, or identity-sensitive commerce records.
- Cart, checkout, account, transactional messages, and structured data audit where DesignStudio Flow controls the visible text.

## Remaining Decisions Before Implementation

The URL policy and core scope are now approved. These details still need a final answer:

1. Should the language registry expose regional locales such as `en-US` and `es-MX` from the first release (recommended), or only broad language codes such as `en` and `es`?
2. Should clone creation require the editor to enter the translated title and slug, or may it initially copy the main-language title/slug and mark both as needing review? Recommended: allow a copied draft but block review/publishing until the title is confirmed.
3. When main-language content changes, should a currently published translation remain public with a `Source changed` warning in admin, or immediately become unavailable until re-reviewed? Recommended: keep it public for minor edits, but remove it only when a required dependency becomes invalid or an editor explicitly marks the source change as translation-critical.

## Approved Release Baseline

Unless one of the remaining decisions changes it, the implementation uses these defaults:

- All visitor-facing DesignStudio Flow, WordPress blog, and WooCommerce catalog systems are in scope.
- Main-language URLs remain unprefixed; every secondary language uses a stable prefix and its own indexable URL.
- Main-language content is the only clone source.
- Every translation is a separate draft object requiring human approval.
- Required translated dependencies block publishing; there is no silent mixed-language fallback.
- The review dashboard is the central place for missing, stale, machine-prefilled, blocked, and ready translations.
- Public switchers show only reviewed, published siblings.
- Every Flow header includes the switcher whenever multilingual mode is enabled, and the shortcode remains available for non-Flow headers.
- Self-hosted LibreTranslate ships in the first release; all output remains draft/unreviewed.
- Forms translate both visitor-facing content and language-specific notification content without changing stable field keys.
- Notification bars and other global messages have one reviewed version per enabled language.
- Saved-block sync is language-scoped and cannot overwrite another language.
- Source changes never automatically overwrite human translations.
- Known translation plugins block DesignStudio Flow multilingual mode until an administrator disables them.

## Implementation Prompts

Use these prompts one at a time. Each prompt assumes the previous phase is complete and reviewed.

### Prompt 1: architecture verification

> Read `AGENTS.md`, `BLOCK-BUILDING-README.md`, and `MULTILINGUAL-FEATURE-README.md` in full. Do not write code yet. Audit pages, editor saves, frontend routing, SEO, every Flow header/footer, popups, forms, notification bars, saved blocks, reusable templates, blog/shop/product templates, WordPress posts/taxonomies, WooCommerce catalog objects, import/export, snapshots, caching, and publish transitions. Produce a file-by-file map for every approved workstream, identify trust boundaries and compatibility risks, inventory plugins that must conflict-block multilingual mode, and list only decisions that genuinely remain unresolved.

### Prompt 2: language settings and relationship service

> Implement only the approved language settings, multilingual-plugin conflict gate, translation relationship service, statuses/source fingerprints, dependency graph, and server-side publish gate from `MULTILINGUAL-FEATURE-README.md`. Follow `BLOCK-BUILDING-README.md`. Cover every approved object type. Add strict locale allowlists, nonce/capability/object checks, one-object-per-language-per-group enforcement, race protection, and focused tests for malformed values, duplicates, conflicts, permissions, stale reviews, missing dependencies, and migration of existing content into the main language. Do not add routing, cloning, switchers, or remote translation yet.

### Prompt 3: URL routing and SEO

> Implement only multilingual routing and frontend language/SEO output for the approved unprefixed-main/prefixed-secondary URL policy. Use separate translated objects linked by the relationship service. Add correct document language/direction, self-canonical URLs, reciprocal published-only `hreflang`, main-language `x-default`, and per-language sitemap behavior. Avoid duplicate tags when supported SEO plugins are active. Handle translated slugs/hierarchies, archives, pagination, collisions, previews, drafts, 404s, cache/CDN behavior, prefix migrations, and permanent redirects. Add focused tests. Do not add cloning, header UI, or LibreTranslate yet.

### Prompt 4: translatable contracts and extraction

> Add explicit translatable-path metadata to every applicable DesignStudio Flow block and content schema, then implement a bounded extractor/reassembler for blocks, nested repeaters, safe WYSIWYG text nodes, page/SEO data, layouts, popups, forms, saved blocks, templates, notifications, WordPress posts/taxonomies, and WooCommerce catalog content. Exclude URLs, IDs, enums, CSS, media, shortcodes, template tokens, form keys, SKUs, prices, inventory, order data, and internal anchors. Reassembled output must pass through existing type-specific sanitizers. Add focused malformed, oversized, XSS, unknown-field, and non-translatable-data preservation tests. Do not call any remote API.

### Prompt 5: self-hosted LibreTranslate

> Implement the self-hosted LibreTranslate provider behind the approved provider interface. Add encrypted server-side configuration and a content-free health/language-pair check. Use an exact configured endpoint, protect against SSRF, enforce TLS, timeouts, redirect/response/segment/character/rate limits, never log content or credentials, sanitize all returned segments, preserve partial failures, and always mark output `Machine prefilled` and `Needs review`. Manual editing must work during provider failure. Add mocked tests only; tests must never call the public internet or an unknown community instance.

### Prompt 6: clone, review dashboard, and publishing workflow

> Implement main-language-only clone-to-draft workflows and the central translation review dashboard across every approved object type. Clones must preserve safe structure/media/shared commerce identity, detach or language-map saved-block relationships, map translated dependencies, invalidate snapshots correctly, and never publish automatically. The dashboard must filter and display missing, draft, machine-prefilled, stale, blocked, ready, reviewed, and published states with blocking dependencies and direct actions. Enforce review and publishing on the server with nonce, broad capability, object capability, source fingerprint, duplicate/race, malformed, oversized, and XSS tests.

### Prompt 7: switchers and core Flow surfaces

> Implement one shared reviewed/published translation resolver and accessible renderer, the `[dsf_language_switcher]` shortcode, and automatic integration into every existing DesignStudio Flow header whenever multilingual mode is enabled. Then finish language-aware pages, headers, footers, popups, notification bars, and reusable Flow templates with strict dependency publishing blocks and no silent main-language fallback. Cover desktop/mobile, keyboard, touch, focus, long native names, RTL, snapshot mode, cache behavior, sanitization, editor/frontend parity, and focused PHP/Vue tests.

### Prompt 8: forms, saved blocks, and portability

> Implement complete multilingual forms, including visitor labels/options/help/validation/confirmation content and language-specific notification content while preserving stable field keys, recipients, submission semantics, entries, privacy, and security gates. Implement saved-block language groups and same-language-only sync so one language cannot overwrite another. Extend import/export to carry portable translation groups and rebuild mapped relationships after all objects import. Add deletion, disabling, recovery, malformed import, permission, sync isolation, form security, and XSS tests.

### Prompt 9: WordPress, blog, and WooCommerce content

> Implement multilingual WordPress posts/taxonomies and WooCommerce catalog content used by DesignStudio Flow, including products, product taxonomies, attributes, variations, templates, archives, cart/checkout/account-visible Flow text, and structured data where applicable. Translate only explicit visitor-facing fields; preserve shared product identity, SKUs, prices, taxes, stock, downloads, orders, and operational relationships. Add dependency review/publish gates, frontend context tests, add-to-cart and variation regression tests, SEO tests, permission tests, and proof that translation operations cannot duplicate or corrupt commerce records.

### Prompt 10: final verification and release review

> Audit the completed multilingual feature against `BLOCK-BUILDING-README.md` and `MULTILINGUAL-FEATURE-README.md`. Run focused and full JavaScript tests, PHP tests, modified-file PHP syntax checks, PHPCS, dependency audit, production build, `git diff --check`, and inspect generated asset manifests. Report every command and exact result, distinguish existing failures from introduced failures, list residual security/compatibility risks, and do not call the feature complete if any mandatory gate is unresolved.

## Acceptance Summary

The feature is ready for its first release only when an editor can configure languages, clone only from main-language content into separate drafts, optionally prefill through the private self-hosted LibreTranslate endpoint, review all supported content and dependencies in one dashboard, and publish only complete human-approved translations. Main-language URLs remain unchanged, secondary translations use stable language prefixes, and every URL produces correct self-canonical and reciprocal language metadata. Every Flow header and the standalone shortcode must resolve actual translated permalinks. Forms, saved blocks, templates, global content, WordPress/blog content, and WooCommerce catalog content must preserve their operational identity and must never let one language overwrite another. Another multilingual plugin must prevent DesignStudio Flow multilingual mode from activating until the conflict is resolved by an administrator.
