<template>
  <div class="dsf-block-media" :class="{ 'dsf-block-media--filled': hasMedia }">
    <iframe
      v-if="mode === 'video' && videoEmbedUrl"
      :src="videoEmbedUrl"
      class="dsf-block-media__el dsf-block-media__el--embed"
      frameborder="0"
      allow="autoplay; fullscreen; picture-in-picture"
      allowfullscreen
      :title="alt || 'Video'"
    />
    <video
      v-else-if="mode === 'video' && videoFileUrl"
      class="dsf-block-media__el"
      autoplay
      muted
      loop
      playsinline
      :poster="imageUrl || ''"
    >
      <source :src="videoFileUrl" :type="videoFileType" />
    </video>
    <img
      v-else-if="mode === 'image' && imageUrl"
      :src="imageUrl"
      :alt="alt"
      class="dsf-block-media__el"
      loading="lazy"
    />
    <slot v-else />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useBlockMedia } from '../../utils/useBlockMedia'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  typeKey: { type: String, default: 'mediaType' },
  imageKey: { type: String, default: 'mediaImage' },
  videoKey: { type: String, default: 'mediaVideo' },
  alt: { type: String, default: '' },
})

const { mode, imageUrl, videoFileUrl, videoFileType, videoEmbedUrl, hasMedia } = useBlockMedia(
  () => props.settings,
  computed(() => ({ typeKey: props.typeKey, imageKey: props.imageKey, videoKey: props.videoKey })).value
)
</script>

<style scoped>
.dsf-block-media { position: relative; width: 100%; height: 100%; }
.dsf-block-media--filled { overflow: hidden; }
.dsf-block-media__el { display: block; width: 100%; height: 100%; object-fit: cover; border: 0; }
.dsf-block-media__el--embed { aspect-ratio: 16 / 9; height: 100%; min-height: 100%; }
</style>
