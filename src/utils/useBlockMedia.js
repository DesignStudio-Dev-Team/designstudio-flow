import { computed, unref } from 'vue'

const FILE_RE = /\.(mp4|webm|ogg|ogv)(\?.*)?$/

/**
 * Shared image / video media resolver for the landing blocks.
 *
 * Convention (mirrors BentoHeroPreview): a block stores `${...}MediaType`
 * ('mockup' | 'image' | 'video'), an image URL, and a video URL (file or
 * YouTube/Vimeo). When the type is 'mockup' (default) the block renders its
 * built-in illustration instead.
 *
 * @param {Function|Object} getSettings  reactive settings object or a getter for it
 * @param {Object} keys                  { typeKey, imageKey, videoKey }
 */
export function useBlockMedia(getSettings, keys = {}) {
  const { typeKey = 'mediaType', imageKey = 'mediaImage', videoKey = 'mediaVideo' } = keys
  const read = () => (typeof getSettings === 'function' ? getSettings() : unref(getSettings)) || {}

  const mode = computed(() => {
    const value = read()[typeKey]
    return value === 'image' || value === 'video' ? value : 'mockup'
  })

  const imageUrl = computed(() => (read()[imageKey] || '').trim())
  const videoUrl = computed(() => (read()[videoKey] || '').trim())

  const videoFileUrl = computed(() => (FILE_RE.test(videoUrl.value.toLowerCase()) ? videoUrl.value : ''))

  const videoFileType = computed(() => {
    const url = videoUrl.value.toLowerCase()
    if (url.includes('.webm')) return 'video/webm'
    if (url.includes('.ogg') || url.includes('.ogv')) return 'video/ogg'
    return 'video/mp4'
  })

  const videoEmbedUrl = computed(() => {
    const url = videoUrl.value
    if (!url || videoFileUrl.value) return ''
    if (url.includes('/embed/') || url.includes('player.vimeo.com')) return url

    const ytShort = url.match(/youtu\.be\/([^?&]+)/)
    if (ytShort) return `https://www.youtube.com/embed/${ytShort[1]}?autoplay=1&mute=1&loop=1&playlist=${ytShort[1]}&controls=0`
    const ytWatch = url.match(/[?&]v=([^&]+)/)
    if (ytWatch) return `https://www.youtube.com/embed/${ytWatch[1]}?autoplay=1&mute=1&loop=1&playlist=${ytWatch[1]}&controls=0`
    const ytShorts = url.match(/shorts\/([^?&]+)/)
    if (ytShorts) return `https://www.youtube.com/embed/${ytShorts[1]}`

    const vimeo = url.match(/vimeo\.com\/(\d+)/)
    if (vimeo) return `https://player.vimeo.com/video/${vimeo[1]}?autoplay=1&muted=1&loop=1&background=1`

    return ''
  })

  const hasMedia = computed(() => {
    if (mode.value === 'image') return !!imageUrl.value
    if (mode.value === 'video') return !!(videoFileUrl.value || videoEmbedUrl.value)
    return false
  })

  return { mode, imageUrl, videoUrl, videoFileUrl, videoFileType, videoEmbedUrl, hasMedia }
}
