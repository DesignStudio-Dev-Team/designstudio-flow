<template>
  <div class="dsf-schematic" :class="`dsf-schematic--${type}`">
    <!-- Hero -->
    <template v-if="type === 'hero'">
      <div class="dsf-schematic__hero">
        <div class="dsf-schematic__line dsf-schematic__line--lg"></div>
        <div class="dsf-schematic__line dsf-schematic__line--sm"></div>
        <div class="dsf-schematic__btn"></div>
      </div>
    </template>

    <!-- Header Mega Menu -->
    <template v-else-if="type === 'header-mega-menu'">
      <div class="dsf-schematic__header-mega">
        <div class="dsf-schematic__header-mega-top">
          <div class="dsf-schematic__line dsf-schematic__line--xxs"></div>
          <div class="dsf-schematic__line dsf-schematic__line--xs"></div>
        </div>
        <div class="dsf-schematic__header-mega-nav">
          <div class="dsf-schematic__header-mega-item" v-for="i in 5" :key="i"></div>
        </div>
      </div>
    </template>

    <!-- Header Cutout Mega -->
    <template v-else-if="type === 'header-cutout-mega'">
      <div class="dsf-schematic__header-mega">
        <div class="dsf-schematic__header-mega-top">
          <div class="dsf-schematic__line dsf-schematic__line--xxs"></div>
          <div class="dsf-schematic__line dsf-schematic__line--xs"></div>
        </div>
        <div class="dsf-schematic__header-mega-nav">
          <div class="dsf-schematic__header-mega-item" v-for="i in 6" :key="i"></div>
        </div>
      </div>
    </template>

    <!-- Footer Dealers -->
    <template v-else-if="type === 'footer-dealers'">
      <div class="dsf-schematic__footer-dealers">
        <div class="dsf-schematic__footer-dealers-cards">
          <div class="dsf-schematic__footer-dealers-card" v-for="i in 2" :key="i"></div>
        </div>
        <div class="dsf-schematic__footer-dealers-bottom"></div>
      </div>
    </template>
    
    <!-- Features Grid -->
    <template v-else-if="type === 'features-grid'">
      <div class="dsf-schematic__features-grid">
        <div class="dsf-schematic__line dsf-schematic__line--center"></div>
        <div class="dsf-schematic__grid-3">
          <div class="dsf-schematic__card" v-for="i in 3" :key="i">
            <div class="dsf-schematic__card-icon"></div>
            <div class="dsf-schematic__line dsf-schematic__line--xs"></div>
          </div>
        </div>
      </div>
    </template>
    
    <!-- Text + Image -->
    <template v-else-if="type === 'text-image'">
      <div class="dsf-schematic__text-image">
        <div class="dsf-schematic__text-block">
          <div class="dsf-schematic__line dsf-schematic__line--md"></div>
          <div class="dsf-schematic__line dsf-schematic__line--sm"></div>
          <div class="dsf-schematic__line dsf-schematic__line--sm"></div>
        </div>
        <div class="dsf-schematic__img-block"></div>
      </div>
    </template>
    
    <!-- Testimonials -->
    <template v-else-if="type === 'testimonials'">
      <div class="dsf-schematic__testimonials">
        <div class="dsf-schematic__testimonial-card">
          <!-- Content side -->
          <div class="dsf-schematic__testimonial-content">
            <div class="dsf-schematic__quote-mark">❝</div>
            <div class="dsf-schematic__line dsf-schematic__line--sm" style="background: #374151; height: 6px;"></div>
            <div class="dsf-schematic__line dsf-schematic__line--md" style="height: 4px;"></div>
            <div class="dsf-schematic__line dsf-schematic__line--sm" style="height: 4px;"></div>
            <div class="dsf-schematic__testimonial-author">
              <div class="dsf-schematic__line dsf-schematic__line--xs" style="background: #1F2937; height: 5px;"></div>
              <div class="dsf-schematic__line dsf-schematic__line--xxs" style="height: 4px;"></div>
            </div>
          </div>
          <!-- Image side -->
          <div class="dsf-schematic__testimonial-image"></div>
        </div>
        <!-- Pagination dots -->
        <div class="dsf-schematic__testimonial-dots">
          <span class="dsf-schematic__dot dsf-schematic__dot--active"></span>
          <span class="dsf-schematic__dot"></span>
          <span class="dsf-schematic__dot"></span>
        </div>
      </div>
    </template>
    
    <!-- Product Grid -->
    <template v-else-if="type === 'product-grid'">
      <div class="dsf-schematic__product-grid">
        <div class="dsf-schematic__line dsf-schematic__line--center dsf-schematic__line--sm"></div>
        <div class="dsf-schematic__grid-3">
          <div class="dsf-schematic__product" v-for="i in 3" :key="i">
            <div class="dsf-schematic__product-img"></div>
            <div class="dsf-schematic__line dsf-schematic__line--xs"></div>
            <div class="dsf-schematic__line dsf-schematic__line--xxs"></div>
          </div>
        </div>
      </div>
    </template>
    
    <!-- Category Grid -->
    <template v-else-if="type === 'category-grid'">
      <div class="dsf-schematic__category-grid">
        <div class="dsf-schematic__line dsf-schematic__line--center dsf-schematic__line--sm"></div>
        <div class="dsf-schematic__grid-4">
          <div class="dsf-schematic__cat-card" v-for="i in 4" :key="i">
            <div class="dsf-schematic__line dsf-schematic__line--xxs"></div>
          </div>
        </div>
      </div>
    </template>
    
    <!-- Brand Carousel -->
    <template v-else-if="type === 'brand-carousel'">
      <div class="dsf-schematic__brand-carousel">
        <div class="dsf-schematic__brand-track">
          <div class="dsf-schematic__brand" v-for="i in 5" :key="i"></div>
        </div>
      </div>
    </template>
    
    <!-- CTA Banner -->
    <template v-else-if="type === 'cta-banner'">
      <div class="dsf-schematic__cta-banner">
        <div class="dsf-schematic__cta-content">
          <div class="dsf-schematic__line dsf-schematic__line--md"></div>
          <div class="dsf-schematic__line dsf-schematic__line--sm"></div>
        </div>
        <div class="dsf-schematic__btn dsf-schematic__btn--sm"></div>
      </div>
    </template>
    
    <!-- Newsletter -->
    <template v-else-if="type === 'newsletter'">
      <div class="dsf-schematic__newsletter">
        <div class="dsf-schematic__line dsf-schematic__line--center"></div>
        <div class="dsf-schematic__line dsf-schematic__line--center dsf-schematic__line--xs"></div>
        <div class="dsf-schematic__newsletter-form">
          <div class="dsf-schematic__input"></div>
          <div class="dsf-schematic__btn dsf-schematic__btn--xs"></div>
        </div>
      </div>
    </template>
    
    <!-- Promo Banner -->
    <template v-else-if="type === 'promo-banner'">
      <div class="dsf-schematic__promo-banner">
        <div class="dsf-schematic__promo-image"></div>
        <div class="dsf-schematic__promo-panel">
          <div class="dsf-schematic__line dsf-schematic__line--xxs"></div>
          <div class="dsf-schematic__promo-amount">$100</div>
          <div class="dsf-schematic__promo-divider"></div>
          <div class="dsf-schematic__line dsf-schematic__line--xxs"></div>
          <div class="dsf-schematic__btn dsf-schematic__btn--xs"></div>
        </div>
      </div>
    </template>
    
    <!-- Ecommerce Showcase -->
    <template v-else-if="type === 'ecommerce-showcase'">
      <div class="dsf-schematic__ecommerce-showcase">
        <div class="dsf-schematic__showcase-header">
          <div class="dsf-schematic__line dsf-schematic__line--md"></div>
          <div class="dsf-schematic__line dsf-schematic__line--xxs" style="margin-left: auto;"></div>
        </div>
        <div class="dsf-schematic__showcase-track">
          <div class="dsf-schematic__showcase-item" v-for="i in 5" :key="i">
            <div class="dsf-schematic__showcase-circle"></div>
            <div class="dsf-schematic__line dsf-schematic__line--xxs"></div>
          </div>
        </div>
      </div>
    </template>
    
    <!-- Bento Hero -->
    <template v-else-if="type === 'bento-hero'">
      <div class="dsf-schematic__bento-hero">
        <div class="dsf-schematic__bento-main">
          <div class="dsf-schematic__line dsf-schematic__line--sm"></div>
          <div class="dsf-schematic__bento-search"></div>
        </div>
        <div class="dsf-schematic__bento-box"></div>
        <div class="dsf-schematic__bento-box"></div>
        <div class="dsf-schematic__bento-box"></div>
        <div class="dsf-schematic__bento-box"></div>
        <div class="dsf-schematic__bento-box"></div>
        <div class="dsf-schematic__bento-cta"></div>
      </div>
    </template>

    <!-- Duo Hero -->
    <template v-else-if="type === 'duo-hero'">
      <div class="dsf-schematic__duo-hero">
        <div class="dsf-schematic__duo-panel">
          <div class="dsf-schematic__duo-img"></div>
          <div class="dsf-schematic__duo-content">
            <div class="dsf-schematic__line dsf-schematic__line--sm"></div>
            <div class="dsf-schematic__btn dsf-schematic__btn--xs"></div>
          </div>
        </div>
        <div class="dsf-schematic__duo-panel">
          <div class="dsf-schematic__duo-img"></div>
          <div class="dsf-schematic__duo-content">
            <div class="dsf-schematic__line dsf-schematic__line--sm"></div>
            <div class="dsf-schematic__btn dsf-schematic__btn--xs"></div>
          </div>
        </div>
      </div>
    </template>

    <!-- Featured Promo Banner -->
    <template v-else-if="type === 'featured-promo-banner'">
      <div class="dsf-schematic__featured-promo">
        <!-- Left content area with curve -->
        <div class="dsf-schematic__fp-content-left">
          <div class="dsf-schematic__line dsf-schematic__line--sm" style="background: #374151;"></div>
          <div class="dsf-schematic__fp-divider"></div>
          <div class="dsf-schematic__line dsf-schematic__line--xs" style="background: #6B7280;"></div>
          <div class="dsf-schematic__fp-circle-btn"></div>
        </div>
        <!-- Right image area -->
        <div class="dsf-schematic__fp-image-right"></div>
        <!-- Badge -->
        <div class="dsf-schematic__fp-badge"></div>
      </div>
    </template>

    <!-- Featured Product Banner -->
    <template v-else-if="type === 'featured-product-banner'">
      <div class="dsf-schematic__featured-product">
        <!-- Ribbon (Vertical Flag) on left -->
        <div class="dsf-schematic__fpb-ribbon">
          <svg viewBox="0 0 141 209" preserveAspectRatio="none">
            <path d="M0,0H141V209L70.4,190.357,0,209Z" fill="#2C5F5D" />
          </svg>
          <div class="dsf-schematic__fpb-ribbon-text">
            <span>20%</span>
            <span>OFF</span>
          </div>
        </div>
        
        <!-- Circle in center with product placeholder -->
        <div class="dsf-schematic__fpb-circle"></div>
        <div class="dsf-schematic__fpb-product-icon"></div>
        
        <!-- Content on right -->
        <div class="dsf-schematic__fpb-content">
          <div class="dsf-schematic__line dsf-schematic__line--sm" style="background: #374151; height: 8px;"></div>
          <div class="dsf-schematic__line dsf-schematic__line--xs" style="background: #2C5F5D; height: 6px;"></div>
          <div class="dsf-schematic__line dsf-schematic__line--xxs" style="height: 4px;"></div>
          <div class="dsf-schematic__btn dsf-schematic__btn--xs" style="background: #2C5F5D;"></div>
        </div>
      </div>
    </template>

    <!-- Form Embed -->
    <template v-else-if="type === 'form-embed'">
      <div class="dsf-schematic__form-embed">
        <div class="dsf-schematic__line dsf-schematic__line--md"></div>
        <div class="dsf-schematic__form-input"></div>
        <div class="dsf-schematic__form-input"></div>
        <div class="dsf-schematic__btn dsf-schematic__btn--sm"></div>
      </div>
    </template>

    <!-- Ecommerce Showcase (Redo) -->
    <template v-else-if="type === 'ecommerce-showcase'">
      <div class="dsf-schematic__ecommerce-showcase-v2">
        <div class="dsf-schematic__es-header">
           <div class="dsf-schematic__line dsf-schematic__line--md"></div>
           <div class="dsf-schematic__line dsf-schematic__line--xxs"></div>
        </div>
        <div class="dsf-schematic__es-grid">
           <div class="dsf-schematic__es-card dsf-schematic__es-card--lg"></div>
           <div class="dsf-schematic__es-card" v-for="i in 2" :key="i"></div>
        </div>
      </div>
    </template>
    
    <!-- Default fallback -->
    <template v-else>
      <div class="dsf-schematic__default">
        <component :is="fallbackIcon" :size="28" />
      </div>
    </template>
  </div>
</template>

<script setup>
import { 
  LayoutTemplate, 
  FileText, 
  ShoppingCart, 
  Target,
  Columns,
  Grid3x3,
  Layout,
  MessageCircle,
  ShoppingBag,
  Folder,
  Award,
  Megaphone,
  Mail,
  Image,
  Percent,
  ListChecks
} from 'lucide-vue-next'
import { computed } from 'vue'

const props = defineProps({
  type: {
    type: String,
    required: true
  },
  icon: {
    type: String,
    default: ''
  }
})

const iconMap = {
  'layout-template': LayoutTemplate,
  'file-text': FileText,
  'shopping-cart': ShoppingCart,
  'target': Target,
  'columns': Columns,
  'grid-3x3': Grid3x3,
  'layout': Layout,
  'message-circle': MessageCircle,
  'shopping-bag': ShoppingBag,
  'folder': Folder,
  'award': Award,
  'megaphone': Megaphone,
  'mail': Mail,
  'image': Image,
  'percent': Percent,
  'list-checks': ListChecks,
}

const fallbackIcon = computed(() => iconMap[props.icon] || LayoutTemplate)
</script>

<style scoped>
.dsf-schematic {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(145deg, #f1f5f9 0%, #e2e8f0 100%);
  padding: 12px;
  overflow: hidden;
}

/* Shared elements */
.dsf-schematic__line {
  height: 6px;
  background: #94a3b8;
  border-radius: 3px;
  opacity: 0.7;
}

.dsf-schematic__line--lg { width: 80%; }
.dsf-schematic__line--md { width: 65%; }
.dsf-schematic__line--sm { width: 50%; }
.dsf-schematic__line--xs { width: 75%; }
.dsf-schematic__line--xxs { width: 55%; }
.dsf-schematic__line--center { margin: 0 auto; }

.dsf-schematic__btn {
  width: 40px;
  height: 10px;
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  border-radius: 5px;
}

.dsf-schematic__btn--sm { width: 32px; height: 8px; }
.dsf-schematic__btn--xs { width: 28px; height: 8px; flex-shrink: 0; }

.dsf-schematic__form-embed {
  display: flex;
  flex-direction: column;
  gap: 6px;
  width: 100%;
}

.dsf-schematic__form-input {
  height: 10px;
  border-radius: 999px;
  background: rgba(148, 163, 184, 0.5);
}

/* Hero */
.dsf-schematic__hero {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  width: 100%;
  padding: 10px 0;
}

/* Header Mega Menu */
.dsf-schematic__header-mega {
  display: flex;
  flex-direction: column;
  width: 100%;
  gap: 5px;
}

.dsf-schematic__header-mega-top {
  height: 14px;
  background: rgba(255, 255, 255, 0.8);
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 5px;
}

.dsf-schematic__header-mega-nav {
  height: 12px;
  border-radius: 4px;
  background: #111827;
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 2px;
  padding: 2px;
}

.dsf-schematic__header-mega-item {
  border-radius: 2px;
  background: rgba(255, 255, 255, 0.16);
}

/* Footer Dealers */
.dsf-schematic__footer-dealers {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.dsf-schematic__footer-dealers-cards {
  background: #1f2430;
  border-radius: 4px;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 4px;
  padding: 5px;
  min-height: 20px;
}

.dsf-schematic__footer-dealers-card {
  border-radius: 3px;
  background: rgba(255, 255, 255, 0.22);
  min-height: 14px;
}

.dsf-schematic__footer-dealers-bottom {
  border-radius: 3px;
  background: #414753;
  min-height: 8px;
}

/* Features Grid */
.dsf-schematic__features-grid {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.dsf-schematic__grid-3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 6px;
}

.dsf-schematic__card {
  background: rgba(255, 255, 255, 0.8);
  border-radius: 4px;
  padding: 6px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.dsf-schematic__card-icon {
  width: 14px;
  height: 14px;
  background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
  border-radius: 50%;
}

/* Text + Image */
.dsf-schematic__text-image {
  display: flex;
  gap: 8px;
  width: 100%;
  align-items: center;
}

.dsf-schematic__text-block {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.dsf-schematic__img-block {
  width: 45%;
  aspect-ratio: 4/3;
  background: linear-gradient(135deg, #cbd5e1 0%, #94a3b8 100%);
  border-radius: 4px;
}

/* Testimonials */
.dsf-schematic__testimonials {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 4px;
}

.dsf-schematic__testimonial-card {
  display: grid;
  grid-template-columns: 1fr 1fr;
  width: 100%;
  background: white;
  border-radius: 4px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  min-height: 55px;
}

.dsf-schematic__testimonial-content {
  padding: 6px 8px;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.dsf-schematic__quote-mark {
  font-size: 14px;
  font-weight: bold;
  color: #0F6B8C;
  line-height: 1;
  margin-bottom: 2px;
}

.dsf-schematic__testimonial-author {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-top: auto;
}

.dsf-schematic__testimonial-image {
  background: linear-gradient(135deg, #cbd5e1 0%, #94a3b8 100%);
}

.dsf-schematic__testimonial-dots {
  display: flex;
  gap: 4px;
  justify-content: center;
}

.dsf-schematic__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #D1D5DB;
}

.dsf-schematic__dot--active {
  background: #0F6B8C;
}

/* Product Grid */
.dsf-schematic__product-grid {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.dsf-schematic__product {
  background: rgba(255, 255, 255, 0.8);
  border-radius: 4px;
  padding: 4px;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.dsf-schematic__product-img {
  width: 100%;
  aspect-ratio: 1;
  background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
  border-radius: 3px;
}

/* Category Grid */
.dsf-schematic__category-grid {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.dsf-schematic__grid-4 {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 4px;
}

.dsf-schematic__cat-card {
  aspect-ratio: 1;
  background: linear-gradient(145deg, #e2e8f0 0%, #cbd5e1 100%);
  border-radius: 4px;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: 4px;
}

/* Brand Carousel */
.dsf-schematic__brand-carousel {
  width: 100%;
  overflow: hidden;
  padding: 8px 0;
}

.dsf-schematic__brand-track {
  display: flex;
  gap: 8px;
  justify-content: center;
}

.dsf-schematic__brand {
  width: 28px;
  height: 16px;
  background: rgba(255, 255, 255, 0.8);
  border-radius: 3px;
}

/* CTA Banner */
.dsf-schematic__cta-banner {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
  padding: 12px;
  border-radius: 6px;
}

.dsf-schematic__cta-banner .dsf-schematic__line {
  background: rgba(255, 255, 255, 0.7);
}

.dsf-schematic__cta-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.dsf-schematic__cta-banner .dsf-schematic__btn {
  background: rgba(255, 255, 255, 0.95);
}

/* Newsletter */
.dsf-schematic__newsletter {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.dsf-schematic__newsletter-form {
  display: flex;
  gap: 4px;
  width: 80%;
}

.dsf-schematic__input {
  flex: 1;
  height: 10px;
  background: rgba(255, 255, 255, 0.9);
  border-radius: 5px;
  border: 1px solid #cbd5e1;
}

/* Default */
.dsf-schematic__default {
  color: #64748b;
}

/* Promo Banner */
.dsf-schematic__promo-banner {
  width: 100%;
  display: flex;
  gap: 0;
  height: 100%;
}

.dsf-schematic__promo-image {
  flex: 1;
  background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
  border-radius: 4px 0 0 4px;
}

.dsf-schematic__promo-panel {
  width: 35%;
  background: linear-gradient(135deg, #2C5F5D 0%, #1e4847 100%);
  border-radius: 0 4px 4px 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 3px;
  padding: 6px;
}

.dsf-schematic__promo-panel .dsf-schematic__line {
  background: rgba(255, 255, 255, 0.6);
}

.dsf-schematic__promo-amount {
  font-size: 14px;
  font-weight: 300;
  color: white;
  line-height: 1;
}

.dsf-schematic__promo-divider {
  width: 20px;
  height: 1px;
  background: rgba(255, 255, 255, 0.4);
  margin: 2px 0;
}

.dsf-schematic__promo-panel .dsf-schematic__btn {
  background: rgba(255, 255, 255, 0.9);
}

/* Ecommerce Showcase */
.dsf-schematic__ecommerce-showcase {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.dsf-schematic__showcase-header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.dsf-schematic__showcase-track {
  display: flex;
  gap: 6px;
  justify-content: center;
}

.dsf-schematic__showcase-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
}

.dsf-schematic__showcase-circle {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: linear-gradient(145deg, #e2e8f0 0%, #cbd5e1 100%);
}

/* Bento Hero */
.dsf-schematic__bento-hero {
  width: 100%;
  height: 100%;
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  grid-template-rows: 1fr 1fr;
  gap: 3px;
}

.dsf-schematic__bento-main {
  grid-row: 1 / 3;
  background: linear-gradient(135deg, #d1d5db 0%, #9ca3af 100%);
  border-radius: 3px;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  padding: 6px;
  gap: 3px;
}

.dsf-schematic__bento-main .dsf-schematic__line {
  background: rgba(255, 255, 255, 0.8);
}

.dsf-schematic__bento-search {
  width: 70%;
  height: 6px;
  background: rgba(255, 255, 255, 0.9);
  border-radius: 2px;
}

.dsf-schematic__bento-box {
  background: linear-gradient(145deg, #f5f5f4 0%, #e7e5e4 100%);
  border-radius: 3px;
}

.dsf-schematic__bento-cta {
  background: linear-gradient(135deg, #2C5F5D 0%, #1e4847 100%);
  border-radius: 3px;
}

/* Duo Hero */
.dsf-schematic__duo-hero {
  display: flex;
  width: 100%;
  height: 100%;
  gap: 2px;
}

.dsf-schematic__duo-panel {
  flex: 1;
  position: relative;
  background: linear-gradient(135deg, #d1d5db 0%, #9ca3af 100%);
  border-radius: 3px;
  overflow: hidden;
}

.dsf-schematic__duo-content {
  position: absolute;
  bottom: 6px;
  left: 6px;
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.dsf-schematic__duo-content .dsf-schematic__line {
  background: rgba(255, 255, 255, 0.9);
}

/* Featured Promo Banner */
.dsf-schematic__featured-promo {
  position: relative;
  width: 100%;
  height: 100%;
  border-radius: 4px;
  overflow: hidden;
  display: flex;
}

.dsf-schematic__fp-content-left {
  width: 55%;
  height: 100%;
  background: #E0F2F1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 4px;
  padding: 8px;
  clip-path: polygon(0 0, 85% 0, 100% 50%, 85% 100%, 0 100%);
  z-index: 1;
}

.dsf-schematic__fp-divider {
  width: 20px;
  height: 2px;
  background: #2C5F5D;
}

.dsf-schematic__fp-circle-btn {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #2C5F5D;
  margin-top: 2px;
}

.dsf-schematic__fp-image-right {
  position: absolute;
  top: 0;
  right: 0;
  width: 55%;
  height: 100%;
  background: linear-gradient(135deg, #cbd5e1 0%, #94a3b8 100%);
  z-index: 0;
}

.dsf-schematic__fp-badge {
  position: absolute;
  bottom: 6px;
  right: 6px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #2C5F5D;
  border: 2px solid white;
  z-index: 2;
}

/* Featured Product Banner */
.dsf-schematic__featured-product {
  position: relative;
  width: 100%;
  height: 100%;
  background: linear-gradient(to right, #B2DFDB 0%, #C8E6C9 100%);
  border-radius: 4px;
  overflow: hidden;
  display: flex;
  align-items: center;
}

.dsf-schematic__fpb-ribbon {
  position: absolute;
  top: 0;
  left: 10%;
  width: 22px;
  height: 38px;
  z-index: 2;
}

.dsf-schematic__fpb-ribbon svg {
  width: 100%;
  height: 100%;
  display: block;
}

.dsf-schematic__fpb-ribbon-text {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 85%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 6px;
  font-weight: 700;
  line-height: 1.1;
}

.dsf-schematic__fpb-circle {
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.5);
  z-index: 1;
}

.dsf-schematic__fpb-product-icon {
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  width: 24px;
  height: 24px;
  background: #9CA3AF;
  border-radius: 4px;
  z-index: 2;
}

.dsf-schematic__fpb-content {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 3px;
  z-index: 3;
}

/* Ecommerce Showcase V2 */
.dsf-schematic__ecommerce-showcase-v2 {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.dsf-schematic__es-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  height: 8px;
}

.dsf-schematic__es-grid {
  flex: 1;
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 4px;
  grid-template-rows: 1fr 1fr;
}

.dsf-schematic__es-card {
  background: #f1f5f9;
  border-radius: 3px;
  border: 1px solid #e2e8f0;
}

.dsf-schematic__es-card--lg {
  grid-row: 1 / 3;
  background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
}
</style>
