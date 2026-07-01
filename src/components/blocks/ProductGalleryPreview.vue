<template>
  <section
    class="dsf-product-gallery"
    :class="[`dsf-product-gallery--${layout}`, `dsf-product-gallery--${aspect}`]"
    :style="blockStyle"
  >
    <div class="dsf-product-gallery__inner" :style="innerStyle">
      <template v-if="images.length">
        <!-- Grid / mosaic -->
        <div v-if="layout === 'grid'" class="dsf-product-gallery__grid" :style="{ gap: `${gap}px` }">
          <button
            v-for="(img, i) in images"
            :key="img.id || i"
            type="button"
            class="dsf-product-gallery__cell"
            :class="{ 'dsf-product-gallery__cell--lead': i === 0 }"
            @click="openLightbox(i)"
          >
            <img :src="img.large" :srcset="img.srcset || undefined" :alt="img.alt || ''" loading="lazy" decoding="async" />
          </button>
        </div>

        <!-- Carousel -->
        <div v-else-if="layout === 'carousel'" class="dsf-product-gallery__carousel">
          <div ref="trackEl" class="dsf-product-gallery__track" :style="{ gap: `${gap}px` }">
            <button
              v-for="(img, i) in images"
              :key="img.id || i"
              type="button"
              class="dsf-product-gallery__slide"
              @click="openLightbox(i)"
            >
              <img :src="img.large" :srcset="img.srcset || undefined" :alt="img.alt || ''" loading="lazy" decoding="async" />
            </button>
          </div>
          <button
            v-if="images.length > 1"
            type="button"
            class="dsf-product-gallery__nav dsf-product-gallery__nav--prev"
            aria-label="Previous image"
            @click="scrollTrack(-1)"
          >
            <ChevronLeft :size="20" />
          </button>
          <button
            v-if="images.length > 1"
            type="button"
            class="dsf-product-gallery__nav dsf-product-gallery__nav--next"
            aria-label="Next image"
            @click="scrollTrack(1)"
          >
            <ChevronRight :size="20" />
          </button>
        </div>

        <!-- Single / thumbs (bottom or left) -->
        <div v-else class="dsf-product-gallery__stage">
          <button
            type="button"
            class="dsf-product-gallery__main"
            :aria-label="enableLightbox ? 'Zoom image' : activeImage.alt || 'Product image'"
            @click="enableLightbox ? openLightbox(activeIndex) : null"
          >
            <img
              :src="activeImage.large"
              :srcset="activeImage.srcset || undefined"
              :alt="activeImage.alt || ''"
              decoding="async"
              :fetchpriority="activeIndex === 0 ? 'high' : 'auto'"
            />
            <span v-if="enableLightbox" class="dsf-product-gallery__zoom" aria-hidden="true"><ZoomIn :size="18" /></span>
          </button>

          <ul
            v-if="showThumbs && layout !== 'single' && images.length > 1"
            class="dsf-product-gallery__thumbs"
            :style="thumbsStyle"
          >
            <li v-for="(img, i) in images" :key="img.id || i">
              <button
                type="button"
                class="dsf-product-gallery__thumb"
                :class="{ 'is-active': i === activeIndex }"
                :aria-current="i === activeIndex ? 'true' : undefined"
                :aria-label="`Show image ${i + 1}`"
                @click="activeIndex = i"
              >
                <img :src="img.thumb" :alt="img.alt || ''" loading="lazy" decoding="async" />
              </button>
            </li>
          </ul>
        </div>
      </template>

      <div v-else class="dsf-product-gallery__placeholder" aria-hidden="true"></div>
    </div>

    <!-- Lightbox -->
    <Teleport to="body">
      <div
        v-if="lightboxOpen"
        ref="lightboxEl"
        class="dsf-product-gallery__lightbox"
        role="dialog"
        aria-modal="true"
        aria-label="Product image viewer"
        @click.self="closeLightbox"
      >
        <button ref="closeBtn" type="button" class="dsf-product-gallery__lb-close" aria-label="Close" @click="closeLightbox">
          <X :size="22" />
        </button>
        <button
          v-if="images.length > 1"
          type="button"
          class="dsf-product-gallery__lb-nav dsf-product-gallery__lb-nav--prev"
          aria-label="Previous image"
          @click="step(-1)"
        >
          <ChevronLeft :size="28" />
        </button>
        <img class="dsf-product-gallery__lb-image" :src="images[lightboxIndex].full" :alt="images[lightboxIndex].alt || ''" />
        <button
          v-if="images.length > 1"
          type="button"
          class="dsf-product-gallery__lb-nav dsf-product-gallery__lb-nav--next"
          aria-label="Next image"
          @click="step(1)"
        >
          <ChevronRight :size="28" />
        </button>
      </div>
    </Teleport>
  </section>
</template>

<script setup>
import { computed, ref, watch, nextTick, onUnmounted } from 'vue'
import { ChevronLeft, ChevronRight, X, ZoomIn } from 'lucide-vue-next'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useProductContext } from '../../utils/useProductContext'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const { product } = useProductContext()

const LAYOUTS = ['thumbs-bottom', 'thumbs-left', 'grid', 'carousel', 'single']
const ASPECTS = ['square', 'portrait', 'landscape', 'natural']

const layout = computed(() => (LAYOUTS.includes(props.settings?.layout) ? props.settings.layout : 'thumbs-bottom'))
const aspect = computed(() => (ASPECTS.includes(props.settings?.aspectRatio) ? props.settings.aspectRatio : 'square'))
const enableLightbox = computed(() => props.settings?.enableLightbox !== false)
const showThumbs = computed(() => props.settings?.showThumbs !== false)
const gap = computed(() => Math.max(0, Math.min(40, Number(props.settings?.gap) ?? 12)))

const images = computed(() => (Array.isArray(product.value?.gallery) ? product.value.gallery : []))

const activeIndex = ref(0)
watch(images, () => { activeIndex.value = 0 })
const activeImage = computed(() => images.value[activeIndex.value] || images.value[0] || {})

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 0
  return { paddingTop: `${paddingY}px`, paddingBottom: `${paddingY}px` }
})

const innerStyle = computed(() => {
  const maxWidth = Number(props.settings?.maxWidth) || 640
  return { maxWidth: `${maxWidth}px` }
})

const thumbsStyle = computed(() => {
  const columns = Math.max(2, Math.min(8, Number(props.settings?.thumbColumns) || 5))
  return { gridTemplateColumns: layout.value === 'thumbs-left' ? '1fr' : `repeat(${columns}, 1fr)`, gap: `${gap.value}px` }
})

const trackEl = ref(null)
function scrollTrack(dir) {
  const el = trackEl.value
  if (!el) return
  el.scrollBy({ left: dir * (el.clientWidth * 0.8), behavior: 'smooth' })
}

// Lightbox
const lightboxOpen = ref(false)
const lightboxIndex = ref(0)
const lightboxEl = ref(null)
const closeBtn = ref(null)

function openLightbox(index) {
  if (!enableLightbox.value) return
  lightboxIndex.value = index
  lightboxOpen.value = true
}
function closeLightbox() {
  lightboxOpen.value = false
}
function step(dir) {
  const len = images.value.length
  if (!len) return
  lightboxIndex.value = (lightboxIndex.value + dir + len) % len
}

function onKeydown(event) {
  if (!lightboxOpen.value) return
  if (event.key === 'Escape') closeLightbox()
  else if (event.key === 'ArrowLeft') step(-1)
  else if (event.key === 'ArrowRight') step(1)
}

watch(lightboxOpen, (open) => {
  if (typeof document === 'undefined') return
  if (open) {
    document.addEventListener('keydown', onKeydown)
    nextTick(() => closeBtn.value?.focus())
  } else {
    document.removeEventListener('keydown', onKeydown)
  }
})

onUnmounted(() => {
  if (typeof document !== 'undefined') document.removeEventListener('keydown', onKeydown)
})
</script>

<style scoped>
.dsf-product-gallery { width: 100%; }
.dsf-product-gallery__inner { margin: 0 auto; }

/* Aspect ratios applied to image frames */
.dsf-product-gallery__main,
.dsf-product-gallery__cell,
.dsf-product-gallery__slide,
.dsf-product-gallery__placeholder {
  position: relative;
  display: block;
  width: 100%;
  padding: 0;
  border: 0;
  background: var(--dsf-gray-100, #f3f4f6);
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
}

.dsf-product-gallery--square .dsf-product-gallery__main,
.dsf-product-gallery--square .dsf-product-gallery__cell,
.dsf-product-gallery--square .dsf-product-gallery__slide { aspect-ratio: 1 / 1; }
.dsf-product-gallery--portrait .dsf-product-gallery__main,
.dsf-product-gallery--portrait .dsf-product-gallery__cell,
.dsf-product-gallery--portrait .dsf-product-gallery__slide { aspect-ratio: 3 / 4; }
.dsf-product-gallery--landscape .dsf-product-gallery__main,
.dsf-product-gallery--landscape .dsf-product-gallery__cell,
.dsf-product-gallery--landscape .dsf-product-gallery__slide { aspect-ratio: 4 / 3; }

.dsf-product-gallery__main img,
.dsf-product-gallery__cell img,
.dsf-product-gallery__slide img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.dsf-product-gallery--natural .dsf-product-gallery__main img { height: auto; object-fit: contain; }

.dsf-product-gallery__placeholder { aspect-ratio: 1 / 1; cursor: default; }

.dsf-product-gallery__zoom {
  position: absolute;
  right: 10px;
  bottom: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 999px;
  background: rgba(17, 24, 39, 0.65);
  color: #fff;
}

/* Stage layouts */
.dsf-product-gallery__stage { display: flex; flex-direction: column; gap: 12px; }
.dsf-product-gallery--thumbs-left .dsf-product-gallery__stage { flex-direction: row-reverse; align-items: flex-start; }
.dsf-product-gallery--thumbs-left .dsf-product-gallery__main { flex: 1; }

.dsf-product-gallery__thumbs {
  display: grid;
  margin: 0;
  padding: 0;
  list-style: none;
}
.dsf-product-gallery--thumbs-left .dsf-product-gallery__thumbs {
  width: 84px;
  flex: 0 0 84px;
}

.dsf-product-gallery__thumb {
  display: block;
  width: 100%;
  aspect-ratio: 1 / 1;
  padding: 0;
  border: 2px solid transparent;
  border-radius: 8px;
  overflow: hidden;
  background: var(--dsf-gray-100, #f3f4f6);
  cursor: pointer;
}
.dsf-product-gallery__thumb.is-active { border-color: var(--dsf-theme-primary, #2c5f5d); }
.dsf-product-gallery__thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }

/* Grid */
.dsf-product-gallery__grid { display: grid; grid-template-columns: repeat(2, 1fr); }
.dsf-product-gallery__cell--lead { grid-column: 1 / -1; }

/* Carousel */
.dsf-product-gallery__carousel { position: relative; }
.dsf-product-gallery__track {
  display: flex;
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  scrollbar-width: none;
}
.dsf-product-gallery__track::-webkit-scrollbar { display: none; }
.dsf-product-gallery__slide { flex: 0 0 82%; scroll-snap-align: center; }

.dsf-product-gallery__nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border: 0;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.92);
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.18);
  color: #111827;
  cursor: pointer;
}
.dsf-product-gallery__nav--prev { left: 8px; }
.dsf-product-gallery__nav--next { right: 8px; }

/* Lightbox */
.dsf-product-gallery__lightbox {
  position: fixed;
  inset: 0;
  z-index: 100050;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4vw;
  background: rgba(8, 12, 20, 0.92);
}
.dsf-product-gallery__lb-image { max-width: 92vw; max-height: 86vh; object-fit: contain; border-radius: 6px; }
.dsf-product-gallery__lb-close {
  position: absolute;
  top: 18px;
  right: 18px;
  display: inline-flex;
  width: 42px;
  height: 42px;
  align-items: center;
  justify-content: center;
  border: 0;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  cursor: pointer;
}
.dsf-product-gallery__lb-nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  display: inline-flex;
  width: 48px;
  height: 48px;
  align-items: center;
  justify-content: center;
  border: 0;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  cursor: pointer;
}
.dsf-product-gallery__lb-nav--prev { left: 3vw; }
.dsf-product-gallery__lb-nav--next { right: 3vw; }
</style>
