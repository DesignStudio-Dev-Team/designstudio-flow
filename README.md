# DesignStudio Flow

> Build your WordPress pages with drag-and-drop pre-coded blocks.

## 🎯 Overview

**DesignStudio Flow** is a lightweight, intuitive page builder for WordPress. Unlike complex builders like Elementor or Divi, Flow focuses on simplicity — offering pre-designed, professionally crafted blocks that users can customize without overwhelming options.

### Key Philosophy

- **Pre-coded blocks only** — No building from scratch
- **Limited but powerful customization** — Content, images, colors, padding, fonts
- **Fixed structure** — Users can't break the layout
- **WooCommerce native** — Deep integration with products and categories
- **Theme-friendly** — Blocks render between your theme's header and footer

---

## ✨ Block Library

### 🧱 Content Blocks

| Block | Description |
|-------|-------------|
| **Hero** | Classic hero section with title, subtitle, and CTA button |
| **Bento Hero** | Modern bento-grid style hero with search, images, and product showcase |
| **Duo Hero** | Split-screen hero with two image containers and search bar |
| **Features Grid** | Grid of feature cards with icons, titles, and descriptions |
| **Text & Image** | Flexible content block with text on one side, image on the other |
| **Testimonials** | Slider carousel with customer testimonials, images, and pagination dots |

### 🛒 Ecommerce Blocks

| Block | Description |
|-------|-------------|
| **Product Grid** | Display products with manual or category selection |
| **Ecommerce Showcase** | Slider with products or categories, pagination, and navigation arrows |
| **Brand Carousel** | Display brand/partner logos in a responsive grid |

### 📣 Marketing Blocks

| Block | Description |
|-------|-------------|
| **CTA Banner** | Call-to-action banner with title, subtitle, and button |
| **Promo Banner** | Promotional banner with image, text overlay, and CTA |
| **Featured Promo Banner** | Curved design promo with discount badge |
| **Featured Product Banner** | Large product feature with ribbon, circle highlight, and promo code |

---

## 🎨 Visual Editor & Layout

- **Primary Color**: `#2C5F5D` (Teal)
- **Canvas Area**: Main drag-and-drop zone
- **Left Sidebar**: Collapsible Block Library with schematic previews
- **Right Sidebar**: Contextual Customizer Panel (opens on block selection)

### 📲 Viewport Modes

Toggle buttons in the header to preview different screen sizes:
- **Desktop**: 100% width
- **Tablet**: 768px fixed width
- **Mobile**: 375px fixed width

### 🛠️ Block Customization

The right sidebar customizer panel includes contextual tabs:

| Tab | Features |
|-----|----------|
| **Content** | Text editing, inline images, button links, toggles |
| **Style** | Padding (slider), background colors (picker), font colors, primary colors |
| **Products** | Source selection, category filter, manual pinning (for ecommerce blocks) |

### 🖼️ WordPress Media Library Integration

- Full integration with the native WordPress Media Library dialog
- Grid-based image picker with hover effects and selection confirmation
- Used for replacing hero backgrounds, testimonial images, product images, and more

---

## 🧩 Block Features

### Inline Text Editing

Most blocks support inline text editing directly on the canvas:
- Click on text to edit
- Changes save automatically
- Works for titles, descriptions, button text, etc.

### Repeater Fields

Several blocks use repeater fields for managing multiple items:
- **Testimonials**: Add/remove testimonials with title, quote, author, location, and image
- **Features Grid**: Add/remove feature cards
- **Brand Carousel**: Add/remove brand logos with name, URL, and image

### Color Customization

Blocks support various color settings:
- Background colors
- Title and text colors
- Button background and text colors
- Primary colors (affects quote icons, navigation arrows, active states)

### Slider Navigation

Blocks with sliders include:
- **Navigation arrows** (teal circular buttons with hover effects)
- **Pagination dots** (clickable indicators)
- **Smooth animations** on slide transitions

---

## 🎨 Design System

### Colors

| Token | Hex | Usage |
|-------|-----|-------|
| Primary | `#2C5F5D` | Buttons, navigation arrows, active states |
| Secondary | `#0F6B8C` | Quote icons, accents |
| Background | `#F3F4F6` | App background |
| Canvas | `#FFFFFF` | Canvas background |
| Borders | `#E5E7EB` | Dividers, inputs |
| Text Primary | `#1F2937` | Headings |
| Text Secondary | `#6B7280` | Descriptions |
| Success | `#10B981` | Notifications |
| Error | `#EF4444` | Destructive actions |

### Typography

| Element | Size |
|---------|------|
| Hero Title | 42px |
| Block Title | 38px |
| Subtitle/Description | 24px |
| Body Text | 24px |
| Button Text | 24px |

---

## 🧑‍💻 Technical Stack

### Tech Stack
- **Framework**: Vue.js 3 (Composition API)
- **State Management**: Pinia
- **Styling**: Scoped CSS with `dsf-` prefix
- **Icons**: Lucide Icons (Vue)
- **Drag & Drop**: `vuedraggable` / `sortablejs`
- **Notifications**: Sonner (Vue)

### Key User Flows

1. **Add Block**: Click "Add Block" → Browse categories → Drag block to canvas
2. **Customize Block**: Click block → Customizer opens → Edit content/style → Changes apply immediately
3. **Reorder Blocks**: Drag block by grip handle → Drop in new position
4. **Edit Text**: Click on text element → Edit inline → Auto-saves
5. **Add Testimonial**: Open customizer → Testimonials tab → Click "Add Testimonial"
6. **Preview**: Click Preview → Controls hide → Full preview mode
7. **Save**: Click Save Page → Toast notification confirms

---

## 🏗️ Architecture & File Structure

```
designstudio-flow/
├── designstudio-flow.php          # Main plugin file
├── README.md                      # Specification & Documentation
│
├── includes/
│   ├── class-dsf-admin.php        # Admin menu & settings
│   ├── class-dsf-editor.php       # Page builder editor
│   ├── class-dsf-blocks.php       # Block registration & rendering
│   ├── class-dsf-ajax.php         # AJAX handlers
│   └── ...
│
├── src/                           # Vue.js source files
│   ├── components/
│   │   ├── blocks/                # Block Preview Components
│   │   │   ├── HeroPreview.vue
│   │   │   ├── BentoHeroPreview.vue
│   │   │   ├── DuoHeroPreview.vue
│   │   │   ├── TestimonialsPreview.vue
│   │   │   ├── TextImagePreview.vue
│   │   │   ├── FeaturedPromoBannerPreview.vue
│   │   │   ├── FeaturedProductBannerPreview.vue
│   │   │   ├── EcommerceShowcasePreview.vue
│   │   │   ├── BrandLogosPreview.vue
│   │   │   └── ...
│   │   ├── common/                # Shared Components
│   │   │   ├── BlockSchematic.vue
│   │   │   ├── InlineText.vue
│   │   │   ├── ColorPicker.vue
│   │   │   ├── RepeaterField.vue
│   │   │   ├── TestimonialsRepeaterField.vue
│   │   │   ├── BrandRepeaterField.vue
│   │   │   └── ...
│   │   ├── selectors/             # Customizer Selectors
│   │   │   ├── ProductsSelector.vue
│   │   │   ├── CategorySelector.vue
│   │   │   └── CategoriesSelector.vue
│   │   ├── BlockLibrary.vue
│   │   ├── EditorHeader.vue
│   │   ├── SidePanel.vue
│   │   ├── SettingField.vue
│   │   └── ...
│   └── styles/
│       ├── variables.css          # Design Tokens
│       └── main.css               # Global Styles
```

---

## � Auto-Updates from GitHub

This plugin supports automatic updates directly from GitHub releases.

### Setup (Required for Private Repos)

Add the following line to your `wp-config.php`:

```php
define('DSF_GITHUB_TOKEN', 'ghp_your_personal_access_token_here');
```

### Creating a GitHub Personal Access Token

1. Go to **GitHub** → **Settings** → **Developer settings** → **Personal access tokens** → **Fine-grained tokens**
2. Click **Generate new token**
3. Configure:
   - **Token name**: `DesignStudio Flow Updates`
   - **Repository access**: Select `designstudio-flow` repository
   - **Permissions**: `Contents: Read-only`
4. Copy the token and add it to `wp-config.php`

### How Updates Work

1. WordPress checks for updates periodically
2. If a new version is available on GitHub Releases, you'll see an update notification
3. Click **Update Now** — the plugin updates without deactivating
4. Your settings and page layouts are preserved

### Deploying a New Version

```bash
# Build Vue assets and create production ZIP
npm run release
```

This creates `designstudio-flow-x.x.x.zip` ready for distribution.

To publish on GitHub:

```bash
git add .
git commit -m "Release v1.0.0"
git tag v1.0.0
git push origin main --tags
```

The GitHub Action will automatically create a Release with the ZIP attached.

---

## �📝 Changelog

### v1.1.0 (Current)
- Added **Testimonials** block with slider, images, and inline editing
- Added **Bento Hero** and **Duo Hero** blocks
- Added **Featured Promo Banner** and **Featured Product Banner** blocks
- Added **Text & Image** block with color customization
- Added **Brand Carousel** block
- Added **CTA Banner** block with full color customization
- Added **Ecommerce Showcase** with slider navigation
- Implemented inline text editing across all blocks
- Added specialized repeater fields for testimonials and brands
- Updated block schematics for accurate previews
- Added primary color settings for navigation elements

### v1.0.0
- Implemented core Drag & Drop builder
- Added `Product Grid` with Manual/Category modes
- Added `Category Grid` with "Shop All" and drag-to-reorder categories
- Integrated WordPress Media Library
- Responsive Preview Modes

---

## 📄 License

GPL v2 or later — [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

---

## 🙏 Credits

Built with ❤️ by [DesignStudio Network, Inc.](https://designstudio.com)
