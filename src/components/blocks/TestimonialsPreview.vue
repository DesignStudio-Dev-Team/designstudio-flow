<template>
  <div 
    class="dsf-block-preview dsf-testimonials"
    :style="previewStyle"
  >
    <div class="dsf-testimonials__container">
      <!-- Slider viewport -->
      <div class="dsf-testimonials__viewport">
        <div 
          class="dsf-testimonials__track"
          :style="{ transform: `translateX(-${currentIndex * 100}%)` }"
        >
          <!-- Each testimonial slide -->
          <div 
            v-for="(t, idx) in displayTestimonials" 
            :key="idx"
            class="dsf-testimonials__slide"
          >
            <div 
              class="dsf-testimonial-card"
              :class="{ 'dsf-testimonial-card--with-image': t.image }"
            >
              <!-- Content side -->
              <div class="dsf-testimonial-card__content">
                <!-- Quote Icon -->
                <div class="dsf-testimonial-card__quote-icon" :style="{ color: settings.primaryColor || '#0F6B8C' }">
                  <svg width="48" height="40" viewBox="0 0 48 40" fill="currentColor">
                    <path d="M0 24.8889C0 11.5556 9.33333 2.22222 22.6667 0V8.88889C15.1111 11.1111 11.5556 16 11.5556 22.2222H20V40H0V24.8889ZM26.2222 24.8889C26.2222 11.5556 35.5556 2.22222 48.8889 0V8.88889C41.3333 11.1111 37.7778 16 37.7778 22.2222H46.2222V40H26.2222V24.8889Z" transform="translate(-0.667)"/>
                  </svg>
                </div>
                
                <!-- Title -->
                <InlineText 
                  v-model="settings.testimonials[idx].title" 
                  tagName="h3"
                  class="dsf-testimonial-card__title"
                  :style="{ color: settings.titleColor || '#1F2937' }"
                  :is-editor="isEditor"
                  placeholder="Great experience!"
                />
                
                <!-- Quote text -->
                <InlineText 
                  v-model="settings.testimonials[idx].quote" 
                  tagName="p"
                  class="dsf-testimonial-card__quote"
                  :style="{ color: settings.textColor || '#4B5563' }"
                  :is-editor="isEditor"
                  placeholder="Share your testimonial here..."
                  :multiline="true"
                />
                
                <!-- Author info -->
                <div class="dsf-testimonial-card__author">
                  <InlineText 
                    v-model="settings.testimonials[idx].author" 
                    tagName="span"
                    class="dsf-testimonial-card__name"
                    :is-editor="isEditor"
                    placeholder="John Doe"
                  />
                  <InlineText 
                    v-model="settings.testimonials[idx].location" 
                    tagName="span"
                    class="dsf-testimonial-card__location"
                    :is-editor="isEditor"
                    placeholder="New York, NY"
                  />
                </div>
              </div>
              
              <!-- Image side (optional) -->
              <div v-if="t.image" class="dsf-testimonial-card__image">
                <img :src="t.image" :alt="t.author" />
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Navigation Arrows -->
      <button 
        v-if="displayTestimonials.length > 1 && canScrollPrev"
        class="dsf-testimonials__nav dsf-testimonials__nav--prev"
        :style="{ backgroundColor: settings.primaryColor || '#0F6B8C' }"
        @click="prev"
      >
        <ArrowLeft :size="20" />
      </button>
      <button 
        v-if="displayTestimonials.length > 1 && canScrollNext"
        class="dsf-testimonials__nav dsf-testimonials__nav--next"
        :style="{ backgroundColor: settings.primaryColor || '#0F6B8C' }"
        @click="next"
      >
        <ArrowRight :size="20" />
      </button>
    </div>
    
    <!-- Pagination Dots -->
    <div v-if="displayTestimonials.length > 1" class="dsf-testimonials__dots">
      <button 
        v-for="(_, idx) in displayTestimonials" 
        :key="idx"
        class="dsf-testimonials__dot"
        :class="{ 'dsf-testimonials__dot--active': idx === currentIndex }"
        :style="idx === currentIndex ? { backgroundColor: settings.primaryColor || '#0F6B8C' } : {}"
        @click="currentIndex = idx"
      ></button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { ArrowLeft, ArrowRight } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'

const props = defineProps({
  settings: Object,
  isEditor: Boolean,
  previewMode: {
    type: String,
    default: 'desktop',
  },
})

const currentIndex = ref(0)

const previewStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 60
  const paddingX = getResponsiveValue(props.settings || {}, props.previewMode, 'paddingX') ?? 24
  return {
    padding: `${paddingY}px ${paddingX}px`,
    backgroundColor: props.settings?.backgroundColor || '#FFFFFF',
  }
})

const displayTestimonials = computed(() => {
  return props.settings?.testimonials || [
    { title: 'Testimonial Title', quote: 'Share your testimonial here...', author: 'Customer Name', location: 'City, State', image: '' },
  ]
})

const canScrollNext = computed(() => currentIndex.value < displayTestimonials.value.length - 1)
const canScrollPrev = computed(() => currentIndex.value > 0)

function next() {
  if (canScrollNext.value) currentIndex.value++
}

function prev() {
  if (canScrollPrev.value) currentIndex.value--
}
</script>

<style scoped>
.dsf-testimonials__container {
  position: relative;
  max-width: 1000px;
  margin: 0 auto;
}

.dsf-testimonials {
  container-type: inline-size;
}

.dsf-testimonials__viewport {
  overflow: hidden;
}

.dsf-testimonials__track {
  display: flex;
  transition: transform 0.4s ease;
}

.dsf-testimonials__slide {
  flex: 0 0 100%;
  min-width: 100%;
  padding: 0 16px;
  box-sizing: border-box;
}

/* Testimonial Card */
.dsf-testimonial-card {
  display: flex;
  align-items: stretch;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  overflow: hidden;
  min-height: 300px;
}

.dsf-testimonial-card--with-image {
  display: grid;
  grid-template-columns: 1fr 1fr;
}

.dsf-testimonial-card__content {
  padding: 32px 40px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.dsf-testimonial-card__quote-icon {
  margin-bottom: 16px;
}

.dsf-testimonial-card__title {
  font-size: 38px;
  font-weight: 700;
  margin: 0 0 16px 0;
  line-height: 1.3;
}

.dsf-testimonial-card__quote {
  font-size: 24px;
  line-height: 1.6;
  margin: 0 0 24px 0;
}

.dsf-testimonial-card__author {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.dsf-testimonial-card__name {
  font-weight: 700;
  font-size: 24px;
  color: #1F2937;
}

.dsf-testimonial-card__location {
  font-size: 24px;
  color: #6B7280;
}

.dsf-testimonial-card__image {
  position: relative;
  overflow: hidden;
}

.dsf-testimonial-card__image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Navigation Arrows */
.dsf-testimonials__nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 44px;
  height: 44px;
  border-radius: 50%;
  color: white;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  z-index: 10;
}

.dsf-testimonials__nav:hover {
  opacity: 0.85;
  transform: translateY(-50%) scale(1.05);
}

.dsf-testimonials__nav--prev {
  left: -20px;
}

.dsf-testimonials__nav--next {
  right: -20px;
}

/* Pagination Dots */
.dsf-testimonials__dots {
  display: flex;
  justify-content: center;
  gap: 8px;
  margin-top: 24px;
}

.dsf-testimonials__dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #D1D5DB;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
  padding: 0;
}

.dsf-testimonials__dot:hover {
  background: #9CA3AF;
}

/* Responsive */
@media (max-width: 768px) {
  .dsf-testimonial-card--with-image {
    grid-template-columns: 1fr;
  }
  
  .dsf-testimonial-card__image {
    height: 250px;
    order: -1;
  }
  
  .dsf-testimonials__nav {
    display: none;
  }
}

@container (max-width: 1024px) {
  .dsf-testimonial-card__title { font-size: 30px; }
  .dsf-testimonial-card__quote,
  .dsf-testimonial-card__name,
  .dsf-testimonial-card__location { font-size: 18px; }
}

@container (max-width: 768px) {
  .dsf-testimonial-card {
    flex-direction: column;
  }

  .dsf-testimonial-card__content {
    padding: 24px;
  }

  .dsf-testimonials__nav {
    display: none;
  }
}
</style>
