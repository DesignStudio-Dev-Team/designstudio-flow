<template>
  <div 
    class="dsf-duo-hero"
    :style="{
      ...wrapperStyle,
      padding: (settings.padding || 40) + 'px 0',
      minHeight: (settings.height || 500) + 'px',
    }"
  >
    <div class="dsf-duo-hero__container" :style="{ gap: (settings.gap || 20) + 'px' }">
      <!-- Left Panel -->

      <div 
        class="dsf-duo-hero__panel"
        :style="{
          flex: `0 0 calc(${splitRatio}% - ${(settings.gap || 20) / 2}px)`
        }"
      >
        <img 
          v-if="settings.leftImage" 
          :src="settings.leftImage" 
          alt="Left Hero"
          class="dsf-duo-hero__panel-img"
        />
        <div v-else class="dsf-duo-hero__panel-placeholder"></div>

        <div class="dsf-duo-hero__overlay"></div>
        <div class="dsf-duo-hero__content" :style="{ color: settings.leftTextColor || '#FFFFFF' }">
          <InlineText 
            tagName="h2" 
            class="dsf-duo-hero__title"
            v-model="settings.leftTitle"
            :is-editor="isEditor"
            placeholder="Enter Title"
          />
          
          <div class="dsf-duo-hero__action">
            <template v-if="settings.leftType === 'search'">
              <div class="dsf-duo-hero__search">
                <input 
                  type="text" 
                  :placeholder="settings.leftSearchPlaceholder"
                  class="dsf-duo-hero__search-input"
                  v-model="leftSearchQuery"
                  @keydown.enter="handleSearch(leftSearchQuery)"
                >
                <button 
                  class="dsf-duo-hero__search-btn"
                  @click="handleSearch(rightSearchQuery)"
                >
                  <Search :size="20" />
                </button>
              </div>
            </template>
            <template v-else>
              <a 
                :href="leftButtonHref"
                class="dsf-duo-hero__btn"
                @click="handleLeftButtonClick"
              >
                <InlineText 
                  tagName="span"
                  v-model="settings.leftButtonText"
                  :is-editor="isEditor"
                  placeholder="Button Text"
                />
              </a>
            </template>
          </div>
        </div>
      </div>

      <!-- Right Panel -->

      <div 
        class="dsf-duo-hero__panel"
        :style="{
          flex: `0 0 calc(${100 - splitRatio}% - ${(settings.gap || 20) / 2}px)`
        }"
      >
        <img 
          v-if="settings.rightImage" 
          :src="settings.rightImage" 
          alt="Right Hero"
          class="dsf-duo-hero__panel-img"
        />
        <div v-else class="dsf-duo-hero__panel-placeholder"></div>

        <div class="dsf-duo-hero__overlay"></div>
        <div class="dsf-duo-hero__content" :style="{ color: settings.rightTextColor || '#FFFFFF' }">
          <InlineText 
            tagName="h2" 
            class="dsf-duo-hero__title"
            v-model="settings.rightTitle"
            :is-editor="isEditor"
            placeholder="Enter Title"
          />
          
          <div class="dsf-duo-hero__action">
            <template v-if="settings.rightType === 'search'">
              <div class="dsf-duo-hero__search">
                <input 
                  type="text" 
                  :placeholder="settings.rightSearchPlaceholder"
                  class="dsf-duo-hero__search-input"
                  v-model="rightSearchQuery"
                  @keydown.enter="handleSearch(rightSearchQuery)"
                >
                <button 
                  class="dsf-duo-hero__search-btn"
                  @click="handleSearch(leftSearchQuery)"
                >
                  <Search :size="20" />
                </button>
              </div>
            </template>
            <template v-else>
              <a 
                :href="rightButtonHref"
                class="dsf-duo-hero__btn"
                @click="handleRightButtonClick"
              >
                <InlineText 
                  tagName="span"
                  v-model="settings.rightButtonText"
                  :is-editor="isEditor"
                  placeholder="Button Text"
                />
              </a>
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Search } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'
import { useFlowModal } from '../common/useFlowModal'

const props = defineProps({
  settings: {
    type: Object,
    default: () => ({})
  },
  isEditor: Boolean
})

const { openModal } = useFlowModal()

const leftSearchQuery = ref('')
const rightSearchQuery = ref('')

function handleSearch(query) {
  if (props.isEditor) return
  if (!query) return
  window.location.href = '/?s=' + encodeURIComponent(query)
}

const leftButtonHref = computed(() =>
  (props.settings?.leftButtonAction || 'link') === 'link'
    ? (props.settings?.leftButtonUrl || '#')
    : '#'
)

const rightButtonHref = computed(() =>
  (props.settings?.rightButtonAction || 'link') === 'link'
    ? (props.settings?.rightButtonUrl || '#')
    : '#'
)

function getLeftModalContent() {
  const type = props.settings?.leftButtonModalContentType || 'wysiwyg'
  if (type === 'html') return props.settings?.leftButtonModalHtml || ''
  if (type === 'shortcode') return props.settings?.leftButtonModalShortcode || ''
  return props.settings?.leftButtonModalContent || ''
}

function getRightModalContent() {
  const type = props.settings?.rightButtonModalContentType || 'wysiwyg'
  if (type === 'html') return props.settings?.rightButtonModalHtml || ''
  if (type === 'shortcode') return props.settings?.rightButtonModalShortcode || ''
  return props.settings?.rightButtonModalContent || ''
}

function handleLeftButtonClick(event) {
  if (props.isEditor) {
    event.preventDefault()
    return
  }
  if ((props.settings?.leftButtonAction || 'link') === 'modal') {
    event.preventDefault()
    openModal({
      layout: props.settings?.leftButtonModalLayout || 'center',
      contentType: props.settings?.leftButtonModalContentType || 'wysiwyg',
      content: getLeftModalContent(),
    })
  }
}

function handleRightButtonClick(event) {
  if (props.isEditor) {
    event.preventDefault()
    return
  }
  if ((props.settings?.rightButtonAction || 'link') === 'modal') {
    event.preventDefault()
    openModal({
      layout: props.settings?.rightButtonModalLayout || 'center',
      contentType: props.settings?.rightButtonModalContentType || 'wysiwyg',
      content: getRightModalContent(),
    })
  }
}

const splitRatio = computed(() => {
  const ratio = parseInt(props.settings.splitRatio)
  return isNaN(ratio) ? 50 : ratio
})

const wrapperStyle = computed(() => ({}))

</script>

<style scoped>
.dsf-duo-hero {
  width: 100%;
  position: relative;
  box-sizing: border-box;
  container-type: inline-size;
}

.dsf-duo-hero__container {
  display: flex;
  width: 100%;
  height: 100%;
  min-height: inherit;
}

.dsf-duo-hero__panel {
  position: relative;
  background-size: cover;
  background-position: center;
  position: relative;
  display: flex;
  align-items: flex-end; /* Align content to bottom as per design */
  padding: 40px;
  overflow: hidden;
  border-radius: 8px; /* Optional rounded corners */
}

.dsf-duo-hero__panel-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  position: absolute;
  inset: 0;
}

.dsf-duo-hero__panel-placeholder {
  width: 100%;
  height: 100%;
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, #d1d5db 0%, #9ca3af 100%);
}

.dsf-duo-hero__overlay {
  position: absolute;
  inset: 0;
  z-index: 1;
  height: 50%;
  top: 58%;
  background: linear-gradient(to top, rgba(0,0,0,0.7) 70%, transparent 100%);
}

.dsf-duo-hero__content {
  position: relative;
  z-index: 2;
  width: 100%;
}

.dsf-duo-hero__title {
  font-size: 2.5rem;
  font-weight: 700;
  margin-bottom: 24px;
  line-height: 1.1;
  text-shadow: 0 2px 4px rgba(0,0,0,0.3);
  color: inherit;
}

/* Ensure InlineText inherits color */
.dsf-duo-hero__title :deep(.dsf-inline-text) {
  color: inherit;
}

.dsf-duo-hero__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 12px 32px;
  background-color: #FFFFFF;
  color: #1F2937;
  font-weight: 600;
  text-decoration: none;
  border-radius: 4px; /* Slightly rounded button */
  transition: all 0.2s;
  font-size: 20px; /* Updated font size */
}

.dsf-duo-hero__btn:hover {
  background-color: #F3F4F6;
  transform: translateY(-1px);
}

/* Search Box Styles */
.dsf-duo-hero__search {
  position: relative;
  display: flex;
  align-items: center;
  background: white;
  border-radius: 4px;
  max-width: 400px;
  width: 100%;
  overflow: hidden;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.dsf-duo-hero__search-input {
  width: 100%;
  border: none;
  padding: 10px 44px 10px 16px;
  font-size: 20px; /* Updated font size */
  outline: none;
  color: #374151;
}

.dsf-duo-hero__search-btn {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  padding: 0;
  width: 32px;
  height: 32px;
  border-radius: 4px;
  background: transparent;
  border: none;
  cursor: pointer;
  color: #6B7280;
  display: flex;
  align-items: center;
  justify-content: center;
}

.dsf-duo-hero__search-btn:hover {
  color: #1F2937;
}

@media (max-width: 768px) {
  .dsf-duo-hero__container {
    flex-direction: column;
  }
  
  .dsf-duo-hero__panel {
    flex: 1 1 auto !important;
    min-height: 300px;
    width: 100%;
  }
  
  .dsf-duo-hero__title {
    font-size: 2rem;
  }
}

@container (max-width: 1024px) {
  .dsf-duo-hero__container {
    flex-direction: column;
  }

  .dsf-duo-hero__panel {
    flex: 1 1 auto !important;
    min-height: 320px;
    width: 100%;
  }

  .dsf-duo-hero__title {
    font-size: 2rem;
  }
}
</style>
