<template>
  <div 
    class="dsf-block-preview dsf-promo-banner"
    :class="{ 'dsf-promo-banner--left': settings.contentPosition === 'left' }"
    :style="{ minHeight: (settings.bannerHeight || 280) + 'px' }"
  >
    <!-- Image Section -->
    <div class="dsf-promo-banner__image">
      <img 
        v-if="settings.image" 
        :src="settings.image" 
        alt="Promotional banner"
        :style="{ objectPosition: 'center ' + (settings.imagePosition || 'center') }"
      />
      <div v-else class="dsf-promo-banner__placeholder">
        <Image :size="64" style="color: #9CA3AF;" />
        <span>Add Banner Image</span>
      </div>
    </div>
    
    <!-- Content Panel -->
    <div 
      class="dsf-promo-banner__panel"
      :style="{ ...panelStyle, width: (settings.panelWidth || 280) + 'px' }"
    >
      <div class="dsf-promo-banner__content">
        <!-- Pre-text (e.g., "UP TO") -->
        <InlineText 
          v-model="settings.preText" 
          tagName="span"
          class="dsf-promo-banner__pretext"
          :is-editor="isEditor"
          placeholder="UP TO"
        />
        
        <!-- Discount Amount with Percent and Suffix -->
        <div class="dsf-promo-banner__discount">
          <InlineText 
            v-model="settings.discountAmount" 
            tagName="span"
            class="dsf-promo-banner__amount"
            :is-editor="isEditor"
            placeholder="20"
          />
          <div class="dsf-promo-banner__suffix-col">
            <span v-if="settings.showPercent !== false" class="dsf-promo-banner__percent">%</span>
            <InlineText 
              v-model="settings.discountSuffix" 
              tagName="span"
              class="dsf-promo-banner__suffix"
              :is-editor="isEditor"
              placeholder="OFF"
            />
          </div>
        </div>
        
        <!-- Divider -->
        <div 
          class="dsf-promo-banner__divider" 
          :style="{ backgroundColor: settings.dividerColor || '#FFFFFF' }"
        ></div>
        
        <!-- Subtitle Lines -->
        <div class="dsf-promo-banner__subtitle">
          <InlineText 
            v-model="settings.subtitle" 
            tagName="span"
            :is-editor="isEditor"
            placeholder="Select"
          />
          <InlineText 
            v-model="settings.subtitle2" 
            tagName="strong"
            :is-editor="isEditor"
            placeholder="Casual Seating"
          />
        </div>
        
        <!-- Button -->
        <a 
          :href="buttonHref"
          class="dsf-promo-banner__btn"
          :style="buttonStyle"
          @click="handleButtonClick"
        >
          <InlineText 
            v-model="settings.buttonText" 
            tagName="span"
            :is-editor="isEditor"
            placeholder="Shop Now"
          />
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Image } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'
import { useFlowModal } from '../common/useFlowModal'

const props = defineProps({
  settings: Object,
  isEditor: Boolean,
})

const { openModal } = useFlowModal()

const buttonHref = computed(() =>
  (props.settings?.buttonAction || 'link') === 'link'
    ? (props.settings?.buttonUrl || '#')
    : '#'
)

function getModalContent() {
  const type = props.settings?.buttonModalContentType || 'wysiwyg'
  if (type === 'html') return props.settings?.buttonModalHtml || ''
  if (type === 'shortcode') return props.settings?.buttonModalShortcode || ''
  return props.settings?.buttonModalContent || ''
}

function handleButtonClick(event) {
  if (props.isEditor) {
    event.preventDefault()
    return
  }
  if ((props.settings?.buttonAction || 'link') === 'modal') {
    event.preventDefault()
    openModal({
      layout: props.settings?.buttonModalLayout || 'center',
      contentType: props.settings?.buttonModalContentType || 'wysiwyg',
      content: getModalContent(),
    })
  }
}

const panelStyle = computed(() => ({
  backgroundColor: props.settings?.panelColor || '#2C5F5D',
  color: props.settings?.textColor || '#FFFFFF',
}))

const buttonStyle = computed(() => ({
  color: props.settings?.panelColor || '#2C5F5D',
  backgroundColor: props.settings?.textColor || '#FFFFFF',
}))
</script>

<style scoped>
.dsf-promo-banner {
  display: flex;
  width: 100%;
  min-height: 280px;
  border-radius: var(--dsf-radius-lg);
  overflow: hidden;
  background: #F3F4F6;
  container-type: inline-size;
}

.dsf-promo-banner--left {
  flex-direction: row-reverse;
}

/* Image Section */
.dsf-promo-banner__image {
  flex: 1;
  min-width: 0;
  position: relative;
}

.dsf-promo-banner__image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  position: absolute;
  inset: 0;
}

.dsf-promo-banner__placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  height: 100%;
  min-height: 280px;
  color: #9CA3AF;
  font-size: 0.875rem;
}

/* Content Panel */
.dsf-promo-banner__panel {
  width: 280px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.dsf-promo-banner__content {
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
}

.dsf-promo-banner__pretext {
  font-size: 24px;
  font-weight: 500;
  letter-spacing: 0.05em;
  opacity: 0.9;
}

.dsf-promo-banner__discount {
  display: flex;
  align-items: flex-end;
  gap: 0;
  line-height: 1;
}

.dsf-promo-banner__amount {
  font-size: 142px;
  font-weight: 300;
  letter-spacing: -0.02em;
  line-height: 0.85;
}

.dsf-promo-banner__suffix-col {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: flex-end;
  margin-left: 0.1em;
  height: 100%;
}

.dsf-promo-banner__percent {
  font-size: 85px;
  font-weight: 300;
  line-height: 1;
}

.dsf-promo-banner__suffix {
  font-size: 34px;
  font-weight: 500;
  line-height: 1;
}

.dsf-promo-banner__divider {
  width: 60px;
  height: 2px;
  margin: 0.75rem 0;
  opacity: 0.6;
}

.dsf-promo-banner__subtitle {
  display: flex;
  flex-direction: column;
  font-size: 24px;
  line-height: 1.4;
}

.dsf-promo-banner__subtitle strong {
  font-weight: 700;
}

.dsf-promo-banner__btn {
  margin-top: 1rem;
  padding: 0.625rem 1.5rem;
  font-size: 24px;
  font-weight: 600;
  text-decoration: none;
  border-radius: 4px;
  border: 1px solid currentColor;
  transition: all 0.2s;
}

.dsf-promo-banner__btn:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

@container (max-width: 1024px) {
  .dsf-promo-banner__amount { font-size: 110px; }
  .dsf-promo-banner__percent { font-size: 68px; }
  .dsf-promo-banner__suffix { font-size: 28px; }
  .dsf-promo-banner__panel { width: min(420px, 45%) !important; }
}

@container (max-width: 768px) {
  .dsf-promo-banner { flex-direction: column; }
  .dsf-promo-banner__panel { width: 100% !important; padding: 1.5rem; }
  .dsf-promo-banner__amount { font-size: 96px; }
  .dsf-promo-banner__percent { font-size: 56px; }
  .dsf-promo-banner__suffix { font-size: 24px; }
  .dsf-promo-banner__btn { width: 100%; text-align: center; }
}
</style>
