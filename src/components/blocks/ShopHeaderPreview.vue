<template>
  <header
    class="dsf-shop-header"
    :class="{ 'dsf-shop-header--center': settings.alignment === 'center' }"
    :style="blockStyle"
  >
    <div class="dsf-shop-header__inner" :style="innerStyle">
      <h1
        v-if="settings.showTitle !== false"
        class="dsf-shop-header__title"
        :style="{ color: settings.titleColor || 'var(--dsf-theme-text, inherit)' }"
      >
        {{ archive.title || 'Shop' }}
      </h1>

      <p v-if="settings.showCount !== false && archive.total > 0" class="dsf-shop-header__count">
        {{ archive.total }} {{ archive.total === 1 ? 'product' : 'products' }}
      </p>

      <!-- descriptionHtml is the term description sanitized server-side with wp_kses_post. -->
      <div
        v-if="settings.showDescription !== false && archive.descriptionHtml"
        class="dsf-shop-header__description"
        v-html="archive.descriptionHtml"
      ></div>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useShopContext } from '../../utils/useShopContext'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const { archive } = useShopContext()

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 32
  return {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    backgroundColor: props.settings?.backgroundColor || 'transparent',
    color: props.settings?.textColor || 'var(--dsf-theme-text, inherit)',
  }
})

const innerStyle = computed(() => {
  const maxWidth = Number(props.settings?.maxWidth) || 1200
  return { maxWidth: `${maxWidth}px` }
})
</script>

<style scoped>
.dsf-shop-header {
  width: 100%;
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-shop-header__inner {
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.dsf-shop-header--center .dsf-shop-header__inner {
  text-align: center;
  align-items: center;
}

.dsf-shop-header__title {
  margin: 0;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: clamp(1.9rem, 3.4vw, var(--dsf-theme-h1, 2.5rem));
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1.1;
}

.dsf-shop-header__count {
  margin: 0;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  font-weight: 600;
  opacity: 0.6;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.dsf-shop-header__description {
  max-width: 62ch;
  font-size: var(--dsf-theme-text-base, 1rem);
  line-height: 1.65;
  opacity: 0.85;
}

.dsf-shop-header__description :deep(p) {
  margin: 0 0 0.6rem;
}

.dsf-shop-header__description :deep(p:last-child) {
  margin-bottom: 0;
}
</style>
