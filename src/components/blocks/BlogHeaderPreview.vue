<template>
  <header
    class="dsf-blog-header"
    :class="{ 'dsf-blog-header--center': settings.alignment === 'center' }"
    :style="blockStyle"
  >
    <div class="dsf-blog-header__inner" :style="innerStyle">
      <h1
        v-if="settings.showTitle !== false"
        class="dsf-blog-header__title"
        :style="{ color: settings.titleColor || 'var(--dsf-theme-text, inherit)' }"
      >
        {{ archive.title || 'Blog' }}
      </h1>

      <p v-if="settings.showCount !== false && archive.total > 0" class="dsf-blog-header__count">
        {{ archive.total }} {{ archive.total === 1 ? 'article' : 'articles' }}
      </p>

      <!-- descriptionHtml is the archive description sanitized server-side with wp_kses_post. -->
      <div
        v-if="settings.showDescription !== false && archive.descriptionHtml"
        class="dsf-blog-header__description"
        v-html="archive.descriptionHtml"
      ></div>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useBlogContext } from '../../utils/useBlogContext'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const { archive } = useBlogContext()

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 40
  return {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    backgroundColor: props.settings?.backgroundColor || 'transparent',
    color: props.settings?.textColor || 'var(--dsf-theme-text, inherit)',
  }
})

const innerStyle = computed(() => ({ maxWidth: `${Number(props.settings?.maxWidth) || 1100}px` }))
</script>

<style scoped>
.dsf-blog-header {
  width: 100%;
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-blog-header__inner {
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.dsf-blog-header--center .dsf-blog-header__inner {
  text-align: center;
  align-items: center;
}

.dsf-blog-header__title {
  margin: 0;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: clamp(2rem, 4vw, var(--dsf-theme-h1, 2.8rem));
  font-weight: 800;
  letter-spacing: -0.025em;
  line-height: 1.08;
}

.dsf-blog-header__count {
  margin: 0;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
  font-weight: 600;
  opacity: 0.6;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.dsf-blog-header__description {
  max-width: 64ch;
  font-size: var(--dsf-theme-text-base, 1rem);
  line-height: 1.65;
  opacity: 0.85;
}

.dsf-blog-header__description :deep(p) {
  margin: 0 0 0.6rem;
}

.dsf-blog-header__description :deep(p:last-child) {
  margin-bottom: 0;
}
</style>
