<template>
  <div :class="mobile ? 'dsf-showcase-header__mobile-panel' : 'dsf-showcase-header__panel-shell'">
    <div :class="mobile ? null : 'dsf-showcase-header__panel'">
      <div :class="mobile ? 'dsf-showcase-header__mobile-intro' : 'dsf-showcase-header__intro'">
        <h2>{{ panel.introTitle }}</h2><p>{{ panel.introText }}</p>
        <a v-if="panel.buttonText" :href="url(panel.buttonUrl)" @click="guard">{{ panel.buttonText }}</a>
      </div>
      <div :class="mobile ? null : 'dsf-showcase-header__panel-main'">
        <div class="dsf-showcase-header__cards">
          <a v-for="(card, index) in cards" :key="index" :href="url(card.url)" @click="guard">
            <img v-if="card.image" :src="card.image" :alt="card.title || ''" /><span><small>{{ card.eyebrow }}</small><strong>{{ card.title }}</strong></span>
          </a>
        </div>
        <a v-if="panel.accentText" class="dsf-showcase-header__accent-link" :href="url(panel.accentUrl)" @click="guard">{{ panel.accentText }}</a>
      </div>
      <a v-if="panel.promoImage || panel.promoTitle" :class="mobile ? 'dsf-showcase-header__mobile-promo' : 'dsf-showcase-header__panel-promo'" :href="url(panel.promoUrl)" @click="guard">
        <img v-if="panel.promoImage" :src="panel.promoImage" :alt="panel.promoTitle || ''" /><span><strong>{{ panel.promoTitle }}</strong><small>{{ panel.promoSubtitle }}</small></span>
      </a>
    </div>
  </div>
</template>
<script setup>
import { computed } from 'vue'
import { safePublicUrl } from '../../utils/safeUrl'
const props = defineProps({ panel: { type: Object, default: () => ({}) }, isEditor: Boolean, mobile: Boolean })
const emit = defineEmits(['navigate'])
const cards = computed(() => Array.isArray(props.panel.cards) ? props.panel.cards.slice(0, 6) : [])
const url = (value) => safePublicUrl(value)
function guard(event) { if (props.isEditor) event.preventDefault(); emit('navigate', event) }
</script>
