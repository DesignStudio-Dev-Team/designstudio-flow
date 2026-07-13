# DesignStudio Flow Block-Building Guide

This is the required guide for creating or changing DesignStudio Flow blocks, block settings, headers, footers, popups, and forms.

It is written for both human developers and AI/LLM coding agents. Read the entire file before starting block or form work.

## Mandatory Rules

The words **MUST**, **MUST NOT**, **REQUIRED**, and **STOP** are intentional requirements.

1. Security is the first acceptance criterion. Visual completion never overrides secure data handling.
1. Treat every value from the browser, database, imported JSON, shortcode, REST request, AJAX request, URL, product record, or remote API as untrusted.
1. A block registration schema controls the editor UI. It does **not** automatically sanitize saved block settings.
1. Every new server-bound field MUST have type-specific validation and sanitization in PHP before storage or use.
1. Every PHP output MUST be escaped for its output context at the latest possible moment.
1. `v-html` MUST NOT be used unless the HTML has already passed through a documented server-side allowlist such as `wp_kses()` or `wp_kses_post()`.
1. Public forms MUST validate against the saved form schema on the server. Client validation is user experience only.
1. Privileged actions MUST require both a valid nonce and the correct capability/object permission.
1. Never weaken a nonce, capability check, allowlist, sanitizer, output escape, or upload restriction to make a feature work.
1. If the secure behavior is unclear, STOP implementation and resolve the threat model before continuing.

## Definition Of Done

A block is complete only when all of these are true:

- It renders through the same Vue component in the editor and frontend.
- It uses existing theme typography and responsive conventions.
- Its schema has safe defaults and bounded controls.
- Its saved data has server-side validation and sanitization.
- Its HTML, attributes, and URLs are safely rendered.
- Its `settings` are self-contained and portable, so saved-block sync and cross-site import work (see "Library, Sync, And Export Contracts").
- Interactive controls work with keyboard and touch input.
- Editor-only behavior does not leak into the frontend.
- Snapshot rendering does not trigger timers, network calls, form initialization, or body-level side effects.
- Focused tests cover normal, empty, malformed, and security-relevant inputs.
- The full JavaScript test suite, PHP checks, and production build pass.

## Architecture Map

| Concern | Primary file |
|---|---|
| Register block and settings schema | `includes/class-dsf-blocks.php` |
| Editor block component map | `src/components/BlockWrapper.vue` |
| Frontend block component map | `src/frontend/FrontendApp.vue` |
| Block Vue component | `src/components/blocks/*Preview.vue` |
| Shared settings renderer | `src/components/SettingField.vue` |
| Custom/repeater setting controls | `src/components/common/*Field.vue` |
| Block library icon and card | `src/components/BlockLibrary.vue` |
| Block library schematic | `src/components/common/BlockSchematic.vue` |
| Library category ordering | `src/App.vue` |
| Editor page save endpoint | `includes/class-dsf-ajax.php` |
| Saved-block / template AJAX, sync, imports | `includes/class-dsf-ajax.php` |
| JSON import / export + media sideload | `includes/class-dsf-import-export.php` |
| Editor data/defaults | `includes/class-dsf-editor.php` |
| Frontend data and snapshots | `includes/class-dsf-frontend.php` |
| Editor action dock (replaces the old top bar) | `src/components/EditorDock.vue` |
| Structure / block navigator panel | `src/components/StructurePanel.vue` |
| Media Library picker control | `src/components/common/MediaPicker.vue` |
| Landing icon set (`iconFor`, `LANDING_ICON_NAMES`) | `src/utils/landingIcons.js` |
| Vue component tests | `src/components/__tests__/` |
| PHP tests | `tests/` |

Blocks use one Vue rendering component in both the editor and frontend. Avoid separate markup implementations because they drift and create security inconsistencies.

## Step-By-Step Workflow

### 1. Define The Block Contract

Before editing code, write down:

- Block ID, display name, category, icon, and description.
- Whether it is a page, header, or footer block.
- Every setting, its data type, default, allowed values, and maximum size.
- Whether it needs inline editing, a custom field, WordPress media, WooCommerce data, AJAX, HTML, a form, or a remote API.
- Desktop, tablet, and mobile behavior.
- Empty and missing-data behavior.
- Security sinks: HTML rendering, URLs, redirects, uploads, SQL, remote requests, or public submission endpoints.

Use `template_scope: header` or `template_scope: footer` only for layout-template blocks. Ordinary page blocks use the default page scope.

### 2. Study Existing Patterns

Read these before copying code:

- `src/components/blocks/StarterBlockPreview.vue`
- One recent block with similar behavior.
- `includes/class-dsf-blocks.php`
- `src/components/SettingField.vue`
- The relevant tests.

Do not copy a pattern blindly. Existing code is context, not proof that a pattern is secure or correct.

### 3. Register The Block

Add the block in `includes/class-dsf-blocks.php` with:

```php
$this->register_block(
	array(
		'id'          => 'example-block',
		'name'        => 'Example Block',
		'category'    => 'content',
		'icon'        => 'layout-template',
		'description' => 'A short human-readable description.',
		'settings'    => array(
			'title' => array(
				'type'    => 'text',
				'label'   => 'Title',
				'default' => 'Default title',
			),
		),
	)
);
```

Requirements:

- IDs are lowercase kebab-case and never change after release.
- Defaults are generic, safe, and useful without setup.
- Select values are stable machine values, not translated labels.
- Numeric controls have reasonable `min` and `max` bounds.
- Conditional controls use `showWhen` or existing panel conventions.
- Repeaters have a server-enforced maximum item count.
- Do not place secrets, API keys, tokens, or passwords in block settings.

### 4. Create The Vue Component

Create `src/components/blocks/ExampleBlockPreview.vue`.

Expected props:

```js
const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})
```

Implementation rules:

- Use semantic HTML: `section`, headings in logical order, lists for lists, buttons for actions, and anchors for navigation.
- Use `InlineText` for appropriate editor text, but do not mutate unrelated props during render.
- Use theme tokens such as `--dsf-theme-heading-font` and `--dsf-theme-body-font`.
- Use `getResponsiveValue()` for responsive settings.
- Keep block styles in the component's scoped style unless a documented global integration requires otherwise.
- Use `type="button"` on every non-submit button.
- Add accessible names to icon-only controls.
- Add `aria-expanded`, `aria-controls`, dialog roles, and Escape handling where appropriate.
- Clean up event listeners, timers, observers, body classes, and scroll locks in `onUnmounted()`.
- Do not call browser APIs during setup without guarding server/snapshot contexts.

### 5. Wire Both Render Paths

Import and map the component in both:

1. `src/components/BlockWrapper.vue`
1. `src/frontend/FrontendApp.vue`

Missing either map causes editor/frontend drift or a generic fallback.

### 6. Add The Library Experience

- Add the icon to `src/components/BlockLibrary.vue` if it is not already mapped.
- Add a lightweight schematic branch to `src/components/common/BlockSchematic.vue`.
- Add the block ID to the proper ordering array in `src/App.vue`.
- Confirm search and category grouping work.

For a new header, `template_scope: header` makes it available in the header editor. Header templates intentionally permit exactly one header block.

### 7. Add Custom Settings Fields Only When Needed

Use existing types from `SettingField.vue` when possible. For custom repeaters or complex controls:

1. Create `src/components/common/ExampleField.vue`.
1. Accept `modelValue` and emit `update:modelValue`.
1. Use stable per-item editor IDs, but remove temporary IDs before saving if they are not part of the data contract.
1. Limit item counts and text lengths in both UI and PHP.
1. Add the custom type branch and import in `SettingField.vue`.
1. Add tests for add, remove, reorder, malformed values, and the maximum count.

Reusable building blocks for custom fields:

- Media Library images/videos: reuse `MediaPicker.vue`; it stores a plain URL string and opens `wp.media`. Store the URL under `settings` so import can sideload it.
- Editable preset icons: expose an `icon` value from `LANDING_ICON_NAMES` (`src/utils/landingIcons.js`) and render it with `iconFor(name)`. Unknown names fall back to a default glyph, so `sanitize_key()` is the correct save-time filter.
- Nav-style rows with a preset icon *or* a custom image per row: use the `dock_nav_links` field type (see "Library, Sync, And Export Contracts").

## Library, Sync, And Export Contracts

Saved blocks are now edited in place and synced, and all Flow content moves between sites as JSON. Blocks participate in these systems, so honor the following contracts.

### Block-Level Keys Beyond `settings`

A saved block instance is `{ id, type, settings, ... }`. The page-save sanitizer `sanitize_known_block_settings()` (in `includes/class-dsf-ajax.php`) rewrites only `settings` and passes every other top-level key through untouched. Two reserved top-level keys exist:

- `label` — an editor-only custom name shown in the Structure panel. Saved with `sanitize_text_field()` and length-capped. Never rendered on the frontend.
- `savedBlockId` — the post ID of the saved block an instance was inserted from. Drives global sync (below).

Rules:

- Everything a block renders MUST live under `settings`. Do not store render data at the top level.
- Any new top-level block key is passed through save **unsanitized by default**. If you introduce one, you MUST add explicit sanitization for it in `sanitize_known_block_settings()`, exactly as `label` does. Unsanitized passthrough of a browser-controllable key is a security defect.
- Do not read `label` or `savedBlockId` inside a block component. They are editor/library metadata, not render inputs.

### Saved Blocks Are Editable And Synced

Editing a saved block in wp-admin opens the DSFlow editor in a restricted single-block mode (`postType === 'dsf_saved_block'`): no add-block, no page chrome, just that block's settings. Saving calls the `dsf_save_library_item` AJAX action, which:

1. Updates the saved block's `_dsf_block_settings`.
1. Rewrites every instance across pages, headers/footers, and product templates whose `savedBlockId` matches, then deletes their `_dsf_html_snapshot` so they re-render.

Implications for block authors:

- A block's `settings` MUST be self-contained and portable. Do not depend on the surrounding page, sibling blocks, or a specific post ID at render time — the same `settings` are pushed into many pages.
- Sync is a push/overwrite; it replaces instance `settings` wholesale. Keep any intentionally per-instance data out of the saved-block workflow.
- An instance only carries `savedBlockId` when it was inserted from the library after this feature shipped. Never assume every instance of a type is linked.

### Export / Import And Media

Saved blocks, templates, pages, and layouts export to JSON (`includes/class-dsf-import-export.php`) via per-item, bulk, and "Export all" controls, and import as new posts (nothing is overwritten). On import, media URLs found inside these meta keys are downloaded into the destination Media Library and rewritten: `_dsf_blocks`, `_dsf_block_settings`, `_dsf_template_blocks`, `_dsf_settings`.

Rules for portable blocks:

- Store an image/video reference as a plain URL string in `settings` (the media picker already does). URLs buried inside WYSIWYG HTML are not migrated.
- Do not use a site-specific numeric ID (attachment, term, or post ID) as the *only* reference to content that must survive a move — it will not exist on the destination.
- Import re-runs the same per-type sanitizers, so a block with a proper sanitizer needs no import-specific code. A block with no sanitizer imports its settings unsanitized — one more reason every block MUST have one.

### Editor Chrome And The Structure Panel

- The editor top bar is gone; all actions live in the floating `EditorDock`. The canvas reserves bottom space via `--dsf-dock-clearance`; do not add fixed, bottom-anchored editor UI inside a block that would collide with it.
- The Structure panel lists blocks by their registered `name` (or a user `label`). Give every block a clear, distinct `name` so a page full of one block type stays legible.

### New Custom Field: `dock_nav_links`

`dock_nav_links` (`src/components/common/DockNavLinksField.vue`) is a repeater of `{ label, url, icon, iconImage }`, where `icon` is a preset from `LANDING_ICON_NAMES` and `iconImage` is an optional Media Library URL that overrides the preset. Sanitize it server-side with a dedicated array sanitizer (`sanitize_dock_nav_links`): `sanitize_text_field()` label, safe URL, `sanitize_key()` icon, `esc_url_raw()` image, with a capped count. Register the field default with the same `{ label, url, icon, iconImage }` shape.

### Testing These Paths

Endpoint changes here need PHP tests just like forms: `dsf_save_library_item`, `dsf_import_template`, and `dsf_import_saved_block` MUST test invalid/missing nonce, insufficient capability, wrong post type, malformed JSON, oversized arrays, and that sync only touches instances with a matching `savedBlockId`.

## Security Gate

### Trust Boundaries

Assume an attacker can bypass the Vue editor and send arbitrary requests directly to WordPress. UI controls, HTML input types, disabled fields, and client-side validation are not security controls.

The following are always untrusted:

- `$_GET`, `$_POST`, `$_REQUEST`, `$_FILES`, cookies, and request headers.
- JSON decoded from requests or imports.
- Saved post meta, including old or manually modified data.
- Shortcode attributes and block settings.
- WooCommerce or third-party plugin HTML.
- Remote API responses and webhook payloads.
- URLs entered by an administrator.

### Sanitization Matrix

Sanitize on input according to meaning, not convenience.

| Data | Save-time handling |
|---|---|
| Single-line text | `sanitize_text_field( wp_unslash( ... ) )` |
| Multi-line plain text | `sanitize_textarea_field( wp_unslash( ... ) )` |
| Integer ID | `absint()` and verify the referenced object/type/permission |
| Bounded integer | `intval()` followed by explicit min/max clamping |
| Decimal | `floatval()` followed by finite/range checks |
| Boolean | Explicit `! empty()` or strict accepted values |
| Enum/select | `sanitize_key()` followed by `in_array( ..., $allowed, true )` |
| Slug/key | `sanitize_key()` or `sanitize_title()` as appropriate |
| Email | `sanitize_email()` and then `is_email()` |
| URL for storage | `esc_url_raw()` plus scheme/host rules when needed |
| External HTTP URL | `esc_url_raw()` and `wp_http_validate_url()`; apply SSRF rules |
| Hex color | `sanitize_hex_color()` with a safe fallback |
| CSS class | Allowlist known class tokens; never accept arbitrary CSS |
| WYSIWYG HTML | `wp_kses_post()` or `wp_kses()` with a minimal explicit allowlist |
| Repeater/array | Verify array, cap count, sanitize every nested key, discard unknown keys |
| Uploaded file | WordPress upload APIs, MIME/extension allowlist, size limits, capability checks |

Never pass a complete settings array through one generic text sanitizer. Rebuild a clean array from known keys and discard everything else.

### Output Escaping

Escape at output according to context:

- Text node: `esc_html()`
- HTML attribute: `esc_attr()`
- URL attribute: `esc_url()`
- Textarea: `esc_textarea()`
- JSON in HTML: `wp_json_encode()` and safe attribute/script placement
- Allowed rich HTML: `wp_kses()` with the narrowest practical allowlist

Do not escape when saving and assume the value is safe forever. Sanitize for storage, validate for business rules, and escape again for the final output context.

Vue escapes interpolation such as `{{ title }}`, but Vue does not make these safe automatically:

- `v-html`
- `:href` and `:src` with attacker-controlled protocols
- inline `style` values assembled from arbitrary strings
- dynamic component names
- data later inserted by third-party scripts

Reject dangerous URL schemes such as `javascript:`. Allow relative site URLs and expected `http:`, `https:`, `mailto:`, or `tel:` schemes only when the feature requires them. Add `rel="noopener noreferrer"` to new-tab links.

### Rich HTML And Snapshots

`v-html` is an explicit security sink.

- WYSIWYG content MUST be sanitized in PHP before storage or frontend localization.
- Raw `<script>`, inline event handlers, untrusted iframes, `javascript:` URLs, and arbitrary SVG are forbidden by default.
- Snapshot sanitization is defense in depth, not a replacement for save-time sanitization.
- Never trust content merely because only administrators normally use the editor. Administrator sessions can be targeted through CSRF or compromised accounts.
- Add a test proving scripts, event attributes, and dangerous URLs are removed.

### AJAX And REST

Privileged AJAX handlers MUST follow this order:

1. Verify the action-specific nonce.
1. Verify the broad capability, such as `edit_pages`.
1. Resolve the target object.
1. Verify object-level permission with `current_user_can( 'edit_post', $post_id )`.
1. Unslash request values.
1. Decode JSON and reject malformed/non-array data.
1. Validate allowlists, types, counts, sizes, ownership, and state transitions.
1. Sanitize every accepted field.
1. Perform the operation.
1. Return a minimal response without secrets or internal errors.

A nonce proves request intent; it is not authorization. Never use a nonce without a capability check for privileged actions.

REST routes require a real `permission_callback`. Never use `__return_true` for write, private, administrative, or user-specific routes.

### SQL, Shortcodes, And Remote Requests

- Use WordPress query APIs whenever possible.
- Use `$wpdb->prepare()` for every dynamic SQL value. Never concatenate user input into SQL.
- Allowlist dynamic column names and sort directions because placeholders do not secure SQL identifiers.
- Do not run arbitrary user-provided shortcodes for unauthenticated visitors.
- Do not use `eval()`, dynamic PHP includes, or executable code stored in settings.
- Use `wp_safe_remote_get()` / `wp_safe_remote_post()` where possible.
- Protect remote requests from SSRF: allow expected schemes/hosts, reject loopback/private/link-local targets, set timeouts, limit redirects, and limit response size.
- Webhook secrets must not be returned to the browser, logged, or exported casually.

## Form Security Gate

Forms are a higher-risk subsystem because anonymous visitors can submit data. Any form-related change MUST satisfy every applicable item below.

### Form Builder Saves

- Require an editor nonce.
- Require `edit_pages` and `edit_post` for the exact form.
- Confirm the post exists and is a `dsf_form`.
- Allowlist form status transitions.
- Sanitize the form schema recursively.
- Cap rows, fields, options, conditional rules, label lengths, and payload bytes.
- Generate server-safe field names; do not trust submitted names.
- Sanitize notification recipients and never allow email-header injection.

### Public Submissions

- Verify the global frontend nonce and the form-specific nonce.
- Confirm the form exists, is the correct post type, and is published unless the user can edit it.
- Treat nonces as CSRF protection, not spam protection.
- Apply spam protection such as configured reCAPTCHA, a honeypot, and/or rate limiting.
- Load the saved form schema on the server.
- Ignore submitted keys that are not present in that schema.
- Enforce required fields on the server.
- Validate by field type on the server: email, URL, number ranges, dates, option membership, checkbox arrays, and maximum lengths.
- Strip values for conditionally hidden fields.
- Set a strict request/payload size limit before expensive processing.
- Never trust a client-provided recipient, subject, redirect, form status, field type, price, or webhook destination.
- Return generic public errors; log detailed errors without personal data or secrets.
- Escape submitted values again in admin entry screens and notification templates.

### File Uploads

Do not add or enable file uploads without a dedicated security review and tests.

Required controls:

- Process files from `$_FILES`, never a client-provided path.
- Require a per-form upload capability/configuration and a strict file-count limit.
- Enforce server-side byte limits.
- Allowlist extensions and MIME types using WordPress file APIs.
- Reject executable/server-parsed formats, double extensions, null bytes, SVG by default, and MIME mismatches.
- Use `wp_handle_upload()` or the appropriate WordPress media API with unique generated names.
- Store outside executable locations when possible.
- Never execute, include, unzip, or render uploaded files as HTML.
- Do not expose private uploads through predictable public URLs.
- Consider malware scanning and retention/deletion policy.

### Redirects And Notifications

- Validate redirects with `wp_http_validate_url()` and prefer `wp_safe_redirect()` for same-site navigation.
- If external redirects are allowed, document and allowlist them deliberately.
- Sanitize and validate every recipient with `sanitize_email()` and `is_email()`.
- Prevent newline/header injection in subjects, names, reply-to values, and webhook headers.
- Escape all entry values in HTML email templates.
- Never put secrets or sensitive form values into URLs.

### Privacy

- Collect only necessary data.
- Do not log full submissions, passwords, payment information, authentication tokens, or secrets.
- Document retention and deletion behavior for entries/uploads.
- Protect entry views and exports with object-level capabilities and nonces.
- Do not cache pages or API responses containing private submissions.

## Testing Requirements

Create a focused Vitest file in `src/components/__tests__/` for each new block.

At minimum test:

- Defaults render.
- User settings render.
- Empty arrays/strings do not crash.
- Malformed optional data falls back safely.
- Conditional controls appear and disappear correctly.
- Editor link clicks do not navigate.
- Frontend links/actions work.
- Responsive or layout classes/styles apply.
- Timers/listeners are cleaned up.
- Accessibility state changes correctly.
- Dangerous HTML/URLs are rejected or stripped at the server boundary.

Form and endpoint changes also require PHP tests for:

- Invalid/missing nonce.
- Insufficient capability.
- Wrong post type or object ownership.
- Unknown fields and enum values.
- Oversized arrays and payloads.
- Required/type validation.
- XSS payloads, dangerous URLs, and malformed JSON.
- Upload extension/MIME/size failures when applicable.

## Verification Commands

Run from the plugin root:

```bash
# Focused JavaScript test while iterating
./node_modules/.bin/vitest run src/components/__tests__/ExampleBlockPreview.spec.js

# Full JavaScript suite
npm run test:run

# PHP tests
npm run test:php

# WordPress coding/security standards
./vendor/bin/phpcs --standard=phpcs.xml.dist .

# PHP syntax for every modified PHP file
php -l includes/class-dsf-blocks.php

# Dependency audit
npm run security-check

# Production assets
npm run build
```

Also run `git diff --check` and inspect `git status --short`.

Vite creates a hashed shared file such as `assets/js/main-HASH.js`. Confirm `assets/.vite/manifest.json` points to the new file and remove only the superseded generated chunk created by your own build. Do not delete unrelated worktree changes.

### Interpreting Security Checks

- A zero-vulnerability dependency audit covers known dependency advisories only. It does not prove application security.
- Unit tests prove only the cases they assert. They do not replace threat modeling, validation, sanitization, escaping, or authorization.
- A non-zero PHPCS exit means the check did not pass. Do not describe it as passing.
- The repository can contain legacy PHPCS findings, including findings from the packaged duplicate source tree. Always run PHPCS on each modified PHP file as well as the full repository report.
- Separate formatting findings from security findings, but do not waive nonce, capability, escaping, SQL, upload, or unsafe-function findings without a documented manual review.
- Never run PHPCBF across the whole dirty repository without approval; it can rewrite unrelated user work.
- Do not claim that DesignStudio Flow, a block, or a form is "secure" based on one tool. Report what was checked, what failed, and any residual risk.

## Final Review Checklist

### Product

- [ ] The block matches the requested behavior.
- [ ] Defaults are generic and editable.
- [ ] Editor and frontend match.
- [ ] Desktop, tablet, and mobile layouts work.
- [ ] Empty and long content are safe.
- [ ] The block is available in the correct library category/scope.

### Security

- [ ] Every input has a server-side type, allowlist, limit, and sanitizer.
- [ ] Unknown nested keys are discarded.
- [ ] Any new top-level block key beyond `settings` is explicitly sanitized in `sanitize_known_block_settings()`.
- [ ] PHP output is escaped by context.
- [ ] Every `v-html` source is documented and sanitized in PHP.
- [ ] URLs reject dangerous protocols.
- [ ] Privileged endpoints check nonce plus capability plus object permission.
- [ ] Public forms validate against the saved server schema.
- [ ] SQL, uploads, redirects, webhooks, and remote requests passed their dedicated gates.
- [ ] No secrets or personal data are exposed to frontend localization, logs, or error messages.

### Quality

- [ ] Semantic HTML and keyboard behavior are correct.
- [ ] `settings` are self-contained and portable for saved-block sync and cross-site import.
- [ ] All listeners, timers, observers, and body mutations are cleaned up.
- [ ] Snapshot mode has no side effects.
- [ ] Focused and full tests pass.
- [ ] Modified PHP files pass syntax checks.
- [ ] Production assets build and the manifest points to the current chunk.
- [ ] No unrelated files were reverted or overwritten.

## Instructions For AI/LLM Agents

When an AI agent is asked to add or change a block or form, it MUST:

1. State that it read this guide.
1. Inspect the current code before proposing or editing.
1. Identify trust boundaries and security sinks before implementation.
1. Prefer existing shared components and patterns, while auditing them rather than assuming they are safe.
1. Implement the full editor, frontend, sanitizer, tests, and build path.
1. Never claim a security check passed unless the command actually ran successfully.
1. Report any security check that could not run and any residual risk.
1. Refuse to bypass security controls for speed or visual convenience.

If a request conflicts with this guide, pause and surface the tradeoff before changing code.
