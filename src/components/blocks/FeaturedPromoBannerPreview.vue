<template>
  <div class="dsf-block-preview dsf-featured-promo" :style="previewStyle">
    <div class="dsf-featured-promo__container">
      
      <!-- Layer 1: Image Background (Right Side mainly, but full cover behind SVG) -->
      <div class="dsf-featured-promo__image-layer">
        <img 
          v-if="settings.image" 
          :src="settings.image" 
          alt="Banner Image"
          class="dsf-featured-promo__img"
        />
        <div v-else class="dsf-featured-promo__placeholder"></div>
      </div>

      <!-- Layer 2: SVG Overlay (Background + Decoration + Mask) -->
      <div class="dsf-featured-promo__svg-layer">
        <svg 
          class="dsf-featured-promo__svg" 
          viewBox="0 0 809 450" 
          preserveAspectRatio="none" 
          xmlns="http://www.w3.org/2000/svg"
        >
          <g transform="translate(-75 -1232)">
            <!-- Decorative Circles -->
            <path 
              d="M704.766,450H0V0H749.847C650.578,54.049,591.312,143.581,591.312,239.5c0,40.539,10.272,79.755,30.532,116.556a281,281,0,0,0,35.726,50.449A322.872,322.872,0,0,0,704.764,450Z" 
              transform="translate(134.093 1232)" 
              fill="#fff" 
              opacity="0.43"
            />
            <path 
              d="M704.766,450H0V0H749.847C650.578,54.049,591.312,143.581,591.312,239.5c0,40.539,10.272,79.755,30.532,116.556a281,281,0,0,0,35.726,50.449A322.872,322.872,0,0,0,704.764,450Z" 
              transform="translate(98.093 1232)" 
              fill="#fff" 
              opacity="0.43"
            />
            <!-- Main Background Shape -->
            <path 
              d="M704.767,450H0V0H749.847C650.578,54.049,591.313,143.581,591.313,239.5c0,40.539,10.272,79.755,30.532,116.556a280.993,280.993,0,0,0,35.725,50.449A322.871,322.871,0,0,0,704.764,450l0,0h0Z" 
              transform="translate(75 1232)" 
              :fill="settings.backgroundColor || '#E0F2F1'"
            />
          </g>
        </svg>
      </div>

      <!-- Layer 3: Content -->
      <div class="dsf-featured-promo__content" :style="{ color: settings.textColor || '#1F2937' }">
        <InlineText 
          tagName="h2" 
          class="dsf-featured-promo__title"
          :style="{ color: settings.titleColor || '#1F2937' }"
          v-model="settings.headerText"
          :is-editor="isEditor"
          placeholder="New At Backyard Leisure"
        />
        
        <div class="dsf-featured-promo__divider" :style="{ borderColor: settings.badgeColor || '#3D736A' }"></div>
        
        <InlineText 
          tagName="p" 
          class="dsf-featured-promo__description"
          v-model="settings.descriptionText"
          :is-editor="isEditor"
          placeholder="Description goes here..."
          :multiline="true"
        />
        
        <a 
          :href="buttonHref"
          class="dsf-featured-promo__arrow-btn"
          :style="{ backgroundColor: settings.badgeColor || '#3D736A' }"
          @click="handleButtonClick"
        >
          <span class="dsf-featured-promo__btn-text" :style="{ color: settings.circleTextColor || '#FFFFFF' }">
            <InlineText 
              tagName="span"
              v-model="settings.buttonText"
              :is-editor="isEditor"
              placeholder="Get Started"
            />
          </span>
          <ArrowRight :size="24" :color="settings.circleTextColor || '#FFFFFF'" class="dsf-featured-promo__btn-icon" />
        </a>
      </div>

      <!-- Badge Guide (Matches SVG dimensions for positioning) -->
      <div class="dsf-featured-promo__badge-guide">
        <div 
          v-if="settings.badgeType"
          class="dsf-featured-promo__badge"
          :class="[
            `dsf-featured-promo__badge--${settings.badgePosition || 'bottom-right'}`,
            `dsf-featured-promo__badge--${settings.badgeType}`
          ]"
          :style="{ 
            backgroundColor: settings.badgeColor || '#3D736A',
            color: settings.circleTextColor || '#FFFFFF'
          }"
        >
          <!-- New Badge -->
          <template v-if="settings.badgeType === 'new'">
            <span class="dsf-badge-lg">NEW</span>
            <span class="dsf-badge-sm">IN STOCK</span>
          </template>
          
          <!-- Low Stock Badge -->
          <template v-else-if="settings.badgeType === 'low'">
            <span class="dsf-badge-lg">LOW</span>
            <span class="dsf-badge-md">STOCK</span>
          </template>
          
          <!-- Custom Badge -->
          <template v-else>
            <span class="dsf-badge-md">{{ settings.badgeCustomLine1 || 'Special' }}</span>
            <span class="dsf-badge-md">{{ settings.badgeCustomLine2 || 'Offer' }}</span>
          </template>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { ArrowRight } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'
import { useFlowModal } from '../common/useFlowModal'
import { getResponsiveValue } from '../../utils/responsiveSettings'

const props = defineProps({
  settings: {
    type: Object,
    default: () => ({})
  },
  isEditor: Boolean,
  previewMode: {
    type: String,
    default: 'desktop',
  },
})

const { openModal } = useFlowModal()

const previewStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 0
  return {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
  }
})

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
</script>

<style scoped>
.dsf-featured-promo {
  width: 100%;
  container-type: inline-size;
}

.dsf-featured-promo__container {
  display: block; /* Removed grid */
  border-radius: 8px;
  overflow: hidden;
  min-height: 450px; /* Match SVG height ratio approx */
  position: relative;
  background-color: #f3f4f6; /* Default background if image/svg missing */
}

/* Layer 1: Image */
.dsf-featured-promo__image-layer {
  position: absolute;
  top: 0;
  right: 0;
  width: 100%; /* Cover full width, SVG will mask left */
  height: 100%;
  z-index: 0;
}

.dsf-featured-promo__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.dsf-featured-promo__placeholder {
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #d1d5db 0%, #9ca3af 100%);
}

/* Layer 2: SVG */
.dsf-featured-promo__svg-layer {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%; /* Full width up to max */
  max-width: 900px;
  height: 100%;
  z-index: 1;
  pointer-events: none; /* Let clicks pass through if needed, though content is on top */
}

.dsf-featured-promo__svg {
  width: 100%;
  height: 100%;
  display: block;
}

/* Layer 3: Content */
.dsf-featured-promo__content {
  position: relative;
  z-index: 2;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  padding: 40px;
  width: 100%; /* Match SVG width */
  max-width: 700px;
  height: 100%;
  min-height: 450px; /* Ensure content area matches height */
}

.dsf-featured-promo__title {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: 42px;
  font-weight: 700;
  margin-bottom: 24px;
  line-height: 1.15;
  width: 100%;
  max-width: 600px;
  word-wrap: break-word;
  overflow-wrap: break-word;
}

.dsf-featured-promo__divider {
  width: 60px;
  border-bottom: 3px solid;
  margin-bottom: 24px;
}

.dsf-featured-promo__description {
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: 24px;
  margin-bottom: 32px;
  line-height: 1.4;
  max-width: 80%;
  word-wrap: break-word;
  overflow-wrap: break-word;
}

.dsf-featured-promo__arrow-btn {
  height: 48px;
  min-width: 48px;
  border-radius: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.4s ease;
  cursor: pointer;
  padding: 0;
  position: relative;
  overflow: hidden;
  text-decoration: none;
}

.dsf-featured-promo__arrow-btn:hover {
  padding-left: 20px;
  padding-right: 16px;
  min-width: 140px; /* Expands to fit text */
}

.dsf-featured-promo__btn-text {
  font-family: var(--dsf-theme-body-font, inherit);
  color: white;
  font-weight: 600;
  font-size: 16px;
  max-width: 0;
  opacity: 0;
  white-space: nowrap;
  transition: all 0.4s ease;
  overflow: hidden;
  margin-right: 0;
  line-height: 1.25;
}

.dsf-featured-promo__arrow-btn:hover .dsf-featured-promo__btn-text {
  max-width: 200px;
  opacity: 1;
  margin-right: 8px;
}

.dsf-featured-promo__btn-icon {
  transition: transform 0.4s ease;
}

/* Badge (Layer 4 basically) */
/* Badge Guide */
.dsf-featured-promo__badge-guide {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 3;
  pointer-events: none;
}

/* Badge (Layer 4 basically) */
.dsf-featured-promo__badge {
  position: absolute;
  /* z-index removed here as parent handles layer, but keeping for safety if moved */
  border-radius: 50%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  border: 4px solid white;
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
  width: 140px;
  height: 140px;
  line-height: 1;
  pointer-events: auto; /* Re-enable clicks */
}

/* Positions */
.dsf-featured-promo__badge--bottom-right {
  bottom: 20px;
  right: 20px;
}

.dsf-featured-promo__badge--overlapping {
  bottom: 50px;
  right: 100px; /* Centers 140px badge on the right edge */
  /* If container clips, user might need to adjust, but this is the "Overlapping" intent */
}

/* Font Sizes */
.dsf-badge-lg {
  font-size: 36px;
  font-weight: 700;
  display: block;
}

.dsf-badge-md {
  font-size: 26px;
  font-weight: 600;
  display: block;
}

.dsf-badge-sm {
  font-size: 19px;
  font-weight: 500;
  display: block;
}

.dsf-featured-promo__badge--new .dsf-badge-lg {
  margin-bottom: 4px;
}

.dsf-featured-promo__badge--low .dsf-badge-lg {
  margin-bottom: 2px;
}

/* Mobile */
@media (max-width: 768px) {
  .dsf-featured-promo__container {
    grid-template-columns: 1fr;
  }
  
  .dsf-featured-promo__curve {
    display: none; /* Hide curve on mobile stack */
  }
  
  .dsf-featured-promo__image-container {
    height: 300px;
    order: -1; /* Image on top */
  }
  
  .dsf-featured-promo__badge {
    width: 100px;
    height: 100px;
  }
  
  .dsf-badge-lg { font-size: 24px; }
  .dsf-badge-md { font-size: 18px; }
  .dsf-badge-sm { font-size: 14px; }
}

@container (max-width: 1024px) {
  .dsf-featured-promo__title { font-size: 34px; }
  .dsf-featured-promo__description { font-size: 18px; }
}

@container (max-width: 768px) {
  .dsf-featured-promo__image-layer {
    display: none;
  }

  .dsf-featured-promo__svg-layer,
  .dsf-featured-promo__badge-guide {
    display: none;
  }

  .dsf-featured-promo__container {
    min-height: auto;
  }

  .dsf-featured-promo__content {
    max-width: 100%;
    min-height: auto;
    padding: 24px;
  }

  .dsf-featured-promo__title { font-size: 28px; }
  .dsf-featured-promo__description { font-size: 16px; }
}
</style>
