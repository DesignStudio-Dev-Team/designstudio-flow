<template>
  <video
    v-if="mode === 'video' && videoSrc"
    class="dsf-media-visual__el"
    :class="$attrs.class"
    :style="$attrs.style"
    autoplay
    muted
    loop
    playsinline
    :poster="imageSrc || undefined"
    aria-hidden="true"
  >
    <source :src="videoSrc" :type="videoType" />
  </video>
  <img
    v-else-if="mode !== 'video' && imageSrc"
    class="dsf-media-visual__el"
    :class="$attrs.class"
    :style="$attrs.style"
    :src="imageSrc"
    :alt="alt"
    loading="lazy"
    decoding="async"
  />
  <slot v-else />
</template>

<script setup>
import { computed } from 'vue'
import { safePublicUrl } from '../../utils/safeUrl'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  mode: { type: String, default: 'image' },
  image: { type: String, default: '' },
  video: { type: String, default: '' },
  alt: { type: String, default: '' },
})

const imageSrc = computed(() => safePublicUrl(props.image, ''))
const rawVideo = computed(() => safePublicUrl(props.video, ''))
const videoSrc = computed(() => (/\.(mp4|webm|ogg|ogv)(?:\?.*)?$/i.test(rawVideo.value) ? rawVideo.value : ''))
const videoType = computed(() => {
  const value = videoSrc.value.toLowerCase()
  if (value.includes('.webm')) return 'video/webm'
  if (value.includes('.ogg') || value.includes('.ogv')) return 'video/ogg'
  return 'video/mp4'
})
</script>

<style scoped>
.dsf-media-visual__el {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
