---
description: DesignStudio Flow - Plugin Development Workflow
---

# DesignStudio Flow Development Workflow

This workflow covers common development tasks for the DesignStudio Flow WordPress page builder plugin.

---

## 📁 Project Location
```
/Users/juantamayo/Sites/dsnshowcase/wp-content/plugins/designstudio-flow
```

---

## 🚀 Getting Started

### 1. Install Dependencies
// turbo
```bash
cd /Users/juantamayo/Sites/dsnshowcase/wp-content/plugins/designstudio-flow && npm install
```

### 2. Start Development Server (with Hot Module Replacement)
```bash
cd /Users/juantamayo/Sites/dsnshowcase/wp-content/plugins/designstudio-flow && npm run dev
```
> This starts Vite dev server on `http://localhost:5173`. The editor will auto-reload when you make changes to Vue components.

### 3. Enable Development Mode in WordPress
Add this to `wp-config.php` to load from Vite dev server:
```php
define('DSF_DEV_MODE', true);
```

### 4. Build for Production
// turbo
```bash
cd /Users/juantamayo/Sites/dsnshowcase/wp-content/plugins/designstudio-flow && npm run build
```
> Builds optimized assets to `assets/css/editor.css` and `assets/js/editor.js`

---

## 🧱 Adding a New Block

### Step 1: Register the Block in PHP
Edit: `includes/class-dsf-blocks.php`

Add to the `register_default_blocks()` method:
```php
$this->register_block([
    'id' => 'my-new-block',        // Unique ID (kebab-case)
    'name' => 'My New Block',       // Display name
    'category' => 'content',        // heroes | content | ecommerce | marketing
    'icon' => 'layout',             // Lucide icon name
    'description' => 'Description of what this block does',
    'preview' => DSF_PLUGIN_URL . 'assets/images/blocks/my-new-block.png',
    'settings' => [
        // Content settings
        'title' => ['type' => 'text', 'label' => 'Title', 'default' => 'Default Title'],
        'content' => ['type' => 'textarea', 'label' => 'Content', 'default' => ''],
        'image' => ['type' => 'image', 'label' => 'Image', 'default' => ''],
        
        // Style settings
        'backgroundColor' => ['type' => 'color', 'label' => 'Background Color', 'default' => '#FFFFFF'],
        'textColor' => ['type' => 'color', 'label' => 'Text Color', 'default' => '#1F2937'],
        'padding' => ['type' => 'slider', 'label' => 'Padding', 'default' => 60, 'min' => 20, 'max' => 120],
        
        // Data settings (for ecommerce blocks)
        'categoryId' => ['type' => 'category', 'label' => 'Category', 'default' => 0],
        'productIds' => ['type' => 'products', 'label' => 'Products', 'default' => []],
    ],
]);
```

### Step 2: Create Vue Preview Component
Create: `src/components/blocks/MyNewBlockPreview.vue`

```vue
<template>
  <div 
    class="dsf-block-preview dsf-my-new-block-preview"
    :style="previewStyle"
  >
    <h2>{{ settings.title || 'Default Title' }}</h2>
    <p>{{ settings.content }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  settings: Object,
  isEditor: Boolean,
})

const previewStyle = computed(() => ({
  padding: `${props.settings?.padding || 60}px 24px`,
  backgroundColor: props.settings?.backgroundColor || '#FFFFFF',
  color: props.settings?.textColor || '#1F2937',
}))
</script>

<style scoped>
.dsf-my-new-block-preview {
  text-align: center;
}
.dsf-my-new-block-preview h2 {
  font-size: 1.875rem;
  font-weight: 600;
  margin-bottom: 1rem;
}
</style>
```

### Step 3: Register Preview Component
Edit: `src/components/BlockWrapper.vue`

Add import:
```javascript
import MyNewBlockPreview from './blocks/MyNewBlockPreview.vue'
```

Add to `previewComponents` object:
```javascript
const previewComponents = {
  // ... existing components
  'my-new-block': MyNewBlockPreview,
}
```

### Step 4: Add Frontend Rendering (Optional)
Edit: `includes/class-dsf-frontend.php`

Add case to `render_block_fallback()`:
```php
case 'my-new-block':
    return $this->render_my_new_block($settings, $style);
```

Add render method:
```php
private function render_my_new_block($s, $style) {
    return sprintf(
        '<section class="dsf-my-new-block" style="%s">
            <div class="dsf-container">
                <h2>%s</h2>
                <p>%s</p>
            </div>
        </section>',
        $style,
        esc_html($s['title'] ?? ''),
        esc_html($s['content'] ?? '')
    );
}
```

### Step 5: Rebuild
// turbo
```bash
cd /Users/juantamayo/Sites/dsnshowcase/wp-content/plugins/designstudio-flow && npm run build
```

---

## 🎨 Setting Types Reference

| Type | Description | Options |
|------|-------------|---------|
| `text` | Single line text input | `label`, `default` |
| `textarea` | Multi-line text input | `label`, `default` |
| `richtext` | WYSIWYG editor | `label`, `default` |
| `number` | Number input | `label`, `default`, `min`, `max` |
| `color` | Color picker | `label`, `default` (hex) |
| `slider` | Range slider | `label`, `default`, `min`, `max` |
| `toggle` | On/off switch | `label`, `default` (boolean) |
| `select` | Dropdown select | `label`, `default`, `options` (array) |
| `image` | Image upload/URL | `label`, `default` |
| `category` | WooCommerce category picker | `label`, `default` |
| `categories` | Multi-category picker | `label`, `default` (array) |
| `products` | Product search & select | `label`, `default` (array) |
| `repeater` | Repeatable items | `label`, `default` (array of objects) |

---

## 📂 Key Files Reference

| File | Purpose |
|------|---------|
| `designstudio-flow.php` | Main plugin file, activation hooks |
| `includes/class-dsf-blocks.php` | Block registration & settings schema |
| `includes/class-dsf-frontend.php` | Frontend HTML rendering |
| `includes/class-dsf-ajax.php` | AJAX handlers for save/products/categories |
| `src/App.vue` | Main editor Vue component |
| `src/components/BlockWrapper.vue` | Block preview component mapping |
| `src/components/SidePanel.vue` | Settings panel with tabs |
| `src/components/SettingField.vue` | Dynamic form field rendering |
| `src/styles/variables.css` | CSS design tokens |
| `src/styles/components.css` | Editor UI styles |
| `src/styles/blocks.css` | Frontend block styles |

---

## 🐛 Debugging

### Check Editor Data
Open browser console in the editor and type:
```javascript
console.log(window.dsfEditorData)
```

### View Registered Blocks
```javascript
console.log(window.dsfEditorData.blocks)
```

### View WooCommerce Categories
```javascript
console.log(window.dsfEditorData.categories)
```

---

## 🔄 Common Commands

// turbo-all

### Install dependencies
```bash
cd /Users/juantamayo/Sites/dsnshowcase/wp-content/plugins/designstudio-flow && npm install
```

### Start dev server
```bash
cd /Users/juantamayo/Sites/dsnshowcase/wp-content/plugins/designstudio-flow && npm run dev
```

### Build production
```bash
cd /Users/juantamayo/Sites/dsnshowcase/wp-content/plugins/designstudio-flow && npm run build
```

### Watch for changes (alternative to dev server)
```bash
cd /Users/juantamayo/Sites/dsnshowcase/wp-content/plugins/designstudio-flow && npm run build -- --watch
```

---

## 📝 Tailwind Notes

- All Tailwind classes are prefixed with `dsf-` (e.g., `dsf-flex`, `dsf-bg-white`)
- Styles are scoped to `#dsf-editor-app` to prevent theme conflicts
- Use CSS variables from `variables.css` for consistency
- Preflight (CSS reset) is disabled to protect theme styles

---

## 🚢 Deployment Checklist

1. [ ] Remove `define('DSF_DEV_MODE', true);` from `wp-config.php`
2. [ ] Run `npm run build` to compile production assets
3. [ ] Test all blocks in the editor
4. [ ] Test frontend rendering of each block
5. [ ] Test WooCommerce integration (if applicable)
6. [ ] Test responsive preview modes (desktop/tablet/mobile)
7. [ ] Verify no CSS conflicts with theme
