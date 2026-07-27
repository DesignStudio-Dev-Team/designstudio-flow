<template>
  <section class="dsf-brand-showcase-grid" :style="sectionStyle">
    <div class="dsf-brand-showcase-grid__intro">
      <InlineText v-model="settings.title" tagName="h2" class="dsf-brand-showcase-grid__title" :is-editor="isEditor" placeholder="Build the Backyard of Your Dreams!" />
      <InlineText v-model="settings.description" tagName="p" class="dsf-brand-showcase-grid__description" :is-editor="isEditor" :multiline="true" placeholder="Add a description" />
    </div>
    <div class="dsf-brand-showcase-grid__cards">
      <component
        :is="cardUrl(card) ? 'a' : 'article'"
        v-for="(card, index) in cards"
        :key="`${card.title}-${index}`"
        class="dsf-brand-showcase-grid__card"
        :href="cardUrl(card) || undefined"
        :style="cardStyle(card)"
        @click="handleCardClick"
      >
        <div class="dsf-brand-showcase-grid__copy" :style="{ color: cardTextColor(card) }">
          <InlineText v-model="card.title" tagName="h3" :style="{ color: cardTextColor(card) }" :is-editor="isEditor" placeholder="Brand name" />
          <InlineText v-model="card.subtitle" tagName="p" :style="{ color: cardTextColor(card) }" :is-editor="isEditor" placeholder="Brand message" />
        </div>
        <img v-if="cardImage(card)" class="dsf-brand-showcase-grid__image" :src="cardImage(card)" :alt="card.title || ''">
        <div v-else class="dsf-brand-showcase-grid__image-placeholder" aria-hidden="true"><ImageIcon :size="34" /></div>
        <span v-if="cardUrl(card) || isEditor" class="dsf-brand-showcase-grid__arrow" aria-hidden="true"><ArrowRight :size="31" /></span>
      </component>
      <div v-if="!cards.length && isEditor" class="dsf-brand-showcase-grid__empty">Add up to eight brand cards.</div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { ArrowRight, Image as ImageIcon } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'
import { safePublicUrl } from '../../utils/safeUrl'

const props = defineProps({ settings: { type: Object, default: () => ({}) }, isEditor: { type: Boolean, default: false } })
const clamp = (value, min, max, fallback) => Number.isFinite(Number(value)) ? Math.max(min, Math.min(max, Number(value))) : fallback
const safeHex = (value, fallback) => /^#[0-9a-f]{6}$/i.test(value || '') ? value : fallback
const cards = computed(() => Array.isArray(props.settings?.cards) ? props.settings.cards.slice(0, 8).filter((card) => card && typeof card === 'object') : [])
const sectionStyle = computed(() => ({ backgroundColor: safeHex(props.settings?.backgroundColor, '#FFFFFF'), padding: `${clamp(props.settings?.paddingY, 0, 160, 48)}px ${clamp(props.settings?.paddingX, 0, 120, 20)}px`, '--dsf-brand-card-radius': `${clamp(props.settings?.cardRadius, 0, 48, 20)}px`, '--dsf-brand-card-gap': `${clamp(props.settings?.cardGap, 0, 48, 16)}px` }))
const cardImage = (card) => typeof card?.image === 'string' && card.image.trim() ? safePublicUrl(card.image, '') : ''
const cardUrl = (card) => typeof card?.url === 'string' && card.url.trim() ? safePublicUrl(card.url, '') : ''
const cardTextColor = (card) => safeHex(card?.textColor, '#111111')
const cardStyle = (card) => ({ backgroundColor: safeHex(card?.backgroundColor, '#F3F4F6'), color: cardTextColor(card) })
function handleCardClick(event) { if (props.isEditor) event.preventDefault() }
</script>

<style scoped>
.dsf-brand-showcase-grid { box-sizing: border-box; width: 100%; font-family: var(--dsf-theme-body-font, inherit); container-type: inline-size; }
.dsf-brand-showcase-grid__intro { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 1.5rem 3rem; align-items: end; max-width: 1050px; margin: 0 auto 4rem; }
.dsf-brand-showcase-grid__title { display: block; margin: 0; color: #080808; font-family: var(--dsf-theme-heading-font, inherit); font-size: clamp(2rem, 3.5vw, 3rem); font-weight: 700; line-height: 1.08; overflow-wrap: anywhere; }
.dsf-brand-showcase-grid__description { display: block; margin: 0; color: #151515; font-size: clamp(1rem, 1.8vw, 1.45rem); line-height: 1.35; overflow-wrap: anywhere; }
.dsf-brand-showcase-grid__cards { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: var(--dsf-brand-card-gap); width: min(1600px, 100%); margin: 0 auto; }
.dsf-brand-showcase-grid__card { position: relative; display: flex; flex-direction: column; min-width: 0; min-height: 356px; overflow: hidden; border-radius: var(--dsf-brand-card-radius); color: inherit; text-decoration: none; isolation: isolate; }
.dsf-brand-showcase-grid__copy { position: relative; z-index: 2; padding: 26px 28px; }
.dsf-brand-showcase-grid__copy h3 { margin: 0; color: inherit; font-family: var(--dsf-theme-heading-font, inherit); font-size: clamp(25px, 2vw, 33px); font-weight: 550; line-height: 1.1; letter-spacing: -0.03em; overflow-wrap: anywhere; }
.dsf-brand-showcase-grid__copy p { margin: 17px 0 0; color: inherit; font-size: 16px; line-height: 1.35; overflow-wrap: anywhere; }
.dsf-brand-showcase-grid__image { position: absolute; z-index: 1; right: 0; bottom: 0; left: 0; width: 100%; max-height: 58%; object-fit: contain; object-position: center bottom; transition: transform .2s ease; }
.dsf-brand-showcase-grid__card:hover .dsf-brand-showcase-grid__image { transform: scale(1.025); }
.dsf-brand-showcase-grid__image-placeholder { position: absolute; right: 0; bottom: 0; left: 0; display: grid; place-items: center; height: 43%; color: currentColor; opacity: .18; }
.dsf-brand-showcase-grid__arrow { position: absolute; z-index: 3; right: 16px; bottom: 16px; display: grid; place-items: center; width: 55px; height: 55px; border-radius: 50%; color: #111; background: #fff; box-shadow: 0 3px 12px rgba(0,0,0,.15); }
.dsf-brand-showcase-grid__card:focus-visible { outline: 3px solid var(--dsf-theme-primary, #2563eb); outline-offset: 3px; }
.dsf-brand-showcase-grid__empty { grid-column: 1 / -1; padding: 42px; border: 2px dashed #cbd5e1; color: #64748b; text-align: center; }
@container (max-width: 1100px) { .dsf-brand-showcase-grid__cards { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@container (max-width: 720px) { .dsf-brand-showcase-grid__intro { grid-template-columns: 1fr; margin-bottom: 42px; } .dsf-brand-showcase-grid__cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dsf-brand-showcase-grid__card { min-height: 330px; } }
@container (max-width: 430px) { .dsf-brand-showcase-grid__cards { grid-template-columns: 1fr; } }
</style>
