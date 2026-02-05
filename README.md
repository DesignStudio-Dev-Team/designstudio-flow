# DesignStudio Flow 🚀

> Build your WordPress Page with Artisanal Content Blocks.

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
- **Style**: Spacing, background, text + button colors
- **Products**: Source, filters, manual picks

---

## 🧩 Block Capabilities

- Inline text editing on canvas
- Media Library image replacement
- Repeater fields (testimonials, features, brands)
- CTA actions: link or modal (center or right drawer)
- Smooth slider animations and pagination

---

## 🧱 Architecture

```
designstudio-flow/
├── designstudio-flow.php
├── includes/
│   ├── class-dsf-editor.php
│   ├── class-dsf-blocks.php
│   ├── class-dsf-frontend.php
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

## 🧱 Creating a New Block (One Workflow)

Blocks render using the **same Vue component** for both editor and frontend. A lightweight HTML snapshot is generated on save for SEO, and the frontend Vue app hydrates over it.

### ✅ Steps
1. Create a new block component in `src/components/blocks/` (example: `MyBlockPreview.vue`).
1. Keep block CSS scoped in the Vue file and shared rules in `src/styles/blocks.css`.
1. Register the block in `includes/class-dsf-blocks.php` (id, name, category, settings).
1. Map the block in the editor renderer (Block Wrapper).
1. Map the block in `src/frontend/FrontendApp.vue` for frontend rendering.
1. If the block should follow Theme Settings, use theme default colors as the block defaults.
1. Run `npm run build` before previewing on the live frontend.

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
1. Update versions in `package.json` and `designstudio-flow.php`.
1. Run `npm run release` to build assets and create the zip.
1. Commit + tag:
```bash
git add .
git commit -m "Release vX.Y.Z"
git tag vX.Y.Z
git push origin main --tags
```

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
- Capability checks for all editor/admin routes
- Strict adherence to WordPress‑Extra coding rules

### 🔍 Security Checks
```bash
npm run security-check
```

This runs PHPCS with WordPress‑Extra rules plus `npm audit`.

---

## 🔁 GitHub Auto‑Updates

For private repos, add this to `wp-config.php`:

```php
define('DSF_GITHUB_TOKEN', 'ghp_your_personal_access_token_here');
```

GitHub releases are used for update delivery.

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

### v1.0.7 (Current)
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

---

## 📄 License

GPL v2 or later — https://www.gnu.org/licenses/gpl-2.0.html

---

## 🙌 Credits

Built with care by **DesignStudio Network, Inc.**
