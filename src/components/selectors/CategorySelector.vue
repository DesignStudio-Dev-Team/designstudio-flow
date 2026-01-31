<template>
  <div class="dsf-category-selector">
    <div class="dsf-setting-card">
      <div class="dsf-setting-card__title">Select Category</div>
      <p class="dsf-setting-card__desc">Products from this category will be displayed automatically</p>
      
      <select 
        class="dsf-input dsf-mt-3"
        :value="value"
        @change="$emit('update', parseInt($event.target.value))"
      >
        <option :value="0">Select a category...</option>
        <option 
          v-for="cat in categories" 
          :key="cat.id" 
          :value="cat.id"
        >
          {{ cat.name }} ({{ cat.count }})
        </option>
      </select>
      
      <div v-if="selectedCategory" class="dsf-mt-3 dsf-flex dsf-items-center dsf-gap-2">
        <Check :size="16" class="dsf-text-success-500" style="color: var(--dsf-success-500);" />
        <span style="color: var(--dsf-gray-600); font-size: var(--dsf-text-sm);">
          Showing products from: <strong>{{ selectedCategory.name }}</strong>
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Check } from 'lucide-vue-next'

const props = defineProps({
  value: Number,
})

defineEmits(['update'])

const categories = computed(() => {
  return window.dsfEditorData?.categories || []
})

const selectedCategory = computed(() => {
  if (!props.value) return null
  return categories.value.find(c => c.id === props.value)
})
</script>
