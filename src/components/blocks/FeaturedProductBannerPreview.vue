<template>
  <div 
    class="dsf-block-preview dsf-fpb"
    :style="backgroundStyle"
  >
    <!-- Inner Container -->
    <div class="dsf-fpb__inner">
      <!-- Sale Banner Ribbon (SVG) -->
      <div class="dsf-fpb__ribbon" :style="{ color: settings.bannerTextColor || '#FFFFFF' }">
        <svg class="dsf-fpb__ribbon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 141.179 209" preserveAspectRatio="none">
          <path d="M0,0H141.179V209L70.4,190.357,0,209Z" :fill="settings.bannerColor || '#2C5F5D'"/>
        </svg>
        <div class="dsf-fpb__ribbon-content">
          <InlineText 
            v-model="settings.bannerText" 
            tagName="span"
            class="dsf-fpb__ribbon-text"
            :is-editor="isEditor"
            placeholder="20%"
          />
          <InlineText 
            v-model="settings.bannerSubtext" 
            tagName="span"
            class="dsf-fpb__ribbon-subtext"
            :is-editor="isEditor"
            placeholder="OFF"
          />
        </div>
      </div>
      
      <!-- Product Circle with Image -->
      <div class="dsf-fpb__product">
        <div 
          class="dsf-fpb__circle"
          :style="{ backgroundColor: settings.circleColor || 'rgba(255,255,255,0.5)' }"
        ></div>
        <img 
          v-if="settings.productImage" 
          :src="settings.productImage" 
          alt="Featured product"
          class="dsf-fpb__product-img"
        />
        <div v-else class="dsf-fpb__product-placeholder">
          <Package :size="48" style="color: #9CA3AF;" />
        </div>
      </div>
      
      <!-- Content Section -->
      <div class="dsf-fpb__content">
        <InlineText 
          v-model="settings.title" 
          tagName="h3"
          class="dsf-fpb__title"
          :style="{ color: settings.titleColor || '#1F2937' }"
          :is-editor="isEditor"
          placeholder="Special Offer"
        />
        
        <InlineText 
          v-model="settings.promoCode" 
          tagName="span"
          class="dsf-fpb__promo"
          :style="{ color: settings.textColor || '#1F2937' }"
          :is-editor="isEditor"
          placeholder="HAPPY2026"
        />
        
        <InlineText 
          v-model="settings.description" 
          tagName="p"
          class="dsf-fpb__desc"
          :style="{ color: settings.textColor || '#1F2937' }"
          :is-editor="isEditor"
          placeholder="Enter promo code at checkout!"
        />
        
        <a 
          :href="buttonHref"
          class="dsf-fpb__btn"
          :style="{ backgroundColor: settings.buttonColor || '#2C5F5D', color: settings.buttonTextColor || '#FFFFFF' }"
          @click="handleButtonClick"
        >
          <InlineText 
            v-model="settings.buttonText" 
            tagName="span"
            :is-editor="isEditor"
            placeholder="Shop All"
          />
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Package } from 'lucide-vue-next'
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

const backgroundStyle = computed(() => {
  const height = (props.settings?.bannerHeight || 240) + 'px'
  const bgType = props.settings?.backgroundType || 'gradient'
  
  if (bgType === 'image' && props.settings?.backgroundImage) {
    return {
      minHeight: height,
      backgroundImage: `url(${props.settings.backgroundImage})`,
      backgroundSize: 'cover',
      backgroundPosition: 'center',
    }
  } else if (bgType === 'gradient') {
    const start = props.settings?.gradientStart || '#B2DFDB'
    const end = props.settings?.gradientEnd || '#C8E6C9'
    const direction = props.settings?.gradientDirection || 'left-right'
    
    let gradientCss
    if (direction === 'left-right') {
      gradientCss = `linear-gradient(to right, ${start} 0%, ${end} 100%)`
    } else if (direction === 'top-bottom') {
      gradientCss = `linear-gradient(to bottom, ${start} 0%, ${end} 100%)`
    } else {
      // radial from center
      gradientCss = `radial-gradient(circle at center, ${start} 0%, ${end} 100%)`
    }
    
    return {
      minHeight: height,
      background: gradientCss,
    }
  } else {
    return {
      minHeight: height,
      backgroundColor: props.settings?.backgroundColor || '#C8E6C9',
    }
  }
})
</script>

<style scoped>
.dsf-fpb {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 240px;
  border-radius: var(--dsf-radius-lg);
  overflow: hidden;
  position: relative;
  container-type: inline-size;
}

/* Inner Container */
.dsf-fpb__inner {
  position: relative;
  width: 100%;
  max-width: 1375px;
  height: 100%;
  min-height: inherit;
  margin: 0 auto;
}

/* Sale Banner Ribbon - SVG pennant style */
.dsf-fpb__ribbon {
  position: absolute;
  left: 2.5rem;
  top: 0;
  width: 141px;
  height: 209px;
  z-index: 2;
}

.dsf-fpb__ribbon-svg {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

.dsf-fpb__ribbon-content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  padding-bottom: 20px;
}

.dsf-fpb__ribbon-text {
  font-size: 42px;
  font-weight: 700;
  line-height: 1;
}

.dsf-fpb__ribbon-subtext {
  font-size: 42px;
  font-weight: 700;
  line-height: 1.1;
}

/* Product Circle - Overflows top and bottom */
.dsf-fpb__product {
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 320px;
  height: 320px;
  z-index: 1;
}

.dsf-fpb__circle {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  position: absolute;
  top: 0;
  left: 0;
}

.dsf-fpb__product-img {
  position: relative;
  z-index: 1;
  max-width: 240px;
  max-height: 200px;
  object-fit: contain;
}

.dsf-fpb__product-placeholder {
  position: relative;
  z-index: 1;
  width: 160px;
  height: 160px;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Content Section */
.dsf-fpb__content {
  position: absolute;
  right: 3rem;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.125rem;
  z-index: 2;
}

.dsf-fpb__title {
  font-size: 42px;
  font-weight: 700;
  font-style: italic;
  margin: 0;
  line-height: 1.2;
}

.dsf-fpb__promo {
  font-size: 24px;
  font-weight: 700;
  margin-top: 0.25rem;
}

.dsf-fpb__desc {
  font-size: 18px;
  font-style: italic;
  margin: 0.25rem 0;
  opacity: 0.9;
}

.dsf-fpb__btn {
  margin-top: 0.75rem;
  padding: 0.625rem 1.5rem;
  font-size: 24px;
  font-weight: 600;
  text-decoration: none;
  border-radius: 4px;
  border: 1px solid transparent;
  transition: all 0.2s;
}

.dsf-fpb__btn:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

@container (max-width: 1024px) {
  .dsf-fpb__product { width: 260px; height: 260px; }
  .dsf-fpb__content { right: 2rem; }
  .dsf-fpb__title { font-size: 32px; }
  .dsf-fpb__promo { font-size: 20px; }
  .dsf-fpb__btn { font-size: 18px; }
}

@container (max-width: 768px) {
  .dsf-fpb {
    padding: 16px;
  }

  .dsf-fpb__inner {
    min-height: auto;
  }

  .dsf-fpb__ribbon {
    position: relative;
    left: auto;
    top: auto;
    margin: 0 auto 1rem;
  }

  .dsf-fpb__product {
    position: relative;
    left: auto;
    top: auto;
    transform: none;
    width: 200px;
    height: 200px;
    margin: 0 auto 1.25rem;
  }

  .dsf-fpb__content {
    position: relative;
    right: auto;
    top: auto;
    transform: none;
    align-items: center;
    text-align: center;
    padding: 0 16px 16px;
  }

  .dsf-fpb__title { font-size: 28px; }
  .dsf-fpb__promo { font-size: 18px; }
  .dsf-fpb__btn { font-size: 18px; }
}
</style>
