# DesignStudio Flow 🚀

> Build your WordPress Page with Artisanal Content Blocks.

> **Building or changing a block or form?** Read [`BLOCK-BUILDING-README.md`](BLOCK-BUILDING-README.md) first. It is the mandatory human and AI/LLM workflow, including the security gates required before implementation.

---

## ✨ Overview

**DesignStudio Flow** is a lightweight page builder focused on **clarity and control**. It uses a curated block library instead of blank‑canvas editing, so pages stay consistent, responsive, and easy to manage.

### ✅ Philosophy
- Pre‑coded blocks only (no blank canvas)
- Limited but powerful customization
- Layouts stay consistent and hard to break
- Theme‑friendly header/footer rendering
- WooCommerce‑ready components

---

## 🧱 Block Library

### 🧩 Content Blocks
| Block | Description |
|---|---|
| **Hero** | Classic hero with title, subtitle, and CTA |
| **Bento Hero** | Modern bento grid hero with imagery + search |
| **Duo Hero** | Split hero layout with two panels |
| **Features Grid** | Feature cards with icons and text |
| **Text & Image** | Side‑by‑side content + image block |
| **Testimonials** | Slider with testimonial cards and dots |

### 🛒 Ecommerce Blocks
| Block | Description |
|---|---|
| **Product Grid** | Manual or category‑based products |
| **Ecommerce Showcase** | Slider with products or categories |
| **Brand Carousel** | Brand/logo grid carousel |

### 📣 Marketing Blocks
| Block | Description |
|---|---|
| **CTA Banner** | Title + subtitle + button |
| **Promo Banner** | Image with overlay text and CTA |
| **Featured Promo Banner** | Curved design promo layout |
| **Featured Product Banner** | Highlighted product hero |

---

## 🎛️ Editor & Theme Settings

### 🎨 Theme Defaults
- **Primary Color**: `#2C5F5D`
- Defaults sync across blocks unless the user overrides a block color

### 📲 Viewport Preview
- **Desktop**: 100% width
- **Tablet**: 768px
- **Mobile**: 375px

### 🧩 Customization Panels
- **Content**: Text, images, buttons, toggles
- **Style**: Spacing, background, text colors, **independent button background + button text colors**, and **eyebrow text + eyebrow line colors**
- **Products**: Source, filters, manual picks

> Every button exposes its **own** text color separate from its background, and eyebrow labels expose separate colors for the text and the accent line/dot. Per‑block colors override the theme; leaving a color blank inherits the theme default.

### 🔠 Content Sizing & Typography (Settings → Content Sizing)
Global typography defaults applied across all blocks (editor preview + published page):
- **Base font sizes** for `p`, `h1`, `h2`, `h3`, `h4`
- **Container max width** for the main page content (per‑page override still available in the Theme panel)
- Heading/body font families and a modular type scale (Settings → Typography)
- Eyebrow labels are standardized to a single consistent size across every block

---

## 🧩 Block Capabilities

- Inline text editing on canvas
- Media Library image replacement
- Repeater fields (testimonials, features, brands)
- CTA actions: link or modal (center or right drawer)
- Smooth slider animations and pagination

---

## 📧 Mail / SMTP Delivery

Routes all WordPress email (form notifications, system mail, etc.) through a chosen transport. Configure it under **DesignStudio Flow → Tools → Mail / SMTP**.

### Supported mailers
| Mailer | How it sends |
|---|---|
| **Default (PHP mail)** | Leaves `wp_mail()` untouched |
| **SendGrid** | Authenticated SMTP using a SendGrid API key |
| **Google / Gmail** | SMTP + XOAUTH2 — **one‑click connect** |
| **Microsoft / Outlook** | SMTP + XOAUTH2 — **one‑click connect** (auto‑detects Microsoft 365 vs personal Outlook) |

### Features
- **One‑click OAuth** for Gmail and Outlook — register an OAuth app once (Client ID + Secret, redirect URI shown in the UI), then connect the mailbox with a single click. Refresh tokens are stored and access tokens minted/refreshed automatically.
- **From email / name** with an optional "force from" override.
- **Send test email** with a full SMTP connection log (access tokens redacted) for fast troubleshooting.
- **Email log** — recent sends (recipient, subject, mailer, status, error) stored in a dedicated table, **auto‑pruned after 30 days** (daily cron + opportunistic cleanup). Toggleable; only metadata is stored, never the message body.
- **Admin warnings** when a mailer is selected but not fully configured, or when a connected mailbox needs re‑authorization.
- **Credentials encrypted at rest** (see Security).

---

## ⚡ Performance

Flow pages are tuned for Core Web Vitals. On Flow‑rendered pages the plugin:
- Loads the large bundle stylesheet **non‑render‑blocking** on landing pages (with inlined critical loader CSS), and loads Google Fonts asynchronously.
- **Module‑preloads** the frontend JS bundle so Vue mounts sooner, and **preconnects** to the Google Fonts origins.
- **Preloads the hero/LCP image** (`fetchpriority="high"`) and adds `loading="lazy"` / `decoding="async"` to rendered images.
- **Dequeues WordPress defaults a self‑contained Flow page doesn't need** — emoji script, the oEmbed host script, and the Gutenberg/global block stylesheets (each filterable).

---

## 🧱 Architecture

```
designstudio-flow/
├── designstudio-flow.php
├── includes/
│   ├── class-dsf-editor.php
│   ├── class-dsf-blocks.php
│   ├── class-dsf-frontend.php
│   ├── class-dsf-mail-smtp.php        # Mail / SMTP transports + OAuth + email log
│   ├── class-dsf-mail-oauth-provider.php
│   ├── class-dsf-crypto.php           # At-rest encryption for stored secrets
│   ├── class-dsf-import-export.php    # Tools page (import/export, redirects, mail)
│   ├── class-dsf-redirects.php
│   └── ...
├── templates/
│   ├── flow-page.php
│   └── flow-page-fullwidth.php
├── assets/
│   ├── css/
│   └── js/
├── src/
│   ├── components/
│   │   ├── blocks/
│   │   └── common/
│   ├── frontend/
│   └── styles/
```

---

## 🧱 Creating a New Block (Full Workflow)

Blocks render using the **same Vue component** for editor + frontend. Keep block CSS inside the Vue file so both views stay in sync.

### ✅ Steps
1. Duplicate `src/components/blocks/StarterBlockPreview.vue` and rename it (example: `MyBlockPreview.vue`).
1. Update the template + settings usage, and keep block CSS in `<style scoped>` inside the Vue file.
1. Register the block in `includes/class-dsf-blocks.php` (id, name, category, icon, settings schema + defaults).
1. Map the block in the editor renderer: `src/components/BlockWrapper.vue`.
1. Map the block in the frontend renderer: `src/frontend/FrontendApp.vue`.
1. If the block should follow Theme Settings, use theme defaults for color settings.
1. If the block needs extra data (Woo categories/products), add AJAX + frontend localize data.
1. Run tests: `npm run test:run`.
1. Build for production: `npm run build` (or `npm run release`).

> Tip: Avoid block‑specific overrides in `assets/css/frontend.css` or `assets/css/editor.css`. Keep block styles in the Vue component.

### 🚀 Starter Block Template
Use `src/components/blocks/StarterBlockPreview.vue` as a ready‑to‑clone template.

---

## 🧾 SEO Snapshot Rendering

- On save, the editor renders an **HTML snapshot** in the browser.
- Snapshot HTML is stored in `_dsf_html_snapshot`.
- PHP outputs the snapshot so crawlers see full content.
- Vue hydrates and takes over for interactions.

---

## ✅ Build & Release

### Build (required after block changes)
```bash
npm run build
```

---

## 🛠️ Local Development

When developing locally, you should run the Vite dev server **and** enable dev mode in WordPress so the editor + frontend load the hot‑reloaded assets.

### 0) Install Dependencies
```bash
npm install
composer install
```

> `npm install` is required for dev/build/test tooling. Composer is required for PHP tooling and tests. Neither is bundled in the repo.

### 1) Start Vite
```bash
npm run dev
```

### 2) Enable Dev Mode in WordPress
Add this to your `wp-config.php`:
```php
define('DSF_DEV_MODE', true);
```

> This tells Flow to load assets from `http://localhost:5173` instead of the production bundles.

### Release ZIP
```bash
npm run release
```

### Release Checklist
1. Update versions in:
   - `package.json`
   - `package-lock.json`
   - `designstudio-flow.php` plugin header
   - `designstudio-flow.php` `DSF_VERSION` constant
1. Run `npm run release` to build assets and create the zip.
1. Verify the ZIP contains a single top-level `designstudio-flow/` folder and the required runtime files:
   - `designstudio-flow.php`
   - `includes/`
   - `templates/`
   - `assets/`
1. Commit + tag:
```bash
git add .
git commit -m "Release vX.Y.Z"
git tag vX.Y.Z
git push origin main --tags
```

### Backend Update Rollout
If you want sites to update from wp-admin, do this after tagging:

1. Go to the GitHub repo Releases page.
1. Create a release for the matching tag, for example `v1.1.10`.
1. Upload the generated ZIP, for example `designstudio-flow-1.1.10.zip`.
1. Publish the release.
1. On the WordPress site, go to `Dashboard > Updates` and click `Check Again`.
1. Then update the plugin from `Plugins`.

> Important: The clean release ZIP is the file that should be attached to the GitHub Release. Do not upload a zip that contains a nested duplicate `designstudio-flow/` plugin folder.

---

## ✅ Unit Testing

We use **Vitest** for Vue components and **PHPUnit** for core PHP behavior.

### Run JavaScript Tests
```bash
npm run test:run
```

### Run PHP Tests
```bash
npm run test:php
```

### Coverage Highlights
- CTA actions + modal behavior (center + drawer)
- Theme color sync across blocks
- WYSIWYG rendering + link handling
- Repeater fields and block settings logic

---

## 🔐 Security & WordPress Standards

DesignStudio Flow is built with **WordPress security best practices** and adheres to the official **WordPress coding standards**.

### ✅ Security Posture
- Sanitization + validation of user‑provided input
- Escaping output for HTML, attributes, and URLs
- Nonce checks for privileged actions
- **Per‑object capability checks** (`edit_post` / `publish_post`), not just general role checks
- Capability checks for all editor/admin routes
- **Stored credentials encrypted at rest** (SMTP API key, OAuth client secrets, refresh/access tokens, reCAPTCHA secret) with AES‑256‑CBC keyed from the site auth salt in `wp-config.php`
- **Allowlisted shortcode rendering** on the public AJAX endpoint (no arbitrary `do_shortcode` from request input)
- **CSV export hardened** against spreadsheet formula injection
- HTML snapshots sanitized with `wp_kses` on save; uploads validated and mime‑restricted
- Strict adherence to WordPress‑Extra coding rules

### 🔍 Security Checks
```bash
npm run security-check
./vendor/bin/phpcs --standard=phpcs.xml.dist .
```

`npm run security-check` runs the JavaScript dependency audit. Run PHPCS separately to check PHP against the repository's WordPress-Extra ruleset.

---

## 🔁 GitHub Auto‑Updates

For private repos, add this to `wp-config.php`:

```php
define('DSF_GITHUB_TOKEN', 'ghp_your_personal_access_token_here');
```

GitHub releases are used for update delivery.

As of `v1.1.10`, the updater can also fall back to Git tags if a formal GitHub Release has not been published yet. Still, the recommended production workflow is:

1. Push the version commit and tag.
1. Publish a GitHub Release for that tag.
1. Attach the generated plugin ZIP to the release.

This gives WordPress the cleanest update path and ensures the backend updater can find the exact release package.

### Notes for Existing Sites
- Sites already running `v1.1.10` or newer can benefit from the improved updater behavior.
- For older installed versions, the safest first jump is still a proper GitHub Release with the ZIP attached.
- If a site does not immediately see the update, click `Check Again` on `Dashboard > Updates` or clear plugin/object cache.

### 🔐 How to Get a GitHub Token (Private Repo)
1. Go to GitHub → **Settings** → **Developer settings** → **Personal access tokens**.
1. Create a **Fine‑grained token** (recommended) or **Classic token**.
1. Minimum access needed:
   - **Repository access**: Only the repo that hosts DesignStudio Flow
   - **Permissions**: `Contents: Read` (required to read releases)
1. Copy the token and add it to `wp-config.php` as shown above.

> Tip: If you rotate the token, just update `DSF_GITHUB_TOKEN` and updates will continue working.

---

## 📝 Changelog

### v1.1.12 (Current)
- Synced plugin header `Version` and `DSF_VERSION` constant to `1.1.12` for release validation

### v1.1.10
- Fixed release packaging so the ZIP contains only one `designstudio-flow/` plugin folder
- Fixed plugin version constant mismatch that could confuse updates
- Improved GitHub updater to clear stale cache when the installed version changes
- Added fallback update detection from Git tags when a GitHub Release is missing

### v1.0.9
- Asset cache‑busting now uses filemtime so uploads always load the latest bundles
- Ecommerce Showcase preview math matches frontend (5‑across without partial cards)
- Ecommerce Showcase hover UI refined (pill “View details →” + circular nav hover)
- Admin templates cleaned for WordPress standards (escaping + strict compares)

### v1.0.8
- Responsive spacing controls added for Desktop / Tablet / Mobile
- Height slider now applies consistently in editor + frontend
- Gap control limited to Bento Hero (only block that uses it)
- Block styles consolidated into `components.css` (removed `blocks.css`)
- Bento Hero search icon cleaned to single icon in editor
- Featured Promo Banner badge aligns to true right edge

### v1.0.7
- Ecommerce Showcase now locks to exactly 5 items across on wide screens
- Slider math updated to prevent partial cards at the edge

### v1.0.6
- Bento Hero mobile layout: hero full width, boxes in 2‑column grid
- Featured Promo Banner hides image layer on mobile for cleaner layout
- Ecommerce Showcase hover arrow stays perfectly circular
- Ecommerce Showcase product cards scaled up for a 5‑across feel
- Auto‑update section expanded with GitHub token instructions

### v1.0.5
- Search icons now submit on click for Bento + Duo hero search boxes
- New frontend build output for updated search button behavior

### v1.0.4
- Ecommerce Showcase now loads WooCommerce data on the frontend
- Frontend pricing shows currency and sale styling correctly
- Ecommerce Showcase typography aligned to 24px defaults
- Search icon fallback added for Bento Hero on frontend
- Improved theme header/footer compatibility for Syndified themes
- Frontend styles now load in the correct override order

### v1.0.3
- Frontend uses the same Vue block components as the editor
- HTML snapshot rendering for SEO + fast initial paint
- CTA actions with modal support (center + right drawer)
- Theme Settings propagate primary colors across blocks
- Release tooling hardened and build output validated

### v1.0.2
- Full‑width template option for theme‑locked layouts
- Improved editor UX and Theme Settings panel
- Visual polish for block previews and button styling

### v1.0.0
- Initial block library + editor foundation
- Drag‑and‑drop layout builder
- WooCommerce product grid + category blocks

-- Near Future Features
 - Create Headers and Footers
   - in headers can create mega menus easier
 - Create Forms - simple - [ShortCode]
   - form fields 
      - single line text
      - multi-line field
      - drop down
      - checkboxes
      - radio buttons
      - hidden
      - HTML
      - multiple choice field
      - number
      - Date
      - email
      - phone
      - website
      - File Upload
      - page break (multi-step form)
   - can move the fields and have 2 column rows
   - conditional option for fields
   - allow field to be populated dynamically
   - Form settings for Confirmations
   - Form settings for Notifications
   - Email Template for Forms replies
   - Place to see Entries
   - DSF-Forms API 
      - view entries / active forms
      - send to zappier / salesforce
 - Create full landing page templates with blocks / forms pre-build
 - Import and Export pre-build templates can also be use for syndication
 


---

## 📄 License

GPL v2 or later — https://www.gnu.org/licenses/gpl-2.0.html

---

## 🙌 Credits

Built with care by **DesignStudio Network, Inc.**

---

## Next Update Improvements

### Mail / SMTP (new)
- New **Tools → Mail / SMTP** tab to route all WordPress email through a chosen transport.
- Four mailers: Default PHP mail, SendGrid (API key), Google/Gmail (XOAUTH2), Microsoft/Outlook (XOAUTH2).
- One‑click OAuth connect for Gmail and Outlook, with automatic token refresh and the exact redirect URI shown in the UI.
- Outlook host auto‑detection (Microsoft 365 vs personal Outlook).
- Send‑test‑email tool with a redacted SMTP connection log.
- Email log with 30‑day retention (dedicated table, daily prune), toggleable, metadata only.
- Site‑wide admin notices for unconfigured mailers and revoked/expired OAuth connections.

### Performance
- Bundle CSS made non‑render‑blocking on landing pages (with inlined critical loader CSS); Google Fonts loaded asynchronously.
- Module‑preload of the frontend JS bundle and preconnect to Google Fonts origins.
- Hero/LCP image preload (`fetchpriority="high"`) plus `loading="lazy"` / `decoding="async"` on rendered images.
- Dequeues unused WordPress defaults on Flow pages (emoji, oEmbed, Gutenberg/global block CSS) — each filterable.

### Typography & Content Sizing
- New **Settings → Content Sizing**: base font sizes for `p`, `h1`, `h2`, `h3`, `h4`, and a global container max width.
- Sizes flow to both the editor canvas and the published page through the shared typography token pipeline.

### Blocks & Colors
- Eyebrow labels standardized to one consistent size across every block.
- Added separate **eyebrow text color** and **eyebrow line color** pickers.
- Every button now has an **independent button text color** distinct from its background (fixed blocks where button colors were hardcoded or shared with body/panel/accent colors).

### Security Hardening
- Credentials encrypted at rest (SMTP/OAuth secrets + reCAPTCHA secret) via a shared AES‑256 helper.
- Public shortcode AJAX endpoint locked to a filterable allowlist + length cap.
- Per‑object capability checks added to page save/publish/title endpoints (IDOR fix).
- `upload_files` capability enforced on image upload; CSV exports protected from formula injection.

### Product Grid
- Added product search support that works together with enabled filters.
- Product filters now stay scoped to the selected product source, including selected categories or manually selected products.
- Filter selections can be reflected in the URL so refreshed pages keep category/filter state without forcing search terms into the URL.
- Product Grid category source now supports multiple selected categories.
- Selected categories can be reordered, matching the manual product sorting experience.
- Category-based product counts now include products from child categories.
- Category selection UI is now a searchable dropdown with selected category chips and remove controls.
- Tag filters now support choosing which product tags are available, with removable/re-addable tag chips.

### Forms
- Hidden Akismet fields are now properly tucked away.
- Required field helper text has been reduced and aligned more cleanly with multi-step form labels.
- Gravity Forms typography now better matches the site paragraph and label styling.
- Gravity Forms name, address, and checkbox layouts have been cleaned up for better alignment and readability.

### Block Library & Editor
- Added a new Heroes block group at the top of the block picker.
- Moved Hero, Bento Hero, Duo Hero, and Featured Promo Banner into the Heroes group.
- Added consistent dropdown controls across block customization panels where supported.
- Replaced the Preview header action with a Settings gear.
- Added a page settings modal for editing page title, slug, status, and parent page from inside Flow.
- Flow Pages custom post type support was removed in favor of standard WordPress pages.
- Added a Showcase Mega Header with an announcement bar, utility navigation, rich editorial mega panels, compact dropdowns, locations, call groups, and a nested mobile drawer.
- Showcase header navigation uses capped nested collections, safe URL handling, and block-specific server-side sanitization.

### Hero Block
- Added a Bottom Split hero layout with a bottom dark-to-transparent gradient.
- Added controls for gradient height, bottom spacing, text/button gap, title/subtitle gap, and text column width.
- Improved zero-gap behavior so title and subtitle spacing can fully collapse when set to `0px`.
- Split layout CTA buttons now align vertically centered with the text column.

### Featured Promo Banner
- Added toggle controls for showing or hiding the badge.
- Added toggle controls for showing or hiding the button.
- Badge and button remain enabled by default for existing layouts.

### WooCommerce
- Fixed add-to-cart AJAX handling for `?wc-ajax=add_to_cart`.
- Improved add-to-cart button behavior in product-related blocks.
