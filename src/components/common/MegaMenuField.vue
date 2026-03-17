<template>
  <div class="dsf-mega-menu-field">
    <div
      v-for="(item, itemIndex) in localItems"
      :key="item.__id"
      class="dsf-mega-menu-field__item"
    >
      <div class="dsf-mega-menu-field__item-head">
        <strong>Menu Item {{ itemIndex + 1 }}</strong>
        <button class="dsf-mega-menu-field__danger" @click="removeItem(itemIndex)">Remove</button>
      </div>

      <div class="dsf-mega-menu-field__row">
        <div class="dsf-form-group">
          <label class="dsf-label">Label</label>
          <input
            type="text"
            class="dsf-input"
            :value="item.label"
            @input="updateItem(itemIndex, 'label', $event.target.value)"
          />
        </div>
        <div class="dsf-form-group">
          <label class="dsf-label">URL</label>
          <input
            type="text"
            class="dsf-input"
            :value="item.url"
            @input="updateItem(itemIndex, 'url', $event.target.value)"
          />
        </div>
        <div class="dsf-form-group">
          <label class="dsf-label">Enable Mega Menu</label>
          <button
            class="dsf-toggle"
            :class="{ 'dsf-toggle--active': !!item.hasMega }"
            @click="toggleMega(itemIndex)"
          >
            <span class="dsf-toggle__thumb"></span>
          </button>
        </div>
      </div>

      <div v-if="item.hasMega" class="dsf-mega-menu-field__mega">
        <div class="dsf-mega-menu-field__columns-head">
          <strong>Columns</strong>
          <button class="dsf-mega-menu-field__small" @click="addColumn(itemIndex)">Add Column</button>
        </div>

        <div
          v-for="(column, columnIndex) in item.columns"
          :key="column.__id"
          class="dsf-mega-menu-field__column"
        >
          <div class="dsf-mega-menu-field__column-head">
            <input
              type="text"
              class="dsf-input"
              :value="column.heading"
              @input="updateColumn(itemIndex, columnIndex, 'heading', $event.target.value)"
              placeholder="Column heading"
            />
            <button class="dsf-mega-menu-field__danger" @click="removeColumn(itemIndex, columnIndex)">Remove</button>
          </div>

          <div class="dsf-mega-menu-field__column-mode">
            <span class="dsf-mega-menu-field__column-mode-label">Image Links</span>
            <button
              class="dsf-toggle"
              :class="{ 'dsf-toggle--active': !!column.imageLinks }"
              @click="toggleColumnImageLinks(itemIndex, columnIndex)"
            >
              <span class="dsf-toggle__thumb"></span>
            </button>
          </div>

          <div v-if="column.imageLinks" class="dsf-mega-menu-field__column-grid">
            <span class="dsf-mega-menu-field__column-grid-label">Image Columns</span>
            <select
              class="dsf-input"
              :value="resolveImageColumns(column.imageColumns)"
              @change="updateColumn(itemIndex, columnIndex, 'imageColumns', parseImageColumns($event.target.value))"
            >
              <option v-for="count in [1, 2, 3, 4]" :key="`image-columns-${count}`" :value="count">
                {{ count }}
              </option>
            </select>
          </div>

          <div class="dsf-mega-menu-field__links-head">
            <span>Links</span>
            <button class="dsf-mega-menu-field__small" @click="addLink(itemIndex, columnIndex)">Add Link</button>
          </div>

          <div
            v-for="(link, linkIndex) in column.links"
            :key="link.__id"
            class="dsf-mega-menu-field__link-row"
            :class="{ 'dsf-mega-menu-field__link-row--with-images': !!column.imageLinks }"
          >
            <div class="dsf-mega-menu-field__link-main">
              <input
                type="text"
                class="dsf-input"
                :value="link.label"
                @input="updateLink(itemIndex, columnIndex, linkIndex, 'label', $event.target.value)"
                placeholder="Link label"
              />
              <input
                type="text"
                class="dsf-input"
                :value="link.url"
                @input="updateLink(itemIndex, columnIndex, linkIndex, 'url', $event.target.value)"
                placeholder="Link URL"
              />
              <button
                class="dsf-mega-menu-field__icon-btn"
                title="Remove link"
                @click="removeLink(itemIndex, columnIndex, linkIndex)"
              >
                x
              </button>
            </div>
            <div v-if="column.imageLinks" class="dsf-mega-menu-field__image-controls">
              <div v-if="link.image" class="dsf-mega-menu-field__image-preview">
                <img :src="link.image" alt="" />
                <button
                  class="dsf-mega-menu-field__image-remove"
                  title="Remove image"
                  @click="updateLink(itemIndex, columnIndex, linkIndex, 'image', '')"
                >
                  <X :size="12" />
                </button>
              </div>
              <button
                class="dsf-mega-menu-field__small dsf-mega-menu-field__image-btn"
                @click="openLinkImageLibrary(itemIndex, columnIndex, linkIndex)"
              >
                <ImagePlus :size="14" />
                {{ link.image ? 'Change' : 'Select Image' }}
              </button>
            </div>
          </div>
        </div>

        <div class="dsf-mega-menu-field__banner">
          <strong>Mega Menu Banner</strong>
          <div class="dsf-mega-menu-field__row">
            <div class="dsf-form-group">
              <label class="dsf-label">Banner Title</label>
              <input
                type="text"
                class="dsf-input"
                :value="item.banner?.title || ''"
                @input="updateBanner(itemIndex, 'title', $event.target.value)"
              />
            </div>
            <div class="dsf-form-group">
              <label class="dsf-label">Banner Image</label>
              <div class="dsf-mega-menu-field__image-controls">
                <div v-if="item.banner?.image" class="dsf-mega-menu-field__image-preview">
                  <img :src="item.banner.image" alt="" />
                  <button
                    class="dsf-mega-menu-field__image-remove"
                    title="Remove image"
                    @click="updateBanner(itemIndex, 'image', '')"
                  >
                    <X :size="12" />
                  </button>
                </div>
                <button
                  class="dsf-mega-menu-field__small"
                  @click="openBannerImageLibrary(itemIndex)"
                >
                  <ImagePlus :size="14" />
                  {{ item.banner?.image ? 'Change Image' : 'Select Image' }}
                </button>
              </div>
            </div>
            <div class="dsf-form-group">
              <label class="dsf-label">Banner URL</label>
              <input
                type="text"
                class="dsf-input"
                :value="item.banner?.url || '#'"
                @input="updateBanner(itemIndex, 'url', $event.target.value)"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <button class="dsf-mega-menu-field__add" @click="addItem">Add Menu Item</button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { X, ImagePlus } from 'lucide-vue-next'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue'])

const localItems = ref([])

watch(
  () => props.modelValue,
  (value) => {
    localItems.value = normalizeItems(Array.isArray(value) ? value : [])
  },
  { immediate: true, deep: true }
)

function id(prefix) {
  return `${prefix}-${Math.random().toString(36).slice(2, 10)}`
}

function createLink() {
  return { __id: id('link'), label: 'Link', url: '#', image: '' }
}

function createColumn(imageLinks = false, imageColumns = 2) {
  return {
    __id: id('column'),
    heading: 'Sub Heading',
    imageLinks,
    imageColumns: resolveImageColumns(imageColumns),
    links: [createLink(), createLink()],
  }
}

function createItem() {
  return {
    __id: id('item'),
    label: 'PRODUCT LINE',
    url: '#',
    hasMega: false,
    columns: [createColumn(true, 2), createColumn(false, 2)],
    banner: {
      title: '',
      image: '',
      url: '#',
    },
  }
}

function normalizeItems(items) {
  return items.map((item) => ({
    __id: item.__id || id('item'),
    label: item?.label || 'Menu Item',
    url: item?.url || '#',
    hasMega: !!item?.hasMega,
    columns: (Array.isArray(item?.columns) && item.columns.length ? item.columns : [createColumn()]).map((column, columnIndex) => ({
      __id: column.__id || id('column'),
      heading: column?.heading || 'Sub Heading',
      imageLinks: typeof column?.imageLinks === 'boolean' ? column.imageLinks : columnIndex === 0,
      imageColumns: resolveImageColumns(column?.imageColumns),
      links: (Array.isArray(column?.links) && column.links.length ? column.links : [createLink()]).map((link) => ({
        __id: link.__id || id('link'),
        label: link?.label || 'Link',
        url: link?.url || '#',
        image: link?.image || '',
      })),
    })),
    banner: {
      title: item?.banner?.title || '',
      image: item?.banner?.image || '',
      url: item?.banner?.url || '#',
    },
  }))
}

function toCleanData() {
  return localItems.value.map((item) => ({
    label: item.label,
    url: item.url,
    hasMega: !!item.hasMega,
    columns: item.columns.map((column) => ({
      heading: column.heading,
      imageLinks: !!column.imageLinks,
      imageColumns: resolveImageColumns(column.imageColumns),
      links: column.links.map((link) => ({
        label: link.label,
        url: link.url,
        image: link.image || '',
      })),
    })),
    banner: {
      title: item.banner?.title || '',
      image: item.banner?.image || '',
      url: item.banner?.url || '#',
    },
  }))
}

function emitUpdate() {
  emit('update:modelValue', toCleanData())
}

function addItem() {
  localItems.value.push(createItem())
  emitUpdate()
}

function removeItem(itemIndex) {
  localItems.value.splice(itemIndex, 1)
  emitUpdate()
}

function updateItem(itemIndex, key, value) {
  localItems.value[itemIndex][key] = value
  emitUpdate()
}

function toggleMega(itemIndex) {
  localItems.value[itemIndex].hasMega = !localItems.value[itemIndex].hasMega
  emitUpdate()
}

function addColumn(itemIndex) {
  localItems.value[itemIndex].columns.push(createColumn())
  emitUpdate()
}

function removeColumn(itemIndex, columnIndex) {
  localItems.value[itemIndex].columns.splice(columnIndex, 1)
  if (!localItems.value[itemIndex].columns.length) {
    localItems.value[itemIndex].columns.push(createColumn())
  }
  emitUpdate()
}

function updateColumn(itemIndex, columnIndex, key, value) {
  localItems.value[itemIndex].columns[columnIndex][key] = key === 'imageColumns' ? resolveImageColumns(value) : value
  emitUpdate()
}

function toggleColumnImageLinks(itemIndex, columnIndex) {
  const column = localItems.value[itemIndex].columns[columnIndex]
  column.imageLinks = !column.imageLinks
  if (column.imageLinks) {
    column.imageColumns = resolveImageColumns(column.imageColumns)
  }
  emitUpdate()
}

function addLink(itemIndex, columnIndex) {
  localItems.value[itemIndex].columns[columnIndex].links.push(createLink())
  emitUpdate()
}

function removeLink(itemIndex, columnIndex, linkIndex) {
  localItems.value[itemIndex].columns[columnIndex].links.splice(linkIndex, 1)
  if (!localItems.value[itemIndex].columns[columnIndex].links.length) {
    localItems.value[itemIndex].columns[columnIndex].links.push(createLink())
  }
  emitUpdate()
}

function updateLink(itemIndex, columnIndex, linkIndex, key, value) {
  localItems.value[itemIndex].columns[columnIndex].links[linkIndex][key] = value
  emitUpdate()
}

function updateBanner(itemIndex, key, value) {
  if (!localItems.value[itemIndex].banner) {
    localItems.value[itemIndex].banner = { title: '', image: '', url: '#' }
  }
  localItems.value[itemIndex].banner[key] = value
  emitUpdate()
}

function openMediaLibrary(onSelect) {
  if (typeof window.wp !== 'undefined' && window.wp.media) {
    const frame = window.wp.media({
      title: 'Select Image',
      button: { text: 'Use this image' },
      multiple: false,
      library: { type: 'image' },
    })

    frame.on('select', () => {
      try {
        const selection = frame.state().get('selection').first().toJSON()
        onSelect(selection.url || '')
      } catch (error) {
        console.error('Error selecting image:', error)
      }
    })

    frame.open()
    return
  }

  alert('Media Library is not available. Please ensure you are logged into WordPress.')
}

function openLinkImageLibrary(itemIndex, columnIndex, linkIndex) {
  openMediaLibrary((url) => {
    updateLink(itemIndex, columnIndex, linkIndex, 'image', url)
  })
}

function openBannerImageLibrary(itemIndex) {
  openMediaLibrary((url) => {
    updateBanner(itemIndex, 'image', url)
  })
}

function resolveImageColumns(rawValue) {
  const parsed = parseInt(rawValue ?? 2, 10)
  if (Number.isNaN(parsed)) return 2
  return Math.min(4, Math.max(1, parsed))
}

function parseImageColumns(value) {
  return resolveImageColumns(value)
}
</script>

<style scoped>
.dsf-mega-menu-field {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.dsf-mega-menu-field__item {
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
  background: #fff;
  padding: 0.75rem;
}

.dsf-mega-menu-field__item-head,
.dsf-mega-menu-field__columns-head,
.dsf-mega-menu-field__links-head,
.dsf-mega-menu-field__column-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.dsf-mega-menu-field__row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem;
}

.dsf-mega-menu-field__mega {
  border-top: 1px solid var(--dsf-gray-200);
  margin-top: 0.5rem;
  padding-top: 0.75rem;
}

.dsf-mega-menu-field__column {
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
  padding: 0.5rem;
  margin-bottom: 0.5rem;
  background: var(--dsf-gray-50);
}

.dsf-mega-menu-field__link-row {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.375rem;
  margin-bottom: 0.375rem;
  padding: 0.3rem;
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
  background: #fff;
}

.dsf-mega-menu-field__link-row--with-images {
  padding: 0.375rem;
}

.dsf-mega-menu-field__column-mode {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin: 0 0 0.55rem;
}

.dsf-mega-menu-field__column-mode-label {
  color: var(--dsf-gray-600);
  font-size: 0.75rem;
  font-weight: 600;
}

.dsf-mega-menu-field__column-grid {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: center;
  gap: 0.5rem;
  margin: 0 0 0.55rem;
}

.dsf-mega-menu-field__column-grid-label {
  color: var(--dsf-gray-600);
  font-size: 0.75rem;
  font-weight: 600;
}

.dsf-mega-menu-field__column-grid .dsf-input {
  width: 84px;
}

.dsf-mega-menu-field__link-main {
  display: grid;
  grid-template-columns: 1fr 1fr auto;
  gap: 0.375rem;
}

.dsf-mega-menu-field__image-controls {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  min-height: 32px;
}

.dsf-mega-menu-field__image-preview {
  width: 44px;
  height: 32px;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid var(--dsf-gray-200);
  position: relative;
  background: #fff;
  flex-shrink: 0;
}

.dsf-mega-menu-field__image-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.dsf-mega-menu-field__image-remove {
  position: absolute;
  top: 1px;
  right: 1px;
  width: 14px;
  height: 14px;
  border: none;
  border-radius: 4px;
  background: rgba(255, 255, 255, 0.95);
  color: #b42318;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
}

.dsf-mega-menu-field__image-btn {
  min-width: 112px;
}

.dsf-mega-menu-field__banner {
  margin-top: 0.75rem;
}

.dsf-mega-menu-field__small,
.dsf-mega-menu-field__danger,
.dsf-mega-menu-field__add,
.dsf-mega-menu-field__icon-btn {
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
  background: #fff;
  color: var(--dsf-gray-700);
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.375rem 0.625rem;
  cursor: pointer;
}

.dsf-mega-menu-field__small:hover,
.dsf-mega-menu-field__add:hover,
.dsf-mega-menu-field__icon-btn:hover {
  background: var(--dsf-gray-100);
}

.dsf-mega-menu-field__danger {
  color: #b42318;
  border-color: #fecdca;
  background: #fff5f3;
}

.dsf-mega-menu-field__danger:hover {
  background: #ffe4df;
}

@media (max-width: 900px) {
  .dsf-mega-menu-field__row {
    grid-template-columns: 1fr;
  }

  .dsf-mega-menu-field__link-main {
    grid-template-columns: 1fr;
  }
}
</style>
