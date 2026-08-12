# DesignStudio Flow Multilingual Architecture Audit

**Date:** 2026-07-16

**Branch:** `languages`

**Phase:** Prompt 1 — architecture verification

**Implementation status:** No multilingual product code has been written in this phase.

## Purpose And Safe Checkpoint

This document is the required first checkpoint from `MULTILINGUAL-FEATURE-README.md`. It records the current architecture, the contracts needed by the approved multilingual release, the affected files for every workstream, the security and compatibility boundaries, and only the decisions that still require an answer.

The audit covered all repository README files:

- `README.md`
- `BLOCK-BUILDING-README.md`
- `MULTILINGUAL-FEATURE-README.md`
- `QUICK-RESTORE-FEATURE-README.md`
- `AGENTS.md`

It also inspected the plugin bootstrap, post types, admin settings, editor and AJAX save paths, frontend templates and contexts, SEO, headers and footers, popups, forms, notification bars, saved blocks, reusable templates, blog/shop/product templates, WordPress and WooCommerce integration, import/export/package handling, snapshots, redirects, caching, and Quick Restore.

Prompt 1 explicitly says not to write feature code. The safe next boundary is to review the decisions at the end of this document, then implement Prompt 2 only.

## Audit Outcome

The planned feature has not been implemented. There is currently no language registry, request-language resolver, translation relationship service, review state, dependency graph, publish gate, prefix routing, translated permalink map, language switcher, `hreflang`, translation extractor, or LibreTranslate provider.

The most important findings are:

1. Publishing is currently possible through many paths that do not share one policy gate. In particular, normal Flow page saves force `publish`.
2. Raw numeric IDs connect pages to layouts, popups, forms, saved blocks, and templates. Those references are language-blind and some resolvers silently fall back to a global object.
3. Post meta alone cannot enforce one object per translation group and language under concurrent requests.
4. Saved-block sync currently crosses all matching content without a language boundary.
5. Import/export and Quick Restore use allowlists that would discard new multilingual state unless they are expanded with the foundation.
6. WooCommerce's requirement for separate translations conflicts with the requirement to preserve one product, variation, inventory, SKU, cart, and order identity. This needs an explicit overlay model before Woo adapters are implemented.
7. A self-hosted LibreTranslate service is commonly private-network hosted, while the mandatory remote-request gate rejects private/link-local destinations and requires TLS. The supported network topology must be chosen before provider work.

## Approved Baseline — Do Not Reopen

The following are already approved by the feature plan:

- All visitor-facing Flow, WordPress blog, and WooCommerce catalog surfaces are release scope.
- The main language is unprefixed. Each secondary language has a stable prefix and an indexable URL.
- Main-language content is the only clone source.
- Translation work starts as a draft and requires human review.
- Missing required same-language dependencies block publishing. There is no silent mixed-language fallback.
- The central review dashboard owns missing, stale, machine-prefilled, blocked, ready, reviewed, and published views.
- Public switchers show actual reviewed and published siblings only.
- Every Flow template header gets the shared switcher while multilingual mode is enabled, unless the product later defines a verifiable site-level alternative.
- Self-hosted LibreTranslate is release scope, but its output is always unreviewed.
- Forms translate visible and notification copy while stable field keys and submission behavior remain unchanged.
- Notification/global messages have a reviewed version per enabled language.
- Saved-block synchronization is same-language only.
- Source changes never overwrite human translations.
- A known multilingual plugin prevents enabling Flow multilingual mode; Flow does not deactivate that plugin.

## Proposed Foundation Contracts

These are engineering conclusions from the current repository and should be implemented in Prompt 2 unless a genuine product decision below changes them.

### 1. Curated language registry

Store a versioned `dsf_multilingual_settings` option containing only validated registry identifiers and policy values:

- enabled state and migration state;
- main language;
- ordered enabled languages;
- stable, unique prefixes for secondary languages;
- missing-translation policy;
- source-change policy;
- schema version.

Derive native label, BCP 47/HTML language, WordPress locale, text direction, and provider code from a server-owned allowlist. Do not accept arbitrary locale strings, direction, labels, or prefixes from a request.

Existing content migration must be idempotent and batched. While migration is incomplete, feature state should be `enabling`, public routing should remain unchanged, and unassigned legacy objects should be treated as main-language objects only by the migration adapter.

### 2. Indexed translation relationships

Use a dedicated relationship table rather than relying on post meta as the authority. It must support post, term, and synthetic/global adapters.

Minimum logical fields:

| Field | Purpose |
| --- | --- |
| `id` | Internal row identity. |
| `group_uuid` | Portable translation-group identity. |
| `object_kind` | Allowlisted adapter kind, such as post, term, notification, or Woo overlay. |
| `object_subtype` | Allowlisted post type, taxonomy, or synthetic subtype. |
| `object_id` | Local object ID. |
| `language` | Curated registry identifier. |
| timestamps | Auditing and deterministic import behavior. |

Required database guarantees:

- unique `(object_kind, object_subtype, object_id)`;
- unique `(group_uuid, language)`;
- bounded, normalized values before SQL;
- transactional insert or an equivalent atomic duplicate check;
- deletion of one member never cascades to the entire group.

Post/term meta may mirror identifiers for queries and diagnostics, but it must not be the uniqueness authority.

### 3. Review state and fingerprints

The current labels overlap and should not be stored as one ambiguous enum. Model them as derived state plus explicit workflow facts:

- WordPress object status: draft/pending/published;
- machine-prefilled flag;
- reviewer ID and review timestamp;
- reviewed source fingerprint and fingerprint schema version;
- explicit translation-critical source-change flag;
- dependency and route eligibility computed by services.

UI labels are then derived:

- `Missing`: no member for an enabled language;
- `Draft`: member exists but is not reviewed/published;
- `Machine prefilled`: machine output is present and unreviewed;
- `Source changed`: reviewed fingerprint no longer matches;
- `Blocked`: a required dependency, route, hierarchy, or permission is invalid;
- `Ready for review`: content exists and structural checks pass;
- `Reviewed`: approved against the current fingerprint;
- `Published`: reviewed and publicly eligible.

The fingerprint must be SHA-256 over a canonical, versioned representation of sanitized visitor-facing source fields. It must exclude credentials, history records, generated snapshots, numeric relationship IDs, submissions, customer/order data, and operational commerce fields. Prompt 4 must replace whole-block fingerprinting with explicit translatable paths so non-translatable edits do not create unnecessary staleness.

### 4. Dependency graph

Store required dependency references by portable translation-group identity, not by the source language's raw numeric ID. Resolve the member for the target language at validation/render time.

Each edge needs:

- owner object/group;
- dependency object/group;
- dependency kind;
- required/optional classification;
- source path for a useful review error;
- cycle-safe traversal.

The publish gate must reject a required dependency when the same-language target is missing, unreviewed, unpublished, deleted, not viewable, or has an invalid route. Optional dependencies may be omitted, but must never silently resolve to a different language.

### 5. Central server-side publish gate

One service must validate publication before state is persisted. UI-disabled buttons and endpoint-specific checks are insufficient.

The gate must validate:

- enabled language and a valid relationship;
- object-level edit and publish capability;
- current reviewed source fingerprint;
- required dependency closure;
- valid, collision-free route/hierarchy where applicable;
- target object integrity and adapter-specific rules;
- no machine-prefilled/unconfirmed title or slug state;
- no conflict-blocking multilingual plugin.

It must cover direct Flow endpoints and native WordPress transitions, including REST, bulk/quick edit, scheduled transitions, XML-RPC/CLI where supported, import, and restore. Active template flags are public state and need the same eligibility check even when the backing post remains private.

Use WordPress's object-specific `edit_post` and `publish_post` checks for post-backed objects and the taxonomy's configured capabilities for terms. Keep `manage_options` for site-wide language configuration and synthetic global content. A new broad custom capability is not necessary for the foundation unless the product later requires a separate translator role.

## Current Object And Dependency Map

| Surface | Current storage/resolution | Multilingual requirement |
| --- | --- | --- |
| Flow pages | `page` plus `_dsf_blocks`, `_dsf_settings`, `_dsf_html_snapshot` | Separate translated page members; route, dependency, review, and snapshot isolation. |
| Headers/footers/layouts | `dsf_layout` and page setting IDs | Same-language required dependency; no global main-language fallback. |
| Popups | `dsf_popup`, page `popupId`, legacy inline fallback | Same-language group lookup; legacy fallback cannot mix languages. |
| Forms | `dsf_form`, block `formId`, entries/options | Translate visible and notification copy; preserve IDs, field names, recipients, logic, entries, and security. |
| Saved blocks | `dsf_saved_block`, top-level `savedBlockId` | Language-grouped instances and same-language-only synchronization. |
| Reusable templates | private template CPTs and raw IDs | Same-language dependency resolution and gated activation/public use. |
| Notification bar | one `dsf_notification_bar` option | Synthetic/global translation group with one reviewed version per language. |
| Product templates | `dsf_product_template`, rules and active flag | Language-aware template resolver and cache; translated display over canonical product identity. |
| Shop templates | `dsf_shop_template`, category IDs and active flag | Language-aware archive/template assignment and query context. |
| Blog templates | `dsf_blog_template`, category IDs and active flag | Language-aware archive/template assignment and query filtering. |
| WP posts/pages | normal posts/pages | Translated members, native editing, routes, archives, review, and publish interception. |
| WP categories/tags | normal terms | Translation relationship/route model and language-filtered archives. |
| Woo products/variations | canonical posts and `WC_Product` data | Translation overlay while preserving the canonical operational product/variation. |
| Woo terms/attributes | product taxonomies and variation values | Translate display labels/routes without changing operational variation matching. |
| Store utility pages | one configured cart/checkout/account page per surface | Prefix-aware aliases to the same operational pages and endpoints; never duplicate carts, accounts, or orders. |

### Current raw dependency locations

- `_dsf_settings.layout.headerTemplateId`
- `_dsf_settings.layout.footerTemplateId`
- `_dsf_settings.popupId`
- block `formId`
- top-level block `savedBlockId`
- product/category/tag/template assignment IDs
- reusable template references inside blocks and settings

`DSF_Frontend::get_assigned_layout_template_data()` currently falls back to a site-wide default layout. `DSF_Popup::resolve_page_popup()` can fall back to legacy inline popup data. Both need explicit same-language behavior before multilingual rendering is enabled.

## Publish And Public-State Transition Inventory

| Path | Current behavior/risk | Required interception |
| --- | --- | --- |
| `DSF_Ajax::save_page()` | Normal Flow page saves force `publish`; requested layout status may also publish. | Run the central gate or save as draft; never infer approval from an editor save. |
| `DSF_Ajax::publish_page()` | Calls `wp_update_post()` without review/dependency checks. | Gate immediately before the transition and again in the central WordPress hook. |
| `src/App.vue::savePage()` | Sends publish-oriented state for pages/layouts. | UI reflects server eligibility but cannot be authoritative. |
| Native post/page/CPT save | wp-admin, REST, bulk, quick edit and scheduled transitions bypass Flow AJAX. | Central transition/status interception with recursion-safe failures. |
| Form and popup saves | Independent handlers/native saving. | Apply relationship/workflow checks to public eligibility and dependencies. |
| Saved block/template creation | Several paths create published objects immediately. | Draft-first for translations; same-language uniqueness. |
| Product/shop/blog templates | Active meta can make a template public independently of post status. | Gate active flag and template resolver eligibility. |
| JSON import/package import | Can recreate published objects and raw relationships. | Import as non-public until mapped, reviewed, and dependency-valid. |
| Quick Restore | Restores post/meta payloads directly. | Restore workflow facts atomically, then re-evaluate public eligibility. |
| Native term/product updates | No Flow multilingual interception exists. | Adapter-specific capabilities, fingerprinting, and publication/visibility checks. |

## Frontend, Routing, SEO, And Cache Audit

### Routing

`DSF_Frontend` currently relies on normal WordPress/WooCommerce query resolution. There are no language rewrite rules, language query variables, prefixed permalink filters, request-language context, translated hierarchy handling, or `redirect_canonical` integration.

Two priority-1 redirect paths already exist:

- `DSF_Frontend::redirect_legacy_flow_urls()`;
- `DSF_Redirects::maybe_redirect()`.

Prompt 3 needs deterministic ordering among language-prefix migration redirects, Flow legacy redirects, the redirect manager, and WordPress canonical redirects. It must check reserved routes including REST, feeds, pagination, author/date/category/tag bases, Woo shop/product/taxonomy bases, account endpoints, `wc-ajax`, subdirectory installations, and multisite paths.

Do not build secondary URLs with string replacement. Native slug uniqueness cannot reliably represent identical slugs in separate virtual language namespaces, so Prompt 3 should use an indexed route map keyed by object/group and language, with unique normalized paths and actual permalink resolution.

### Frontend context

`templates/flow-*.php` call `language_attributes()`, which currently reflects the site locale rather than a resolved object language. Frontend data in `src/frontend/FrontendApp.vue` and the `use*Context` utilities has no trusted language identity. The server must resolve and localize language context; the browser must not infer it from a path.

Public `admin-ajax.php` requests lose the visible page prefix. Language supplied to product/category/search endpoints must be an allowlisted value bound to a validated source object, not trusted from an arbitrary request parameter.

### SEO

`DSF_SEO` has no relationship-based `hreflang`, `x-default`, translated canonical, or language-aware sitemap support. `og:locale` and JSON-LD `inLanguage` use the site locale. DSF disables its own output when Yoast, Rank Math, AIOSEO, or SEOPress is detected, but those plugins do not know Flow translation relationships automatically.

Prompt 3 should make DSF authoritative for translation relationships and alternate URLs, while using explicit adapters/filters to avoid duplicate canonical and metadata output from supported SEO plugins. Reciprocal alternates must include only reviewed, published, publicly viewable siblings; `x-default` points to the main-language member.

Canonical Woo objects with translated virtual routes also require a DSF sitemap provider or expansion layer. Core post/term sitemap providers emit only the canonical object URL.

### Snapshots and caches

- Pages/layouts use sanitized `_dsf_html_snapshot`; product/shop/blog templates render dynamic server context.
- A clone must never copy the source snapshot. The target starts without a snapshot and regenerates only after sanitized target content is saved.
- An empty snapshot submitted by the current save path may leave the old snapshot intact; translation mutations must explicitly invalidate it.
- Product/shop/blog template caches and several frontend request caches are not language-keyed.
- Prefixes create cache-safe variants only when cookies do not change the representation of the same URL.
- Prefix changes and translation state changes must invalidate siblings, affected archives, sitemaps, redirects, and registered full-page/CDN cache integrations.
- Provide a generic Flow invalidation action. Add targeted cache-plugin adapters only when their contract is verified.

## Flow Header And Footer Inventory

Template-scope headers requiring the shared switcher in Prompt 7:

- `header-mega-menu`
- `header-showcase-mega`
- `header-cutout-mega`
- `header-modern-mega`

Template-scope footers:

- `footer-dealers`
- `footer-commerce`

`header-mega-menu` already exposes manual `showLanguage` and `languageUrl` fields and renders a desktop-only globe link. It is not a translation resolver or accessible language switcher and should be migrated through the shared renderer rather than extended as a parallel system.

The repository also contains header-like content blocks (`landing-progress-header`, `landing-dock-header`, `shop-header`, and `blog-header`). Whether “every Flow header” includes these is a genuine scope decision recorded below.

## Forms, Saved Blocks, Imports, And Recovery

### Forms

Flow forms already provide stable form IDs, field names, and separate option label/value structures. Translation must change labels, help, validation, confirmation, and notification copy without changing values, recipients, conditional logic, entries, privacy behavior, nonces, uploads, spam controls, or redirects.

`assets/js/forms.js` contains hard-coded English success, failure, and reCAPTCHA messages. The resolved form language should be recorded with entries and notifications so processing does not depend on a later browser request.

Gravity Forms embeds require an explicit compatibility adapter/policy; conversion between Flow and Gravity Forms must not guess multilingual field identity.

### Saved blocks

`DSF_Ajax::sync_saved_block_instances()` currently rewrites matching `savedBlockId` instances across Flow pages/layouts/product/shop/blog templates without a language check and deletes snapshots. Prompt 8 must resolve the saved block's translation group to the owner's language and synchronize only that member.

### Import/export/package

`DSF_Import_Export::get_meta_keys_for_type()` and package builders do not include future language/workflow metadata. Package import has two-pass numeric ID remapping, but no portable translation-group pass.

Required order:

1. validate and sanitize package bounds and object types;
2. import all objects as non-public;
3. create or map portable group UUIDs;
4. rebuild same-language dependencies and routes from the local ID map;
5. re-run type-specific sanitizers and fingerprints;
6. evaluate review and publication gates.

The generic item importer currently writes allowlisted meta without always re-entering the type-specific sanitizer. That must be corrected when multilingual fields are added.

### Quick Restore

The Quick Restore README describes a plan, while the repository already contains `DSF_History`. Its explicit post type and meta allowlists omit future language, group, fingerprint, reviewer, dependency, and route state. Adding multilingual metadata without updating capture/restore atomically would produce partial restores. Credentials and provider configuration must remain excluded from history payloads.

## File-By-File Workstream Map

Names marked **new** are proposed boundaries, not files that currently exist.

### Workstream 1 — foundation and conflicts

| File | Required work |
| --- | --- |
| `designstudio-flow.php` | Activation/upgrade entry point and schema version coordination. |
| `includes/class-designstudio-flow.php` | Load/init services, install tables, defaults, batched legacy migration, cache invalidation hooks. |
| `includes/class-dsf-multilingual-settings.php` **new** | Curated registry, settings validation, enable/disable/migration state. |
| `includes/class-dsf-multilingual-conflicts.php` **new** | Active/network/MU/runtime conflict detection and admin notice; never deactivate another plugin. |
| `includes/class-dsf-translation-relationships.php` **new** | Indexed groups, atomic uniqueness, adapters, deletion semantics, portable UUIDs. |
| `includes/class-dsf-translation-workflow.php` **new** | Fingerprints, review facts, derived states, reviewer recording. |
| `includes/class-dsf-translation-dependencies.php` **new** | Required/optional graph, same-language resolution, cycle-safe validation. |
| `includes/class-dsf-translation-publish-gate.php` **new** | Central server enforcement for all public-state transitions. |
| `includes/class-dsf-post-type.php` | Register only safe mirror metadata and expose supported subtype adapters. |
| `includes/class-dsf-admin.php`, `templates/admin-settings.php` | Languages settings and conflict/migration status; no clone/review UI yet. |
| `includes/class-dsf-ajax.php` | Secure settings endpoints and route current publish endpoints through the gate. |
| `includes/class-dsf-forms.php`, `class-dsf-popup.php` | Foundation publish/dependency interception. |
| `includes/class-dsf-product-templates.php`, `class-dsf-shop-templates.php`, `class-dsf-blog-templates.php` | Gate active/public template state. |
| `includes/class-dsf-import-export.php`, `class-dsf-package.php`, `class-dsf-history.php` | Preserve foundation state safely without prematurely importing public eligibility. |
| `tests/` | Schema, malformed value, permission, conflict, duplicate/race, fingerprint, dependency, publish bypass, and migration tests. |

### Workstream 2 — routing and frontend context

| File | Required work |
| --- | --- |
| `includes/class-dsf-language-context.php` **new** | Resolve one trusted request language and expose locale/direction/context. |
| `includes/class-dsf-language-routing.php` **new** | Rewrite/query vars, route table, collision checks, preview/draft rules, hierarchy and archive resolution. |
| `includes/class-dsf-frontend.php` | Language-aware templates, dependencies, AJAX payloads, caches, and no mixed fallback. |
| `includes/class-dsf-seo.php` | Self-canonical, reciprocal alternates, `x-default`, OG/JSON-LD locale, sitemap providers/adapters. |
| `includes/class-dsf-redirects.php` | Prefix migration ordering, loops, collision-safe permanent redirects. |
| `templates/flow-page.php`, `flow-page-fullwidth.php`, `flow-universal.php` | Correct document language/direction and resolved dependencies. |
| `templates/flow-product.php`, `flow-shop.php`, `flow-blog.php` | Language-aware archive/catalog contexts without changing operational identity. |
| `includes/class-dsf-product-templates.php`, `class-dsf-shop-templates.php`, `class-dsf-blog-templates.php` | Language-keyed resolution and caches. |
| `includes/class-dsf-site-pages.php`, `class-dsf-store-pages.php` | Language-filtered search and prefix-aware canonical Woo utility endpoints. |
| `src/frontend/FrontendApp.vue`, `src/utils/use*Context.js` | Consume server-resolved language; never synthesize sibling URLs. |
| `tests/`, `src/**/__tests__/` | Routes, collisions, redirects, locale/RTL, canonical/alternate reciprocity, sitemap, cache, and SEO-plugin tests. |

### Workstream 3 — extraction and self-hosted translation

| File | Required work |
| --- | --- |
| `includes/class-dsf-blocks.php` | Explicit bounded translatable-path metadata for each applicable block; no string guessing. |
| `docs/ADDON-BLOCK-API.md` | Add-on contract for translatable paths and non-translatable invariants. |
| `includes/class-dsf-translation-extractor.php` **new** | Typed extraction for blocks, settings, rich text nodes, SEO, forms, templates, globals, WP and Woo adapters. |
| `includes/class-dsf-translation-reassembler.php` **new** | Exact path replacement followed by existing type-specific sanitizers. |
| `includes/class-dsf-translation-provider.php` **new** | Narrow provider interface and bounded result/error contract. |
| `includes/class-dsf-libretranslate-provider.php` **new** | Exact endpoint, encrypted credential use, health/pair test, limits, partial failure handling. |
| `includes/class-dsf-crypto.php` | Reuse/audit encryption without exposing credentials in browser, logs, exports, or history. |
| `includes/class-dsf-ajax.php` | Nonce, capability, object permission, payload bounds, safe provider orchestration. |
| `includes/class-dsf-forms.php`, `class-dsf-popup.php`, `class-dsf-notification-bar.php`, `class-dsf-seo.php` | Typed sanitizer re-entry for translated fields. |
| `tests/` | Malformed/oversized/XSS/unknown path, preservation, SSRF, TLS, redirect, timeout, response-size, secret/log, and partial-failure tests with mocks only. |

### Workstream 4 — clone, review, and core Flow content

| File | Required work |
| --- | --- |
| `includes/class-dsf-translation-cloner.php` **new** | Main-only, draft-only clone service; safe structure/media copying, dependency mapping, snapshot invalidation. |
| `includes/class-dsf-translation-review.php` **new** | Review queries/actions and blocking-reason presentation. |
| `includes/class-dsf-ajax.php` | Clone/review endpoints with nonce, broad/object caps, source/target validation, bounds, race protection. |
| `includes/class-dsf-editor.php`, `src/App.vue` | Localized current language, siblings, state and permitted actions. |
| `src/components/EditorDock.vue`, `PageSettingsModal.vue` | Language/status UI and safe direct actions. |
| `includes/class-dsf-admin.php`, review admin template/assets **new** | Central review dashboard and filters. |
| `includes/class-dsf-frontend.php` | Explicit snapshot invalidation/regeneration and same-language dependency use. |
| `includes/class-dsf-popup.php`, `class-dsf-notification-bar.php` | Language-aware core Flow adapters. |
| `tests/`, `src/**/__tests__/` | Permissions, races, stale review, blocked dependencies, clone integrity, snapshot and UI parity. |

### Workstream 5 — forms, saved blocks, and portability

| File | Required work |
| --- | --- |
| `includes/class-dsf-forms.php` | Language-specific visible/notification copy, stable schema identity, entry language, unchanged public security. |
| `assets/js/forms-builder.js`, `assets/js/forms.js` | Localized builder/runtime messages without weakening server validation. |
| `includes/class-dsf-ajax.php` | Same-language saved-block library, creation and synchronization. |
| `src/components/BlockLibrary.vue`, `SaveBlockModal.vue` | Language-aware reusable content actions. |
| `includes/class-dsf-import-export.php`, `class-dsf-package.php` | Portable group UUIDs, deferred relation mapping, sanitized non-public imports. |
| `includes/class-dsf-history.php` | Atomic multilingual capture/restore allowlists and eligibility re-evaluation. |
| delete/uninstall handling | Member-only deletion, orphan-safe groups, explicit disable/uninstall recovery policy. |
| `tests/`, `src/**/__tests__/` | Cross-language sync isolation, form security, malformed imports, mapping, restore, deletion and disable behavior. |

### Workstream 6 — WordPress, blog, and WooCommerce

| File | Required work |
| --- | --- |
| Native post/term adapter classes **new** | Editing, relationships, fingerprints, routes, capabilities, archives and review integration. |
| Woo translation overlay adapter **new** | Language display fields over canonical product/variation IDs; never duplicate stock/SKU/order identity. |
| `includes/class-dsf-product-templates.php` | Apply translated display overlays while native add-to-cart/variation inputs remain canonical. |
| `includes/class-dsf-shop-templates.php`, `class-dsf-blog-templates.php` | Language-filtered archives, terms, cards, pagination and template assignments. |
| `includes/class-dsf-site-pages.php`, `class-dsf-store-pages.php` | Language-filtered search and canonical cart/checkout/account behavior. |
| `includes/class-dsf-frontend.php`, `class-dsf-seo.php` | Translated structured output and routes without duplicate commerce entities. |
| `includes/class-dsf-blocks.php` and relevant Vue previews | Explicit catalog display translation contracts and editor/frontend parity. |
| `includes/class-dsf-package.php` | Portable overlays/term relationships without operational commerce data. |
| `tests/`, `src/**/__tests__/` | Add-to-cart, variation matching, filters, stock/SKU preservation, archives, structured data, permissions, SEO and corruption regression tests. |

## Trust Boundaries And Security Sinks

| Boundary | Untrusted input | Required controls before sink |
| --- | --- | --- |
| Language settings | Admin POST/AJAX values | Action nonce, `manage_options`, curated allowlists, prefix normalization/reserved-route checks, bounded arrays, known-key reconstruction. |
| Clone/review endpoints | IDs, language, action, target title/slug | Action nonce, broad capability, source and target object checks, language allowlist, main-source rule, bounds, duplicate/race protection, draft-only creation. |
| Native WP saves | REST/wp-admin/bulk/scheduled/CLI/plugin mutations | Central status/public-state interception and object-specific capability checks. |
| Relationship/dependency storage | IDs, UUIDs, kinds, languages | Adapter allowlists, existence/permission checks, prepared SQL, database uniqueness, transactions, cycle/bounds checks. |
| Import/package/history | JSON/ZIP/database payloads | Size/depth/count limits, known schema, type-specific sanitizer re-entry, safe mapping, non-public default, no credentials/private records. |
| URL path/cookies/AJAX language | Public request values | Server route resolution, allowlisted prefixes, no string URL synthesis, valid source binding, no cookie-varying indexable representation. |
| LibreTranslate | Configured URL, DNS, response, translated HTML/text | Exact endpoint, HTTPS/TLS policy, DNS/IP validation, SSRF defense, bounded redirects/timeouts/bytes/segments/rate, generic errors, sanitizer re-entry. |
| Flow/WP/Woo stored content | Meta, options, posts, terms, remote output | Treat stored values as untrusted; typed sanitization and context-appropriate escaping at every HTML/attribute/URL/JSON sink. |
| Public switcher/SEO | Relationship and route data | Published/reviewed/viewable filter, actual permalink, reciprocal validation, escaped labels/URLs/attributes. |
| Forms and Woo operations | Submission values, variation inputs, nonces, IDs | Preserve server schema, stable keys and operational values; never translate security tokens, recipients, IDs, attributes, SKUs, prices or order data. |
| Logs/telemetry | Errors, endpoint failures, content | Never log source/translated content, credentials, submissions, customer/order data, or private object content. |

Rich text and remote output are never trusted HTML. Reassembly must operate on approved paths/nodes, then pass through the same sanitizer used by a normal save. Rendering still requires contextual escaping. Generated snapshots are output caches, not trusted source data.

## Multilingual Conflict Inventory

Detection should compare the active plugin directory slug, not one assumed main filename, and must include multisite network-active plugins. MU plugins and renamed/custom loaders require conservative runtime signature checks. The list should be filterable so verified conflicts can be added without a schema change.

Initial conflict families:

- WPML (`sitepress-multilingual-cms` and related core loader)
- Polylang and Polylang Pro (`polylang`, `polylang-pro`)
- TranslatePress (`translatepress-multilingual`)
- Weglot (`weglot`)
- MultilingualPress (`multilingualpress`)
- qTranslate-X/qTranslate-XT (`qtranslate-x`, `qtranslate-xt`)
- GTranslate (`gtranslate`)
- WPGlobus (`wpglobus`)
- WP Multilang (`wp-multilang`)
- Falang (`falang`)
- ConveyThis (`conveythis-translate`)
- Linguise (`linguise`)
- Google Language Translator (`google-language-translator`)

These families control translated content, public language URLs, switchers, or proxy-rendered language output and therefore can conflict with Flow's approved routing/SEO ownership. Loco Translate is not a conflict by default because gettext catalog editing does not create parallel public content or language routes.

The local WordPress installation currently contains WooCommerce, Yoast SEO, WP Rocket, Elementor/Elementor Pro, and Gravity Forms, among others, but no known multilingual plugin was present during this audit. Yoast, WP Rocket, Elementor, Gravity Forms, and WooCommerce are compatibility targets, not conflict-blockers.

## Test And Verification Architecture

There are no multilingual tests today. The current PHP suite uses WP_Mock, which can cover validation and service behavior but cannot prove database uniqueness under races, real rewrite resolution, native REST/status transitions, core sitemap output, or WooCommerce request behavior by itself.

Prompt 2 should add:

- focused WP_Mock unit tests for settings, capabilities, fingerprints, dependency evaluation, conflict detection and publish decisions;
- a database-backed integration layer for table schema, unique constraints, transactions/races, migrations and deletion;
- real WordPress integration coverage for native status transitions, REST and scheduled/bulk paths;
- later real WooCommerce integration coverage for products, variations, carts, filters and endpoints;
- Vitest coverage for each editor/admin/frontend UI phase;
- no live external calls in tests.

Per `BLOCK-BUILDING-README.md`, each implementation phase must run applicable focused tests while iterating, then the full JavaScript and PHP suites, syntax checks for modified PHP, PHPCS, dependency audit, production build when frontend assets change, manifest inspection, `git diff --check`, and a worktree review. A release command must not be used as verification because it changes version and Git state.

## Genuine Decisions Still Unresolved

The first three are explicitly open in `MULTILINGUAL-FEATURE-README.md`. The recommended baseline applies unless changed:

1. **Locale granularity:** support regional locales such as `en-US` and `es-MX` in release one (recommended), or only broad codes.
2. **Initial title/slug:** permit copied main-language title/slug in an unreviewed draft, while blocking review/publish until each is confirmed (recommended), or require translated values during clone creation.
3. **Already-published stale translations:** keep public for minor source edits, but hide when a required dependency becomes invalid or an editor marks the change translation-critical (recommended), or unpublish on every source change.

The audit found these additional decisions that cannot be safely guessed:

4. **WooCommerce storage:** the plan says one real WordPress object per translation, but also forbids duplicate product/variation/inventory/SKU/order identity. Recommended: canonical Woo product and variation objects plus private language-specific translation overlays for visitor-facing content and routes. Operational IDs and data always remain canonical.
5. **Taxonomy storage:** use separate terms for ordinary WordPress categories/tags, or translation overlays. WooCommerce attribute terms/variation values should use display overlays unless a proven mapping preserves variation matching and filtering.
6. **Private LibreTranslate topology:** require a routable HTTPS endpoint that passes the strict SSRF policy (recommended for the simplest secure release), or support an explicitly configured private-network exception with administrator opt-in, exact host/IP allowlisting, DNS rebinding checks, TLS rules, and prominent risk documentation.
7. **Archive base translations:** translate only the language prefix and terminal object slug while keeping registered archive/taxonomy bases stable in release one (recommended), or translate base segments too, which materially expands rewrite/collision/redirect work.
8. **Missing translation behavior:** return a language-specific 404 (recommended) or perform an explicit, visible redirect. Rendering main-language content under a secondary-language URL is not an allowed option.
9. **Header scope:** apply automatic switchers only to the four template-scope `header-*` blocks (recommended), or also treat landing/shop/blog header-like content blocks as mandatory header integrations.
10. **Single-item portability and disable behavior:** define whether importing one translation creates a new one-member group or leaves it ungrouped, and whether disabling multilingual mode preserves secondary prefixed routes, redirects them, or makes them unavailable. Data must remain recoverable in every case.

The following are not open product questions and should be treated as engineering requirements: indexed atomic uniqueness, portable group UUIDs, server-side publish enforcement, exact capability checks, typed sanitization, actual-permalink switchers, no snapshot copying, no mixed-language fallback, and canonical Woo operational identity.

## Next Safe Action

Review decisions 1–10, especially WooCommerce storage and LibreTranslate topology. Once approved, implement only Prompt 2:

- language settings and idempotent main-language migration;
- multilingual conflict gate;
- indexed relationship service with atomic uniqueness;
- review facts and source fingerprints;
- dependency graph;
- centralized server-side publish gate;
- focused unit and integration tests.

Do not add routing, cloning, switchers, extraction, or remote translation during Prompt 2.
