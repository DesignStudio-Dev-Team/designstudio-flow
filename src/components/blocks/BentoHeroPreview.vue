<template>
  <div class="dsf-block-preview dsf-bento-hero">
    <div 
      class="dsf-bento-hero__grid"
      :style="{ gap: (settings.gap || 12) + 'px' }"
    >
      <!-- Hero Section (Left, spans 2 rows) -->
      <div class="dsf-bento-hero__hero">
        <img 
          v-if="settings.heroImage" 
          :src="settings.heroImage" 
          alt="Hero"
          class="dsf-bento-hero__hero-img"
        />
        <div v-else class="dsf-bento-hero__hero-placeholder"></div>
        
        <!-- Hero Content Overlay -->
        <div class="dsf-bento-hero__hero-content">
          <InlineText 
            v-model="settings.heroTitle" 
            tagName="h2"
            class="dsf-bento-hero__hero-title"
            :is-editor="isEditor"
            placeholder="Hero Title"
          />
          
          <!-- Search Box -->
          <div v-if="!settings.heroType || settings.heroType === 'search'" class="dsf-bento-hero__search">
            <input 
              type="text" 
              :placeholder="settings.searchPlaceholder || 'Search by keyword'"
              v-model="searchQuery"
              @keydown.enter="handleSearch"
            />
            <button 
              type="button"
              class="dsf-bento-hero__search-btn-icon" 
              @click="handleSearch"
              style="background:none; border:none; position:absolute; right:10px; top: 50%; transform: translateY(-50%); cursor:pointer; padding: 0;"
            >
              <Search :size="20" class="dsf-bento-hero__search-icon" />
            </button>
          </div>
          
          <!-- Hero Button -->
          <a 
            v-else-if="settings.heroType === 'button'"
            :href="heroButtonHref"
            class="dsf-bento-hero__btn"
            @click="handleHeroButtonClick"
          >
            <InlineText 
              v-model="settings.heroButtonText" 
              tagName="span"
              :is-editor="isEditor"
              placeholder="Shop Now"
            />
          </a>
        </div>
      </div>
      
      <!-- Top Row Boxes (3) -->
      <a 
        v-for="i in 3" 
        :key="'box' + i"
        href="#"
        class="dsf-bento-hero__box"
        :style="{ backgroundColor: settings.boxBackground || '#F5F5F4' }"
        @click.prevent
      >
        <img 
          v-if="settings['box' + i + 'Image']" 
          :src="settings['box' + i + 'Image']" 
          :alt="settings['box' + i + 'Title']"
          class="dsf-bento-hero__box-img"
        />
        <div v-else class="dsf-bento-hero__box-placeholder">
          <Image :size="32" />
        </div>
        <InlineText 
          v-model="settings['box' + i + 'Title']" 
          tagName="span"
          class="dsf-bento-hero__box-title"
          :style="{ color: settings.titleColor || '#1F2937' }"
          :is-editor="isEditor"
          :placeholder="'Box ' + i + ' Title'"
        />
      </a>
      
      <!-- Bottom Row Boxes (2) -->
      <a 
        v-for="i in [4, 5]" 
        :key="'box' + i"
        href="#"
        class="dsf-bento-hero__box dsf-bento-hero__box--bottom"
        :style="{ backgroundColor: settings.boxBackground || '#F5F5F4' }"
        @click.prevent
      >
        <img 
          v-if="settings['box' + i + 'Image']" 
          :src="settings['box' + i + 'Image']" 
          :alt="settings['box' + i + 'Title']"
          class="dsf-bento-hero__box-img"
        />
        <div v-else class="dsf-bento-hero__box-placeholder">
          <Image :size="32" />
        </div>
        <InlineText 
          v-model="settings['box' + i + 'Title']" 
          tagName="span"
          class="dsf-bento-hero__box-title"
          :style="{ color: settings.titleColor || '#1F2937' }"
          :is-editor="isEditor"
          :placeholder="'Box ' + i + ' Title'"
        />
      </a>
      
      <!-- CTA Box -->
      <a 
        :href="ctaHref"
        class="dsf-bento-hero__cta"
        :style="{ backgroundColor: settings.ctaColor || '#2C5F5D', color: settings.ctaTextColor || '#FFFFFF' }"
        @click="handleCtaClick"
      >
        <InlineText 
          v-model="settings.ctaText" 
          tagName="span"
          class="dsf-bento-hero__cta-text"
          :is-editor="isEditor"
          placeholder="Shop All Patio Furniture"
        />
        <div class="dsf-bento-hero__cta-arrow">
          <ArrowRight :size="24" />
        </div>
      </a>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Search, Image, ArrowRight } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'
import { useFlowModal } from '../common/useFlowModal'

const props = defineProps({
  settings: Object,
  isEditor: Boolean,
})

const { openModal } = useFlowModal()

const searchQuery = ref('')

function buildSearchUrl(template, query) {
  const normalized = (template || '').trim()
  const encoded = encodeURIComponent(query)
  if (!normalized) return `/?s=${encoded}`
  if (normalized.includes('{query}')) {
    return normalized.split('{query}').join(encoded)
  }
  const joiner = normalized.includes('?')
    ? (normalized.endsWith('?') || normalized.endsWith('&') ? '' : '&')
    : '?'
  return `${normalized}${joiner}s=${encoded}`
}

function handleSearch() {
  if (props.isEditor) return
  if (!searchQuery.value) return
  const targetUrl = buildSearchUrl(props.settings?.searchUrl, searchQuery.value)
  window.location.href = targetUrl
}

const heroButtonHref = computed(() =>
  (props.settings?.heroButtonAction || 'link') === 'link'
    ? (props.settings?.heroButtonUrl || '#')
    : '#'
)

function getHeroModalContent() {
  const type = props.settings?.heroButtonModalContentType || 'wysiwyg'
  if (type === 'html') return props.settings?.heroButtonModalHtml || ''
  if (type === 'shortcode') return props.settings?.heroButtonModalShortcode || ''
  return props.settings?.heroButtonModalContent || ''
}

function handleHeroButtonClick(event) {
  if (props.isEditor) {
    event.preventDefault()
    return
  }
  if ((props.settings?.heroButtonAction || 'link') === 'modal') {
    event.preventDefault()
    openModal({
      layout: props.settings?.heroButtonModalLayout || 'center',
      contentType: props.settings?.heroButtonModalContentType || 'wysiwyg',
      content: getHeroModalContent(),
    })
  }
}

const ctaHref = computed(() =>
  (props.settings?.ctaAction || 'link') === 'link'
    ? (props.settings?.ctaUrl || '#')
    : '#'
)

function getCtaModalContent() {
  const type = props.settings?.ctaModalContentType || 'wysiwyg'
  if (type === 'html') return props.settings?.ctaModalHtml || ''
  if (type === 'shortcode') return props.settings?.ctaModalShortcode || ''
  return props.settings?.ctaModalContent || ''
}

function handleCtaClick(event) {
  if (props.isEditor) {
    event.preventDefault()
    return
  }
  if ((props.settings?.ctaAction || 'link') === 'modal') {
    event.preventDefault()
    openModal({
      layout: props.settings?.ctaModalLayout || 'center',
      contentType: props.settings?.ctaModalContentType || 'wysiwyg',
      content: getCtaModalContent(),
    })
  }
}
</script>

<style scoped>
.dsf-bento-hero {
  width: 100%;
  container-type: inline-size;
}

.dsf-bento-hero__grid {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  grid-template-rows: 1fr 1fr;
  width: 100%;
  min-height: 400px;
}

/* Hero Section - spans 2 rows */
.dsf-bento-hero__hero {
  grid-row: 1 / 3;
  grid-column: 1;
  position: relative;
  border-radius: var(--dsf-radius-lg);
  overflow: hidden;
  background: #E5E7EB;
}

.dsf-bento-hero__hero-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  position: absolute;
  inset: 0;
}

.dsf-bento-hero__hero-placeholder {
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #d1d5db 0%, #9ca3af 100%);
}

.dsf-bento-hero__hero-content {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 1.5rem;
  background: linear-gradient(to top, rgba(0,0,0,0.7) 70%, transparent 100%);
}

.dsf-bento-hero__hero-title {
  font-size: 42px;
  font-weight: 700;
  color: white;
  margin: 0 0 1rem 0;
  text-shadow: 0 1px 3px rgba(0,0,0,0.3);
}

.dsf-bento-hero__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.75rem 2rem;
  background-color: white;
  color: #1F2937;
  font-weight: 600;
  border-radius: 4px;
  text-decoration: none;
  transition: all 0.2s;
  cursor: pointer;
  border: none;
  font-size: 20px; /* Updated font size */
}

.dsf-bento-hero__btn:hover {
  background-color: #F3F4F6;
  transform: translateY(-1px);
}

.dsf-bento-hero__search {
  position: relative;
  display: flex;
  align-items: center;
  max-width: 280px;
}

.dsf-bento-hero__search input {
  width: 100%;
  max-width: 280px;
  padding: 0.625rem 2.5rem 0.625rem 0.875rem;
  border: none;
  border-radius: 4px;
  font-size: 20px; /* Updated font size */
  background: white;
  outline: none;
}



/* Feature Boxes */
.dsf-bento-hero__box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  border-radius: var(--dsf-radius-lg);
  text-decoration: none;
  transition: all 0.2s;
  overflow: hidden;
  position: relative;
}

.dsf-bento-hero__box:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.dsf-bento-hero__box-img {
  position: relative;
  width: 100%;
  flex: 1;
  object-fit: contain;
  margin-bottom: 0.5rem;
}

.dsf-bento-hero__box-placeholder {
  position: relative;
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  color: #9CA3AF;
  opacity: 0.5;
}

.dsf-bento-hero__box-title {
  position: relative;
  z-index: 1;
  font-size: 24px;
  font-weight: 600;
  text-align: center;
  width: 100%;
  flex-shrink: 0;
}

/* CTA Box */
.dsf-bento-hero__cta {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 1.5rem;
  border-radius: var(--dsf-radius-lg);
  text-decoration: none;
  transition: all 0.2s;
}

.dsf-bento-hero__cta:hover {
  transform: translateY(-2px);
  opacity: 0.95;
}

.dsf-bento-hero__cta-text {
  font-size: 24px;
  font-weight: 600;
  text-align: center;
  line-height: 1.3;
}

.dsf-bento-hero__cta-arrow {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: 2px solid currentColor;
  display: flex;
  align-items: center;
  justify-content: center;
}

@container (max-width: 1024px) {
  .dsf-bento-hero__hero-title { font-size: 34px; }
  .dsf-bento-hero__box-title,
  .dsf-bento-hero__cta-text { font-size: 20px; }
}

/* Mobile Responsive using Container Queries */
@container (max-width: 768px) {
  .dsf-bento-hero__grid {
    grid-template-columns: repeat(2, 1fr);
    grid-template-rows: auto;
    height: auto;
  }

  .dsf-bento-hero__hero {
    grid-column: 1 / -1;
    grid-row: auto;
    min-height: 350px;
  }

  .dsf-bento-hero__box,
  .dsf-bento-hero__cta {
    min-height: 200px;
  }

}
</style>
