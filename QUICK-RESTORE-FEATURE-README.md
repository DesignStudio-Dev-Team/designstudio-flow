# DesignStudio Flow Quick Restore Feature Plan

Status: approved product and architecture plan only. No implementation has started.

This plan must be used together with `BLOCK-BUILDING-README.md`. Quick Restore touches page, template, header/footer, popup, form, global-setting, AJAX, sync, import, and snapshot paths. Every implementation phase must follow that guide's sanitization, output-safety, authorization, testing, and production-build requirements.

## Approved Product Decisions

- Keep the current state plus the two previous valid states of every supported DesignStudio Flow object.
- Include DesignStudio Flow global settings, with two previous states per settings group.
- The first release does not need a visual side-by-side comparison.
- Each restore point shows timestamp, editor, reason, and a concise change summary.
- Restoring an older version first saves the current state, so a restore can be undone.
- This feature is named `Quick Restore` or `Flow History`, not `Full Backup`.

## Product Goal

Protect editors from recent mistakes without requiring a database administrator or a full-site backup restore.

An editor should be able to:

1. Save Flow content normally.
2. Open History from the Flow editor.
3. See the two most recent previous versions.
4. Identify when each version was created, who made the change, why it changed, and a short summary.
5. Restore one version after confirmation.
6. Undo that restore by restoring the automatically preserved former current state.

An administrator should also have a central Quick Restore screen for every supported Flow object and global settings group.

## What Quick Restore Is And Is Not

Quick Restore stores recent DesignStudio Flow configuration and content state in the WordPress database.

It is not:

- A WordPress database backup.
- A Media Library backup.
- A server or filesystem backup.
- Disaster recovery after database loss.
- A replacement for off-site backups.
- A backup of orders, submissions, customers, credentials, or operational logs.

If an image or video is deleted from the Media Library, restoring a Flow version can restore its old reference but cannot recreate the deleted file. The UI must state this clearly.

## Why A Dedicated History Service Is Recommended

Some current Flow post types declare WordPress revision support, but Flow's important data lives across many post-meta keys, custom AJAX save paths, dedicated form/popup saves, saved-block synchronization, and global WordPress options. Native WordPress revisions do not currently provide one consistent restore contract for all of that state.

Use one dedicated server-side `DSF_History` service with per-content-type capture and restore adapters. Do not implement separate ad hoc backup logic in every Vue component or AJAX handler.

## Retention Model

For every source object or settings group:

- `Current` remains in its normal post, post meta, or WordPress options.
- Quick Restore stores at most two previous distinct valid states.
- A restore point is created immediately before a successful mutation.
- A restore point is created only if the incoming sanitized state differs from the current sanitized state.
- Identical saves do not consume a history slot.
- After a successful mutation, older history is pruned so only two previous states remain.

Example:

```text
Current: Version C
History: Version B, Version A

Restore Version A

Current: Version A
History: Version C, Version B
```

Restoring a version does not create an unlimited chain. The state that was current immediately before restore becomes the newest restore point, and the oldest excess state is pruned.

## Supported Content

### Post-backed Flow objects

- DesignStudio Flow pages.
- Headers and footers (`dsf_layout`).
- Popups (`dsf_popup`).
- Forms (`dsf_form`).
- Saved blocks (`dsf_saved_block`).
- Reusable templates (`dsf_template`).
- Product templates (`dsf_product_template`).
- Shop templates (`dsf_shop_template`).
- Blog templates (`dsf_blog_template`).
- Any future visitor-facing Flow object must register a history adapter before it is considered complete.

### Global Flow settings groups

Keep separate histories so restoring typography does not unexpectedly restore unrelated mail or language settings:

- Theme colors and typography.
- Default header/footer and whole-site layout behavior.
- SEO defaults.
- Notification bar settings.
- Multilingual language configuration and routing settings.
- Redirect rules, excluding hit counters and logs.
- Forms/global spam settings, excluding secrets.
- Product and editor feature toggles.
- Other non-secret portable Flow settings included in the existing site-package contract.

Global settings restoration requires `manage_options`. Content restoration requires the broad edit capability plus object-level `edit_post` permission.

## Explicit Exclusions

Never store these in Quick Restore payloads:

- Form entries or submitted personal data.
- WooCommerce orders, customers, payment data, sessions, carts, or inventory history.
- Passwords, OAuth refresh/access tokens, SMTP credentials, webhook secrets, API keys, GitHub tokens, reCAPTCHA secrets, or LibreTranslate credentials.
- Connection secrets or authentication headers.
- Raw request data, cookies, or visitor information.
- Media file bytes.
- Generated `_dsf_html_snapshot` markup.
- Cache data, transient data, analytics counters, redirect hit counts, or debug logs.
- Plugin binaries, themes, WordPress core, or database tables unrelated to the history feature.

External connection definitions may be captured only through an explicit non-secret allowlist. Restored external connections must remain disabled until an authorized administrator reviews them, so restoring a version cannot unexpectedly fire webhooks or messages.

## Recommended Storage Architecture

Use a dedicated WordPress database table rather than relying on post revisions or a public/custom post type. The feature needs indexed lookup for post-backed objects and option-backed settings groups, strict retention, bounded payloads, and one consistent schema.

Suggested logical record fields:

- Numeric history record ID.
- Source kind: `post` or `settings`.
- Source object ID for post-backed content, or a validated settings-group key.
- Source object type.
- Payload schema version.
- Sanitized JSON payload.
- SHA-256 hash of the canonical payload.
- Sanitized change summary.
- Mutation reason: manual save, saved-block sync, bulk layout apply, import overwrite, settings save, or restore.
- Creating user ID.
- UTC creation timestamp.

Required indexes:

- Source kind plus source identifier plus creation time.
- Source object type for the administrator history screen.

Do not store PHP serialized objects, executable data, closures, arbitrary class names, or raw browser JSON. Payloads must be arrays rebuilt from known allowlisted fields and encoded with WordPress JSON APIs.

The installation/migration routine must use the WordPress database upgrade pattern, be idempotent, and never delete current Flow content if table creation or migration fails.

## Capture Contracts

Each supported source type registers a capture adapter that returns a complete, sanitized, bounded, portable state.

### Pages and layout/template objects

Capture only applicable fields:

- Post title, slug, parent, status, and selected safe post attributes.
- `_dsf_blocks`.
- `_dsf_settings`.
- Layout/template type and assignment settings.
- Saved-block/template-specific Flow metadata.
- Multilingual language/group/review metadata when that feature exists.

Do not capture author ownership changes casually. Restoring content must not transfer ownership to the editor performing the restore unless that is a separately authorized action.

### Forms

Capture:

- Title and publish state.
- Sanitized `_dsf_form_rows` schema.
- Sanitized `_dsf_form_settings` with secrets excluded.
- Safe multilingual/review metadata when available.

Never capture form entries. Restoring a form changes its future rendering and submissions only; it must not rewrite or delete historical entries.

### Popups

Capture the title, status, sanitized popup settings, and safe language/review metadata. Restore through the same `DSF_Popup::sanitize_settings()` contract used by normal saves.

### Global settings

Each group has an explicit list of option keys and a dedicated sanitizer. Never back up an arbitrary option name supplied by the browser.

## When Restore Points Are Created

Create a restore point before these successful state-changing operations:

- Manual Flow editor save.
- Form builder save.
- Popup save.
- Saved-block edit and the synchronized update of every affected page/template instance.
- Header/footer default changes that modify existing Flow objects.
- Global Flow settings save.
- Notification bar save.
- Multilingual clone, review, relationship, and routing changes once implemented.
- Import only when an operation intentionally overwrites an existing supported object or settings group. Imports that create new objects have no previous state to capture.
- Any bulk operation that changes supported existing Flow content.
- Quick Restore itself.

Do not create restore points for:

- Read-only previews.
- HTML snapshot generation.
- Autosave-like requests that do not change the canonical sanitized payload.
- Analytics/hit updates.
- Public form submissions.
- Add-to-cart, checkout, order, or customer activity.

## Save Ordering And Failure Behavior

For an ordinary save:

1. Verify nonce and authorization.
2. Parse, validate, bound, and sanitize the proposed new state.
3. Capture and canonicalize the current state.
4. Compare current and proposed hashes.
5. If unchanged, return success without a history record.
6. If changed, persist the current state as a restore point.
7. Confirm the restore point was written successfully.
8. Apply the new state.
9. Invalidate/regenerate derived snapshots as appropriate.
10. Prune history to two previous states.

If the current state cannot be captured or the restore point cannot be stored, do not silently continue with a protected save. Return a clear error and leave the current content unchanged. The UI may offer retry but must not weaken authorization or data validation.

For a bulk operation, preflight and create restore points for every object before mutating any target. If preflight fails, abort the bulk mutation and report which object prevented it. Since WordPress installations may not provide transactional storage across all engines, the implementation must also report and recover safely from partial database failures.

## Restore Workflow

1. The user opens History for an object or settings group.
2. The server returns at most two metadata summaries; payloads are not localized to JavaScript until a specific authorized restore requires them.
3. The user chooses Restore.
4. A confirmation dialog identifies the version, timestamp, editor, summary, and any known missing media/dependencies.
5. The restore request sends the history record ID, source identity, expected current-state hash, and action-specific nonce.
6. The server rechecks broad and object-level permissions.
7. The server verifies that the history record belongs to the requested source.
8. The server validates the payload schema version and migrates it through explicit safe migrations if needed.
9. The server runs the payload through the current type-specific sanitizers.
10. The server refuses stale concurrent requests when the expected current hash no longer matches.
11. The server saves the current state as the newest restore point.
12. The server applies the restored state through the registered restore adapter.
13. The server invalidates derived snapshots and caches; it never restores saved snapshot HTML.
14. The server prunes to two previous states and returns the new current summary.

Restoring must not send emails, submit forms, call webhooks, run arbitrary shortcodes, translate content remotely, or trigger customer/order actions.

## Change Summaries

Summaries are generated on the server by comparing sanitized old and new states. They are not supplied by the browser.

Useful summary examples:

- `Updated hero heading and button text; changed SEO title.`
- `Added 2 blocks; removed 1 block; reordered page sections.`
- `Changed default header from “Header A” to “Header B”.`
- `Updated 3 form fields and confirmation message.`
- `Saved block sync updated 4 linked instances.`
- `Changed Spanish prefix and enabled French.`
- `Restored version from July 14, 2026 at 3:42 PM.`

Summary requirements:

- Cap summary length and item count.
- Use registered block/field labels where safe and available.
- Never include secrets, full rich text, submitted data, raw HTML, or remote error bodies.
- Fall back to `Updated Flow content` when a safe useful summary cannot be produced.
- Escape summaries as text everywhere they render.

## User Interface

### Flow editor history

Add a History action to the floating editor dock for every supported editor mode.

The history panel shows:

- `Current version` with current modified time and editor when available.
- Up to two previous versions.
- Timestamp in the site's configured timezone.
- Editor display name, safely escaped.
- Mutation reason.
- Change summary.
- Restore button for users authorized to restore that object.

The panel does not render raw backup JSON.

### Administrator Quick Restore screen

Add a DesignStudio Flow admin screen with:

- Search by object title.
- Filters for object type, language, editor, and date where applicable.
- Current status and latest history timestamp.
- Direct link to edit the source object.
- Expandable list of its two restore points.
- Restore action with confirmation.
- Separate Global Settings section grouped by settings domain.

Do not add a bulk restore action in the first release. Bulk restore can cause large, unclear cascades and requires a separate transaction/recovery design.

## Global Settings Restore Rules

- Restore one settings group at a time.
- Require `manage_options` and an action-specific nonce.
- Run every option through its current sanitizer.
- Exclude secrets even if an older payload somehow contains them.
- Theme/typography restoration invalidates affected snapshots/caches but does not restore snapshot HTML.
- Default header/footer restoration must preview the number of Flow objects affected. Before applying any cascade, create restore points for all affected objects.
- Language prefix restoration must pass route-collision checks, flush rewrite rules only after success, update SEO routing, and create required redirects where the multilingual plan requires them.
- Redirect-rule restoration preserves rules but never restores hit counts.
- Restoring settings must not reactivate an external integration or conflicting multilingual plugin automatically.

## Saved Block Sync And Cascades

Saved-block synchronization can modify many objects from one editor action.

Required behavior:

- Back up the saved block before changing it.
- Identify every linked instance that will actually change.
- Back up every affected page/template before applying sync.
- If any required restore point cannot be written, abort the sync before modifying targets.
- Use mutation reason `saved_block_sync` on affected-object restore points.
- In multilingual mode, sync and history remain language-scoped.
- Restoring one affected page restores that page only; it does not silently roll back the saved block or every other page.
- Restoring the saved block does not automatically cascade unless the confirmation explicitly states that a new sync will occur and all affected objects pass backup preflight.

## Concurrency And Integrity

- Use canonical JSON and a stable SHA-256 hash for comparisons.
- Reject restore requests when the current hash changed after the panel was loaded.
- Prevent overlapping restore/save operations from creating more than two retained states or restoring the wrong source.
- A history record ID alone is never sufficient authorization; verify source kind, source ID/key, type, and permissions.
- Add bounded retries or a per-source lock using a safe WordPress mechanism. Locks must expire and must not permanently strand an object.
- Never trust an old history payload merely because DesignStudio Flow created it. Revalidate it against the current schema before restore.
- Unknown keys are discarded.
- Unsupported future schema versions are refused with a clear update-required error.

## Payload Limits And Cleanup

- Define a bounded maximum uncompressed payload size per restore point.
- Reject or safely report oversized captures before changing current content.
- Cap nested arrays through the existing per-block/form/template sanitizers.
- Retention cleanup runs synchronously for the just-saved source so the two-version promise is immediate.
- A scheduled maintenance job may remove orphaned records whose source object was permanently deleted.
- Moving an object to Trash keeps its history temporarily; permanent deletion removes its history according to a documented retention rule.
- Plugin uninstall must not silently delete history unless the administrator explicitly selected destructive cleanup.

## Security Gates

Every history list/read/restore/delete endpoint must:

1. Verify an action-specific nonce.
2. Verify the broad capability.
3. Resolve and validate the exact source object or allowlisted settings group.
4. Verify object-level permission for post-backed content.
5. Verify the history record belongs to that source and expected type.
6. Enforce response and payload size limits.
7. Return metadata only unless the server is actively performing an authorized restore.
8. Re-sanitize restored data through the current source adapter.
9. Escape every title, editor name, reason, summary, date, and URL in the UI.
10. Avoid logging payloads, secrets, personal information, or raw restored content.

There is no public or unauthenticated history API.

## Testing Requirements

### PHP tests

- First change creates one prior version.
- Second change retains two prior versions.
- Third change prunes the oldest version.
- Identical saves create no restore point.
- Restore preserves the former current state and remains limited to two versions.
- Restore rejects invalid/missing nonce, insufficient capability, and failed object-level permission.
- A record cannot restore into another object or settings group.
- Malformed JSON, unknown keys, oversized payloads, future schema versions, and XSS values are rejected or safely sanitized.
- Secrets, entries, orders, logs, counters, and snapshots never enter payloads.
- Current-hash mismatch rejects a stale restore.
- Failed capture/history write leaves current content unchanged.
- Saved-block sync backs up every affected object before mutation and aborts on failed preflight.
- Form restore never changes/deletes entries or sends notifications.
- Popup restore uses its current sanitizer.
- Global settings restore is group-scoped and administrator-only.
- Default layout and language-route cascades back up affected content before changing it.
- Import-created objects do not create meaningless empty history; overwrite operations do.
- Permanent deletion/orphan cleanup follows the approved retention rule.

### Vue/editor tests

- History action appears in supported editor modes.
- At most two previous entries render.
- Timestamp, editor, reason, and summary render safely.
- Empty history state is clear.
- Restore confirmation identifies the selected version.
- Unauthorized users do not receive a usable restore control.
- Loading, success, stale-state, missing-media, and server-failure states are accessible.
- Keyboard focus is contained and returned correctly in dialogs.
- Restore panel does not expose raw JSON or secret values.
- Snapshot rendering does not fetch history or create restore points.

### Verification

Run the focused PHP and Vue tests, the full JavaScript and PHP suites, modified-file PHP syntax checks, PHPCS, dependency audit, production build, `git diff --check`, and generated-asset manifest inspection required by `BLOCK-BUILDING-README.md`.

## Suggested Delivery Phases

### Phase 1: history storage and adapters

- Database schema/migration.
- Central history service.
- Canonical payload/hash contract.
- Page, layout, popup, form, saved-block, and template capture/restore adapters.
- Settings-group registry with secret exclusions.
- Retention, concurrency, and adapter tests.

### Phase 2: save-path integration

- Ordinary editor saves.
- Form and popup saves.
- Saved-block sync and cascades.
- Global settings saves.
- Notification bar and redirect saves.
- Import overwrite and multilingual hooks.
- Failure and rollback behavior tests.

### Phase 3: restore endpoints and editor UI

- Secure history metadata endpoint.
- Secure restore endpoint.
- Editor dock History action and panel.
- Timestamp/editor/reason/change-summary UI.
- Confirmation, stale-state, and failure handling.

### Phase 4: administrator screen and final verification

- Cross-content Quick Restore screen.
- Global Settings history section.
- Search and filters.
- Orphan cleanup and uninstall behavior.
- Full security, regression, performance, and production-build verification.

## Implementation Prompts

Use these prompts one at a time. Each assumes the previous phase passed review.

### Prompt 1: architecture audit

> Read `AGENTS.md`, `BLOCK-BUILDING-README.md`, `MULTILINGUAL-FEATURE-README.md`, and `QUICK-RESTORE-FEATURE-README.md` in full. Do not write code yet. Audit every current Flow save path, post/meta/option contract, popup/form save, saved-block sync, default-layout cascade, import overwrite, snapshot invalidation, capability check, and existing test. Produce a file-by-file implementation map, exact allowlisted payload contract for each source type/settings group, secret/privacy exclusion list, and database migration plan. Identify any unresolved security or atomicity issue before implementation.

### Prompt 2: storage and central service

> Implement only the Quick Restore database schema/migration and central history service from `QUICK-RESTORE-FEATURE-README.md`. Add canonical JSON hashing, schema versions, strict source identifiers, two-version pruning, bounded payloads, per-source concurrency protection, orphan cleanup primitives, and adapter registration. Do not integrate save paths or add UI yet. Add focused PHP tests for installation, idempotent migration, deduplication, retention, source isolation, limits, malformed data, and concurrent/stale operations.

### Prompt 3: capture and restore adapters

> Implement complete allowlisted capture/restore adapters for pages, layouts, popups, forms, saved blocks, reusable/product/shop/blog templates, notification settings, redirects, multilingual state, and each approved global settings group. Exclude secrets, entries, orders, customer data, media bytes, counters, logs, and snapshots. Revalidate all restored payloads through current sanitizers and add safe schema migrations. Do not wire automatic captures or UI yet. Add focused tests for every adapter and exclusion.

### Prompt 4: save-path integration

> Integrate Quick Restore into every approved mutation path. Sanitize the proposed state first, compare hashes, write the current restore point before mutation, abort safely if protection fails, apply the change, invalidate derived snapshots, and prune. Cover page/template saves, forms, popups, global settings, notification bars, redirects, imports that overwrite, multilingual changes, default-layout cascades, and saved-block sync with all-target preflight. Add normal, unchanged, failure, partial-failure, permissions, oversized, and cascade tests. Do not add UI yet.

### Prompt 5: restore endpoints

> Implement secure history-metadata and restore endpoints. Return only bounded summaries for listing. Restore requests must verify action nonce, broad and object-level permission, record/source/type ownership, expected current hash, payload schema, limits, and current sanitizers. Preserve the former current state, invalidate derived snapshots/caches, prevent external side effects, and retain exactly two prior versions. Add security, stale concurrency, cross-object, malformed, XSS, secret, and rollback tests.

### Prompt 6: Flow editor history UI

> Read the required guides again, then add the History action to the floating Flow editor dock and build the accessible history panel. Show current state and at most two previous versions with timestamp, editor, mutation reason, and server-generated change summary. Add restore confirmation, loading, empty, stale, missing-dependency/media, success, and failure states. Never expose raw payload JSON. Ensure snapshot mode causes no history requests or side effects. Add focused Vue tests and server-output escaping tests.

### Prompt 7: administrator Quick Restore screen

> Implement the DesignStudio Flow Quick Restore admin screen with safe search, object-type/language/editor/date filters, per-object history expansion, direct edit links, restore confirmation, and a separate administrator-only global settings section. Do not add bulk restore. Escape all output, cap queries/results, verify every action independently, and add focused PHP/UI tests.

### Prompt 8: final verification

> Audit Quick Restore against `BLOCK-BUILDING-README.md` and `QUICK-RESTORE-FEATURE-README.md`. Run focused and full JavaScript tests, PHP tests, modified-file PHP syntax checks, PHPCS, dependency audit, production build, `git diff --check`, and inspect generated manifests. Test retention, restore undo, failed writes, saved-block cascades, forms, settings, multilingual routing, secret exclusions, permissions, concurrency, and payload limits. Report exact command results and residual risks. Do not call the feature complete while a mandatory gate remains unresolved.

## Acceptance Summary

Quick Restore is ready only when every supported Flow object and global settings group automatically preserves exactly two distinct prior valid states; an authorized user can identify them by timestamp, editor, reason, and summary; restoring one safely preserves the former current state; and no secret, submission, order, customer record, media file, generated snapshot, or operational log enters history. Restore failures must leave current content unchanged, cascades must protect every affected object first, and all required security, tests, and production-build gates must pass.
