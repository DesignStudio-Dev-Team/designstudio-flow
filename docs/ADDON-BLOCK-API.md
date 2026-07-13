# DesignStudio Flow — Add-on Block API

Build a companion plugin that adds your own blocks to the DesignStudio Flow
editor, without touching the Flow codebase. A block is two halves:

1. **A PHP schema** — registered with Flow so the block appears in the library and
   its settings panel renders. This is the source of truth for defaults and for
   server-side validation.
2. **A JavaScript preview component** — registered at runtime with the same Vue
   instance Flow uses, so your block renders identically in the editor canvas and
   on the published page.

They are linked by one string: the block **`id`** / **`type`**.

> Flow's preview components are compiled into its bundle, so an add-on cannot
> inject a `.vue` file into it. Instead you hand Flow a component at runtime via
> `window.dsfFlow.registerBlock()`. Until a component is registered for a type,
> the block falls back to a neutral "Block Preview" placeholder.

---

## 1. Register the block schema (PHP)

Hook `dsf_register_blocks` and call `register_block()` on the passed registry.
This action fires after Flow's own blocks are registered and before any consumer
reads the registry, so it is the right (and only) place to add blocks.

```php
add_action( 'dsf_register_blocks', function ( $blocks ) {
    $blocks->register_block( array(
        'id'          => 'acme-quote',          // required, becomes the block type
        'name'        => 'Pull Quote',
        'category'    => 'content',             // existing tab: content|marketing|ecommerce|footers
        'icon'        => 'quote',               // a lucide icon name
        'description' => 'A large pull quote with an attribution line.',
        'settings'    => array(
            'quote' => array(
                'type'    => 'wysiwyg',
                'label'   => 'Quote',
                'default' => '<p>Design is intelligence made visible.</p>',
            ),
            'author' => array(
                'type'    => 'text',
                'label'   => 'Attribution',
                'default' => 'Alina Wheeler',
            ),
            'accent' => array(
                'type'    => 'color',
                'label'   => 'Accent',
                'default' => '#2563EB',
            ),
        ),
    ) );
} );
```

**Recognised schema keys:** `id` (required), `name`, `category`, `icon`,
`description`, `settings`, `group` (target a specific library tab — see below),
`template_scope` (`page` by default; product/shop/blog blocks are context-bound),
and `preset_only` (registered for rendering but hidden from the library).

**Control `type`s** understood by the settings panel and the schema sanitizer:
`text`, `textarea`, `wysiwyg`, `color`, `url`/`link`, `image`, `number`/`slider`
(with `min`/`max`), `toggle`/`checkbox`, `select` (with `options`). Other Flow
control types exist but need matching UI; start with these.

### Adding a new library tab (optional)

Blocks with a `category` that isn't a built-in tab fall back into **Content**. To
give your blocks their own tab, add it via `dsf_block_categories` and point your
blocks at it with `category` or `group`:

```php
add_filter( 'dsf_block_categories', function ( $categories ) {
    $categories['acme'] = array( 'label' => 'Acme', 'icon' => 'sparkles', 'blocks' => array() );
    return $categories;
} );
```

---

## 2. Provide the runtime preview component (JS)

Enqueue a script (see step 3) that registers a Vue component keyed by your block
`id`. Build it with the Vue helpers Flow exposes on `window.dsfFlow.vue` so you
share Flow's single Vue runtime — **do not bundle your own Vue**.

```js
// build/blocks.js  (enqueued by your plugin)
const { h } = window.dsfFlow.vue

window.dsfFlow.registerBlock('acme-quote', {
  props: {
    settings: { type: Object, default: () => ({}) },
    isEditor: Boolean,
    blockId: String,
    previewMode: String, // 'desktop' | 'tablet' | 'mobile'
  },
  render() {
    const s = this.settings || {}
    return h('figure', { class: 'acme-quote', style: { '--accent': s.accent } }, [
      // `quote` is a wysiwyg field: it was run through wp_kses_post() on save.
      h('blockquote', { innerHTML: s.quote || '' }),
      h('figcaption', {}, s.author || ''),
    ])
  },
})
```

Your component receives the exact same props every built-in preview gets:
`settings`, `isEditor`, `blockId`, `previewMode`.

**Registration timing is handled for you.** The registry is reactive: whether
your script runs before or after Flow's app mounts, the block resolves and
re-renders. If you register as an ES module (the default in step 3) your script
runs after Flow's bundle, so `window.dsfFlow` is always defined by then.

### `window.dsfFlow` reference

| Member | Description |
| --- | --- |
| `registerBlock(type, component)` | Register/replace the preview component for a block type. Returns `true` on success. |
| `getCustomBlock(type)` | The registered component for a type, or `null`. |
| `getRegisteredBlockTypes()` | Array of registered add-on block types. |
| `vue` | Shared Vue helpers: `h`, `defineComponent`, `ref`, `reactive`, `computed`, `watch`, `onMounted`, `onUnmounted`, `inject`. |
| `version` | API version integer (currently `1`). |

---

## 3. Enqueue your script (PHP)

Return your script from `dsf_flow_block_assets`. Flow enqueues it on both the
editor and the frontend, **after** its own bundle, and as an ES module by default:

```php
add_filter( 'dsf_flow_block_assets', function ( $assets ) {
    $assets['acme-blocks'] = array(
        'src'     => plugins_url( 'build/blocks.js', __FILE__ ),
        'version' => '1.0.0',
        // 'deps'   => array(),   // extra script deps (Flow bundle is added automatically)
        // 'module' => true,      // load as type="module" (default); set false for a classic script
    );
    return $assets;
} );
```

A string value is shorthand for `array( 'src' => ... )`.

---

## 4. Sanitize your settings (PHP) — required

A block schema controls the **editor UI only**. It does **not** sanitize saved
data. Every add-on block must sanitize its own settings on save via
`dsf_sanitize_block_settings`. The simplest safe implementation delegates to the
schema-driven sanitizer, which sanitizes each value by its control `type` and
drops any key not in your schema:

```php
add_filter( 'dsf_sanitize_block_settings', function ( $settings, $type ) {
    if ( 'acme-quote' === $type ) {
        return DSF_Ajax::sanitize_block_settings_by_schema( $settings, $type );
    }
    return $settings;
}, 10, 2 );
```

If your block needs custom rules (e.g. an allowlist, a bounded repeater, an
attachment-ID lookup), sanitize the array yourself and return it. Treat every
value as untrusted. In your JS component, only use `innerHTML` for fields you
sanitized with `wp_kses_post()` on save; render everything else as text.

This filter runs for any block type Flow doesn't have a built-in sanitizer for,
so always gate on your own `$type`.

---

## 5. Export / import & backwards compatibility

Flow's export/import carries a block's `settings` automatically — as long as all
data lives inside `settings`, your block travels between sites with no extra work,
and the media URLs inside it are sideloaded on import.

- **Extra post meta.** If your block stores data in its own post meta (outside
  `settings`), include those keys via `dsf_export_meta_keys` so they export and
  import:

  ```php
  add_filter( 'dsf_export_meta_keys', function ( $keys, $post_type ) {
      if ( 'page' === $post_type ) {
          $keys[] = '_acme_block_data';
      }
      return $keys;
  }, 10, 2 );
  ```

- **Migrating older data.** If a future version of your add-on changes its saved
  shape, reconcile imported items in `dsf_import_item` (fired per item, with the
  source file `format`).

- **Missing add-on on import.** A page that uses your block imports fine even if
  your add-on isn't installed on the destination; the block shows the neutral
  placeholder until the add-on is active, then renders normally. (Importing a
  *saved block* of an unregistered type is skipped, so activate the add-on first.)

- **Format compatibility.** Export files are versioned. Flow imports its current
  format and any older one; a file from a *newer* Flow than the destination is
  refused with a clear "update the plugin" notice rather than mis-read.

---

## Hooks reference

| Hook | Type | Purpose |
| --- | --- | --- |
| `dsf_register_blocks` | action | Register block schemas. `do_action( 'dsf_register_blocks', $blocks )`. |
| `dsf_block_categories` | filter | Add/reorder library tabs. |
| `dsf_flow_block_assets` | filter | Enqueue add-on preview scripts (editor + frontend). |
| `dsf_sanitize_block_settings` | filter | Sanitize an add-on block's settings on save. `($settings, $type)`. |
| `dsf_export_meta_keys` | filter | Extra post-meta keys to export/import. `($keys, $post_type)`. |
| `dsf_import_item` | filter | Migrate/adjust one imported item. `($item, $format)`. |

## Public API

| Symbol | Where | Purpose |
| --- | --- | --- |
| `DSF_Blocks::register_block( $schema )` | PHP | Register a block (call from `dsf_register_blocks`). |
| `DSF_Ajax::sanitize_block_settings_by_schema( $settings, $type )` | PHP | Schema-driven settings sanitizer. |
| `window.dsfFlow.registerBlock( type, component )` | JS | Register a runtime preview component. |
| `window.dsfFlow.vue` | JS | Flow's shared Vue helpers. |
