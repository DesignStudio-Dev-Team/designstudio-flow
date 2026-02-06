<template>
  <div 
    class="dsf-block-preview dsf-category-grid-preview"
    :style="previewStyle"
  >
    <!-- Editable Title -->
    <InlineText 
      v-model="settings.title" 
      tagName="h2"
      class="dsf-category-grid-preview__title"
      :style="{ color: settings.titleColor || '#1F2937' }"
      :is-editor="isEditor"
      placeholder="Shop by Category"
    />
    
    <div class="dsf-category-grid-preview__container">
      <!-- Carousel/Grid Wrapper -->
      <div class="dsf-category-grid-preview__items">
        <!-- Category Items -->
        <a 
          v-for="cat in displayCategories" 
          :key="cat.id"
          href="#"
          class="dsf-category-item-preview"
          @click.prevent
        >
          <div class="dsf-category-item-preview__image-wrapper">
            <div class="dsf-category-item-preview__image">
              <img v-if="cat.image" :src="cat.image" :alt="cat.name" />
              <Folder v-else :size="48" style="color: #CBD5E1;" />
            </div>
          </div>
          <span class="dsf-category-item-preview__name">{{ cat.name }}</span>
        </a>

        <!-- Shop All Card -->
        <a 
          v-if="settings.showShopAll"
          href="#" 
          class="dsf-category-item-preview"
          @click.prevent
        >
          <div 
            class="dsf-category-item-preview__image-wrapper"
          >
            <div 
              class="dsf-category-item-preview__shop-all"
              :style="{ backgroundColor: settings.shopAllColor || '#2C5F5D' }"
            >
              <span class="dsf-shop-all-text">Shop All <br> {{ settings.title?.replace('Shop by ', '') || 'Apparel' }}</span>
              <div class="dsf-shop-all-icon">
                <ArrowRight :size="20" />
              </div>
            </div>
          </div>
          <span class="dsf-category-item-preview__name">Shop All</span>
        </a>
      </div>
      
      <!-- Carousel Indicators (Visual Only for design match) -->
      <div v-if="isEditor" class="dsf-category-pagination">
         <span class="dsf-pagination-text">1/3</span>
         <div class="dsf-pagination-arrow">
           <ArrowRightCircle :size="24" />
         </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Folder, ArrowRight, ArrowRightCircle } from 'lucide-vue-next'
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

const wpData = window.dsfEditorData || {}

const previewStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 60
  const paddingX = getResponsiveValue(props.settings || {}, props.previewMode, 'paddingX') ?? 24
  return {
    padding: `${paddingY}px ${paddingX}px`,
    backgroundColor: props.settings?.backgroundColor || '#FFFFFF',
    color: props.settings?.textColor || '#1F2937',
  }
})

const displayCategories = computed(() => {
  const allCategories = wpData.categories || []
  const selectedIds = props.settings?.categoryIds || []
  const limit = props.settings?.limit || 5
  
  let categories = []
  
  if (selectedIds.length > 0) {
    // Sort by selection order to support drag-and-drop ordering
    categories = selectedIds
      .map(id => allCategories.find(c => c.id === id))
      .filter(Boolean)
  } else if (allCategories.length > 0) {
    categories = allCategories
  } else {
    // Demo categories if no WP data
    categories = [
      { id: 1, name: 'Accessories', image: 'https://images.unsplash.com/photo-1523293182086-7651a899d37f?auto=format&fit=crop&w=300&q=80' },
      { id: 2, name: 'Casual Seating', image: 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=300&q=80' },
      { id: 3, name: 'Dining Furniture', image: 'https://images.unsplash.com/photo-1617806118233-18e1de247200?auto=format&fit=crop&w=300&q=80' },
      { id: 4, name: 'Fire Pit Tables', image: 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?auto=format&fit=crop&w=300&q=80' },
      { id: 5, name: 'Side Tables', image: 'https://images.unsplash.com/photo-1532372320572-cda25653a26d?auto=format&fit=crop&w=300&q=80' },
    ]
  }
  
  return categories.slice(0, limit)
})
</script>

<style scoped>
.dsf-category-grid-preview__title {
  font-size: 1.875rem;
  font-weight: 700;
  margin-bottom: 2rem;
  /* color comes from inline style */
}

.dsf-category-grid-preview {
  container-type: inline-size;
}

.dsf-category-grid-preview__container {
  position: relative;
}

.dsf-category-grid-preview__items {
  display: flex;
  gap: 2rem;
  overflow-x: auto;
  padding-bottom: 1.5rem;
  scrollbar-width: none; /* Firefox */
  -ms-overflow-style: none; /* IE/Edge */
  align-items: flex-start;
}

.dsf-category-grid-preview__items::-webkit-scrollbar {
  display: none;
}

.dsf-category-item-preview {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  flex-shrink: 0;
  text-decoration: none;
  color: inherit;
  width: 180px;
  transition: transform 0.2s;
}

.dsf-category-item-preview:hover {
  transform: translateY(-4px);
}

.dsf-category-item-preview__image-wrapper {
  width: 180px;
  height: 180px;
  border-radius: 50%;
  padding: 8px; /* The gap between image and any border if needed, matches design whitespace */
  background: transparent;
}

.dsf-category-item-preview__image {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  overflow: hidden;
  background: var(--dsf-gray-100);
  display: flex;
  align-items: center;
  justify-content: center;
}

.dsf-category-item-preview__image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.dsf-category-item-preview__shop-all {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: white;
  text-align: center;
  padding: 1.5rem;
  position: relative;
}

.dsf-shop-all-text {
  font-weight: 600;
  font-size: 1.125rem;
  line-height: 1.3;
}

.dsf-shop-all-icon {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: rgba(255, 255, 255, 0.2);
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0; /* Hidden in main view based on image provided, or maybe it is the arrow to the right? */
}

/* Pagination indicator (1/3) */
.dsf-category-pagination {
  position: absolute;
  top: -60px;
  right: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--dsf-gray-400);
}

.dsf-pagination-text {
  font-size: 0.875rem;
  font-weight: 500;
}

.dsf-pagination-arrow {
  color: #2C5F5D; /* Matches Shop All color usually */
  cursor: pointer;
}

.dsf-category-item-preview__name {
  text-align: center;
  font-weight: 600;
  font-size: 1rem;
  color: #1F2937;
}

@container (max-width: 1024px) {
  .dsf-category-item-preview {
    width: 160px;
  }

  .dsf-category-item-preview__image-wrapper {
    width: 160px;
    height: 160px;
  }
}

@container (max-width: 768px) {
  .dsf-category-item-preview {
    width: 140px;
  }

  .dsf-category-item-preview__image-wrapper {
    width: 140px;
    height: 140px;
  }
}
</style>
