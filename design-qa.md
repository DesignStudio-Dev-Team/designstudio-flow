# Design QA — Landing Showcase Hero

final result: passed

## Scope

- Page: `http://dsnshowcase.test/landing-page/`
- Reference: generated modern starting-hub concept
- Implementation: `LandingShowcaseHeroPreview.vue`
- Viewports reviewed: 1440 × 1024, 430 × 932, and 375 × 812

## Visual comparison

- Preserves the established hero structure: message and actions on the left, six editor-inspired destination tiles on the right, and the floating navigation dock at the bottom.
- Removes the need for a conventional site header and keeps the first viewport focused on what DSFlow does.
- Uses six distinct mini-scenes based on the real editor's global styles, block selection, responsive controls, WooCommerce, forms, security, and reusable page structure.
- The production result intentionally keeps the live page's warmer grid background and brand typography instead of copying the reference literally.

Comparison artifact: `/Users/juantamayo/.codex/visualizations/2026/07/13/019f5a68-470c-7672-b059-2192295fd6c6/landing-hero-reference-comparison.png`

Final captures:

- Desktop: `/Users/juantamayo/.codex/visualizations/2026/07/13/019f5a68-470c-7672-b059-2192295fd6c6/landing-hero-two-word-final.png`
- Mobile: `/Users/juantamayo/.codex/visualizations/2026/07/13/019f5a68-470c-7672-b059-2192295fd6c6/landing-hero-two-word-mobile.png`

## Behavior and accessibility

- All six phrases are unique, exactly two words, and use the same active index as their matching tile.
- Rotating text leaves enough line box and bottom padding for descenders; the `g` and `y` in “page visually” were geometry-checked inside their visible bounds.
- The visible pause control has been removed; reduced-motion mode still removes automatic movement.
- The stable screen-reader headline contains the complete message without an interrupting live region.
- Editor and snapshot modes do not start animation timers.

## Responsive and runtime checks

- No horizontal document overflow at 430 px or 375 px.
- The destination hub remains a two-column grid at common mobile widths and collapses only on very narrow screens.
- The bottom dock remains fixed and usable without reintroducing a top header.
- Tile navigation was verified against the `#editor` destination.
- Frontend console review found no errors or warnings.

## Verification

- Vitest: 60 files, 430 tests passed.
- PHPUnit: 230 tests, 1,418 assertions passed.
- Security audit: 0 vulnerabilities.
- Production build completed successfully.
- PHP syntax checks and focused PHPCS checks passed.
- `git diff --check` passed.
