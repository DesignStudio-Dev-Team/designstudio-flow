<template>
  <div class="dsf-popup-settings-fields">
    <div v-if="showEnable" class="dsf-popup-settings-fields__enable">
      <div>
        <strong>Enable page popup</strong>
        <p>Show this popup only on this DS Flow page.</p>
      </div>
      <button
        type="button"
        class="dsf-toggle"
        :class="{ 'dsf-toggle--active': popup.enabled }"
        :aria-pressed="popup.enabled"
        @click="update('enabled', !popup.enabled)"
      >
        <span class="dsf-toggle__thumb"></span>
      </button>
    </div>

    <template v-if="!showEnable || popup.enabled">
      <section class="dsf-popup-settings-fields__section">
        <div class="dsf-popup-settings-fields__section-heading">
          <h3>Content</h3>
          <p>Build a message with text and a CTA, or use one full-size graphic.</p>
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label" for="dsf-popup-type">Popup Type</label>
          <select id="dsf-popup-type" class="dsf-input" :value="popup.type" @change="update('type', $event.target.value)">
            <option value="content">Content + optional image</option>
            <option value="image">Full image</option>
          </select>
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label">Popup Image</label>
          <MediaPicker :model-value="popup.image" @update:model-value="update('image', $event)" />
        </div>

        <div v-if="popup.image" class="dsf-form-group">
          <label class="dsf-label" for="dsf-popup-image-alt">Image Alt Text</label>
          <input id="dsf-popup-image-alt" class="dsf-input" type="text" :value="popup.imageAlt" @input="update('imageAlt', $event.target.value)" />
        </div>

        <template v-if="popup.type === 'content'">
          <div v-if="popup.image" class="dsf-form-group">
            <label class="dsf-label" for="dsf-popup-image-position">Image Placement</label>
            <select id="dsf-popup-image-position" class="dsf-input" :value="popup.imagePosition" @change="update('imagePosition', $event.target.value)">
              <option value="top">Above content</option>
              <option value="left">Left of content</option>
              <option value="right">Right of content</option>
            </select>
          </div>

          <div class="dsf-form-group">
            <label class="dsf-label" for="dsf-popup-headline">Headline</label>
            <input id="dsf-popup-headline" class="dsf-input" type="text" :value="popup.headline" @input="update('headline', $event.target.value)" />
          </div>

          <div class="dsf-form-group">
            <label class="dsf-label">Body Content</label>
            <WysiwygField :model-value="popup.body" @update:model-value="update('body', $event)" />
          </div>
        </template>

        <div class="dsf-popup-settings-fields__grid">
          <div class="dsf-form-group">
            <label class="dsf-label" for="dsf-popup-button-text">{{ popup.type === 'image' ? 'Image Link Label' : 'Button Text' }}</label>
            <input id="dsf-popup-button-text" class="dsf-input" type="text" :value="popup.buttonText" @input="update('buttonText', $event.target.value)" />
          </div>
          <div class="dsf-form-group">
            <label class="dsf-label" for="dsf-popup-button-url">{{ popup.type === 'image' ? 'Image Link URL' : 'Button URL' }}</label>
            <input id="dsf-popup-button-url" class="dsf-input" type="url" :value="popup.buttonUrl" placeholder="https://" @input="update('buttonUrl', $event.target.value)" />
          </div>
        </div>

        <ToggleRow label="Open link in a new tab" :value="popup.openNewTab" @update="update('openNewTab', $event)" />
      </section>

      <section class="dsf-popup-settings-fields__section">
        <div class="dsf-popup-settings-fields__section-heading">
          <h3>Layout & Style</h3>
          <p>Use the page theme fonts while controlling popup size and colors.</p>
        </div>

        <div class="dsf-popup-settings-fields__grid">
          <div class="dsf-form-group">
            <label class="dsf-label" for="dsf-popup-width">Width</label>
            <select id="dsf-popup-width" class="dsf-input" :value="popup.width" @change="update('width', $event.target.value)">
              <option value="small">Small (420px)</option>
              <option value="medium">Medium (620px)</option>
              <option value="large">Large (860px)</option>
              <option value="wide">Wide (1100px)</option>
            </select>
          </div>
          <div class="dsf-form-group">
            <label class="dsf-label" for="dsf-popup-position">Position</label>
            <select id="dsf-popup-position" class="dsf-input" :value="popup.position" @change="update('position', $event.target.value)">
              <option value="center">Center</option>
              <option value="bottom-right">Bottom right</option>
              <option value="bottom-left">Bottom left</option>
            </select>
          </div>
        </div>

        <div class="dsf-popup-settings-fields__colors">
          <div class="dsf-form-group"><label class="dsf-label">Background</label><ColorPicker :model-value="popup.backgroundColor" @update:model-value="update('backgroundColor', $event)" /></div>
          <div class="dsf-form-group"><label class="dsf-label">Text</label><ColorPicker :model-value="popup.textColor" @update:model-value="update('textColor', $event)" /></div>
          <div class="dsf-form-group"><label class="dsf-label">Button / Accent</label><ColorPicker :model-value="popup.accentColor" @update:model-value="update('accentColor', $event)" /></div>
        </div>

        <ToggleRow label="Show dark page overlay" :value="popup.showOverlay" @update="update('showOverlay', $event)" />
        <ToggleRow v-if="popup.showOverlay" label="Close when overlay is clicked" :value="popup.closeOnOverlay" @update="update('closeOnOverlay', $event)" />
        <ToggleRow label="Show close button" :value="popup.showClose" @update="update('showClose', $event)" />
      </section>

      <section class="dsf-popup-settings-fields__section">
        <div class="dsf-popup-settings-fields__section-heading">
          <h3>Display Rules</h3>
          <p>Schedule the campaign and control when visitors see it again.</p>
        </div>

        <div class="dsf-popup-settings-fields__grid dsf-popup-settings-fields__grid--three">
          <div class="dsf-form-group">
            <label class="dsf-label" for="dsf-popup-delay">Entrance Delay</label>
            <div class="dsf-popup-settings-fields__input-suffix"><input id="dsf-popup-delay" class="dsf-input" type="number" min="0" max="3600" :value="popup.delaySeconds" @input="updateNumber('delaySeconds', $event.target.value, 0, 3600)" /><span>seconds</span></div>
          </div>
          <div class="dsf-form-group">
            <label class="dsf-label" for="dsf-popup-cookie-duration">Hide After Closing</label>
            <input id="dsf-popup-cookie-duration" class="dsf-input" type="number" min="0" max="365" :value="popup.cookieDuration" @input="updateNumber('cookieDuration', $event.target.value, 0, 365)" />
          </div>
          <div class="dsf-form-group">
            <label class="dsf-label" for="dsf-popup-cookie-unit">Duration Unit</label>
            <select id="dsf-popup-cookie-unit" class="dsf-input" :value="popup.cookieUnit" @change="update('cookieUnit', $event.target.value)">
              <option value="hours">Hours</option>
              <option value="days">Days</option>
            </select>
          </div>
        </div>
        <p class="dsf-helper-text">Use 0 to hide only for the current browser session.</p>

        <div class="dsf-popup-settings-fields__grid">
          <div class="dsf-form-group">
            <label class="dsf-label" for="dsf-popup-start">Start Date (optional)</label>
            <input id="dsf-popup-start" class="dsf-input" type="datetime-local" :value="popup.startDate" @input="update('startDate', $event.target.value)" />
          </div>
          <div class="dsf-form-group">
            <label class="dsf-label" for="dsf-popup-end">End Date (optional)</label>
            <input id="dsf-popup-end" class="dsf-input" type="datetime-local" :value="popup.endDate" @input="update('endDate', $event.target.value)" />
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { computed, h } from 'vue'
import ColorPicker from './ColorPicker.vue'
import MediaPicker from './MediaPicker.vue'
import WysiwygField from './WysiwygField.vue'

const DEFAULT_POPUP = {
  enabled: false,
  type: 'content',
  headline: 'Limited time offer',
  body: '<p>Add your popup message here.</p>',
  image: '',
  imageAlt: '',
  imagePosition: 'top',
  buttonText: 'Learn more',
  buttonUrl: '#',
  openNewTab: false,
  width: 'medium',
  position: 'center',
  delaySeconds: 3,
  startDate: '',
  endDate: '',
  cookieDuration: 24,
  cookieUnit: 'hours',
  showOverlay: true,
  closeOnOverlay: true,
  showClose: true,
  backgroundColor: '#FFFFFF',
  textColor: '#1F2937',
  accentColor: '#2C5F5D',
}

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  showEnable: { type: Boolean, default: true },
})
const emit = defineEmits(['update:modelValue'])
const popup = computed(() => ({ ...DEFAULT_POPUP, ...(props.modelValue || {}) }))

function update(key, value) {
  emit('update:modelValue', { ...popup.value, [key]: value })
}

function updateNumber(key, value, min, max) {
  const parsed = Number.parseInt(value, 10)
  update(key, Math.min(max, Math.max(min, Number.isFinite(parsed) ? parsed : min)))
}

const ToggleRow = {
  props: ['label', 'value'],
  emits: ['update'],
  setup(toggleProps, { emit: emitToggle }) {
    return () => h('div', { class: 'dsf-popup-settings-fields__toggle-row' }, [
      h('span', toggleProps.label),
      h('button', {
        type: 'button',
        class: ['dsf-toggle', { 'dsf-toggle--active': toggleProps.value }],
        'aria-pressed': Boolean(toggleProps.value),
        onClick: () => emitToggle('update', !toggleProps.value),
      }, [h('span', { class: 'dsf-toggle__thumb' })]),
    ])
  },
}
</script>

<style scoped>
.dsf-popup-settings-fields { display: grid; gap: 18px; }
.dsf-popup-settings-fields__enable,
.dsf-popup-settings-fields__toggle-row { display: flex; align-items: center; justify-content: space-between; gap: 20px; }
.dsf-popup-settings-fields__enable { padding: 18px; border: 1px solid var(--dsf-gray-200); border-radius: 12px; background: var(--dsf-gray-50); }
.dsf-popup-settings-fields__enable strong { color: var(--dsf-gray-900); font-size: 0.9rem; }
.dsf-popup-settings-fields__enable p { margin: 3px 0 0; color: var(--dsf-gray-500); font-size: 0.78rem; }
.dsf-popup-settings-fields__section { display: grid; gap: 14px; padding: 18px; border: 1px solid var(--dsf-gray-200); border-radius: 12px; }
.dsf-popup-settings-fields__section-heading h3 { margin: 0; color: var(--dsf-gray-900); font-size: 0.9rem; }
.dsf-popup-settings-fields__section-heading p { margin: 3px 0 0; color: var(--dsf-gray-500); font-size: 0.78rem; line-height: 1.45; }
.dsf-popup-settings-fields__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.dsf-popup-settings-fields__grid--three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.dsf-popup-settings-fields__colors { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.dsf-popup-settings-fields__toggle-row { min-height: 34px; color: var(--dsf-gray-700); font-size: 0.82rem; font-weight: 600; }
.dsf-popup-settings-fields__input-suffix { display: flex; align-items: center; gap: 8px; }
.dsf-popup-settings-fields__input-suffix span { color: var(--dsf-gray-500); font-size: 0.75rem; }
@media (max-width: 700px) {
  .dsf-popup-settings-fields__grid,
  .dsf-popup-settings-fields__grid--three,
  .dsf-popup-settings-fields__colors { grid-template-columns: 1fr; }
}
</style>
