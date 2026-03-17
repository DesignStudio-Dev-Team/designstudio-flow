<template>
  <footer class="dsf-footer-dealers" :style="footerStyle">
    <div class="dsf-footer-dealers__top">
      <div class="dsf-footer-dealers__container" :style="containerStyle">
        <div class="dsf-footer-dealers__cards" :style="cardsStyle">
          <article
            v-for="(dealer, index) in dealers"
            :key="`dealer-${index}`"
            class="dsf-footer-dealers__card"
          >
            <div class="dsf-footer-dealers__visual" :style="visualStyle">
              <img
                v-if="dealer.mapImage"
                :src="dealer.mapImage"
                alt="Dealer map"
                class="dsf-footer-dealers__map"
              />
              <div v-else class="dsf-footer-dealers__map-placeholder">Map Image</div>

              <div class="dsf-footer-dealers__store-wrap">
                <img
                  v-if="dealer.photoImage"
                  :src="dealer.photoImage"
                  alt="Dealer showroom"
                  class="dsf-footer-dealers__store"
                />
                <div v-else class="dsf-footer-dealers__store-placeholder">Store Photo</div>
              </div>
            </div>

            <InlineText
              tagName="h3"
              class="dsf-footer-dealers__name"
              v-model="dealer.name"
              :is-editor="isEditor"
              placeholder="Dealer name"
            />
            <InlineText
              tagName="p"
              class="dsf-footer-dealers__line"
              v-model="dealer.addressLine1"
              :is-editor="isEditor"
              placeholder="Address line 1"
            />
            <InlineText
              tagName="p"
              class="dsf-footer-dealers__line"
              v-model="dealer.addressLine2"
              :is-editor="isEditor"
              placeholder="Address line 2"
            />

            <p class="dsf-footer-dealers__contact">
              <Phone :size="15" />
              <InlineText
                tagName="span"
                v-model="dealer.phone"
                :is-editor="isEditor"
                placeholder="Phone number"
              />
            </p>

            <a
              class="dsf-footer-dealers__contact dsf-footer-dealers__contact--link"
              :href="dealer.directionsUrl || '#'"
              @click="preventInEditor"
            >
              <MapPin :size="15" />
              <InlineText
                tagName="span"
                v-model="dealer.directionsLabel"
                :is-editor="isEditor"
                placeholder="Directions label"
                @click.stop
              />
            </a>

            <div class="dsf-footer-dealers__hours">
              <InlineText
                tagName="p"
                class="dsf-footer-dealers__hours-label"
                v-model="dealer.hoursLabel"
                :is-editor="isEditor"
                placeholder="Openingstijden:"
              />
              <div class="dsf-footer-dealers__hours-row">
                <InlineText
                  tagName="span"
                  v-model="dealer.day1"
                  :is-editor="isEditor"
                  placeholder="Day label"
                />
                <InlineText
                  tagName="strong"
                  v-model="dealer.hours1"
                  :is-editor="isEditor"
                  placeholder="08:00 - 17:00"
                />
              </div>
              <div class="dsf-footer-dealers__hours-row">
                <InlineText
                  tagName="span"
                  v-model="dealer.day2"
                  :is-editor="isEditor"
                  placeholder="Day label"
                />
                <InlineText
                  tagName="strong"
                  v-model="dealer.hours2"
                  :is-editor="isEditor"
                  placeholder="08:00 - 16:30"
                />
              </div>
            </div>
          </article>
        </div>
      </div>
    </div>

    <div class="dsf-footer-dealers__bottom">
      <div class="dsf-footer-dealers__container" :style="containerStyle">
        <a
          v-if="settings.showFacebook !== false"
          class="dsf-footer-dealers__social"
          :href="settings.facebookUrl || '#'"
          @click="preventInEditor"
          aria-label="Facebook"
        >
          <span>f</span>
        </a>

        <div class="dsf-footer-dealers__legal">
          <a
            v-for="(link, index) in legalLinks"
            :key="`legal-${index}`"
            :href="link.url || '#'"
            @click="preventInEditor"
          >
            <InlineText
              tagName="span"
              v-model="link.label"
              :is-editor="isEditor"
              placeholder="Legal link"
              @click.stop
            />
          </a>
        </div>

        <p class="dsf-footer-dealers__copyright">
          <InlineText
            tagName="span"
            v-model="settings.copyrightText"
            :is-editor="isEditor"
            placeholder="© 2026 Naam dealer. Alle rechten voorbehouden."
          />
          <InlineText
            v-if="settings.creditText || isEditor"
            tagName="span"
            class="dsf-footer-dealers__credit"
            v-model="settings.creditText"
            :is-editor="isEditor"
            placeholder="| Site door"
          />
        </p>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { computed, watchEffect } from 'vue'
import { MapPin, Phone } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'

const props = defineProps({
  settings: {
    type: Object,
    default: () => ({}),
  },
  isEditor: {
    type: Boolean,
    default: false,
  },
})

const defaultDealers = [
  {
    name: 'Dealer Toonzaal 1 ~ Amsterdam',
    mapImage: '',
    photoImage: '',
    addressLine1: 'Nieuwe Prinsengracht',
    addressLine2: 'Amsterdam, Netherlands 1018ED',
    phone: '0255-555555',
    directionsLabel: 'Routebeschrijving',
    directionsUrl: '#',
    hoursLabel: 'Openingstijden:',
    day1: 'ma - do',
    hours1: '08:00 - 17:00',
    day2: 'vr',
    hours2: '08:00 - 16:30',
  },
  {
    name: 'Dealer Toonzaal 2 ~ Utrecht',
    mapImage: '',
    photoImage: '',
    addressLine1: 'Vleutenseweg, 3532 HP',
    addressLine2: 'Utrecht, Netherlands 1018ED',
    phone: '0255-555555',
    directionsLabel: 'Routebeschrijving',
    directionsUrl: '#',
    hoursLabel: 'Openingstijden:',
    day1: 'ma - do',
    hours1: '07:30 - 17:30',
    day2: 'vr',
    hours2: '08:00 - 16:30',
  },
]

const defaultLegalLinks = [
  { label: 'Privacybeleid', url: '#' },
  { label: 'Juridische disclaimer', url: '#' },
]

function cloneDealers() {
  return defaultDealers.map((dealer) => ({ ...dealer }))
}

watchEffect(() => {
  if (!props.isEditor || !props.settings) return

  if (!Array.isArray(props.settings.dealers) || !props.settings.dealers.length) {
    props.settings.dealers = cloneDealers()
  }

  if (!Array.isArray(props.settings.legalLinks) || !props.settings.legalLinks.length) {
    props.settings.legalLinks = defaultLegalLinks.map((link) => ({ ...link }))
  }

  if (props.settings.copyrightText === undefined || props.settings.copyrightText === null) {
    props.settings.copyrightText = '© 2026 Naam dealer. Alle rechten voorbehouden.'
  }

  if (typeof props.settings.creditText !== 'string') {
    props.settings.creditText = ''
  }
})

const dealers = computed(() => {
  const source = Array.isArray(props.settings?.dealers) ? props.settings.dealers : []
  if (!source.length) return defaultDealers
  return source
})

const legalLinks = computed(() => {
  const source = Array.isArray(props.settings?.legalLinks) ? props.settings.legalLinks : []
  if (!source.length) {
    return defaultLegalLinks
  }
  return source
})

const footerStyle = computed(() => ({
  '--footer-bg': props.settings?.backgroundColor || '#14171b',
  '--footer-bottom-bg': props.settings?.bottomBarColor || '#33363b',
  '--footer-text': props.settings?.textColor || '#f8f9fb',
  '--footer-heading': props.settings?.headingColor || '#ffffff',
  '--footer-accent': props.settings?.accentColor || '#8fce7a',
  '--footer-max-width': `${props.settings?.contentMaxWidth || 1280}px`,
  '--footer-map-height': `${props.settings?.mapHeight || 230}px`,
  '--footer-gap': `${props.settings?.cardGap || 110}px`,
  '--footer-padding-y': `${props.settings?.padding || 72}px`,
  '--footer-bottom-padding': `${props.settings?.bottomPadding || 42}px`,
  '--footer-social-bg': props.settings?.socialBackgroundColor || '#4267b2',
  '--footer-social-color': props.settings?.socialIconColor || '#ffffff',
}))

const containerStyle = computed(() => ({
  paddingLeft: `${props.settings?.contentPaddingX || 24}px`,
  paddingRight: `${props.settings?.contentPaddingX || 24}px`,
}))

const cardsStyle = computed(() => ({
  gap: `var(--footer-gap)`,
}))

const visualStyle = computed(() => ({
  minHeight: `var(--footer-map-height)`,
}))

function preventInEditor(event) {
  if (props.isEditor) {
    event.preventDefault()
  }
}
</script>

<style scoped>
.dsf-footer-dealers {
  width: 100%;
  background: var(--footer-bg);
  color: var(--footer-text);
  font-family: var(--dsf-theme-body-font, 'Inter', sans-serif);
}

.dsf-footer-dealers__top {
  padding-top: var(--footer-padding-y);
  padding-bottom: var(--footer-padding-y);
}

.dsf-footer-dealers__container {
  width: min(var(--footer-max-width), 100%);
  margin: 0 auto;
}

.dsf-footer-dealers__cards {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.dsf-footer-dealers__card {
  max-width: 360px;
}

.dsf-footer-dealers__visual {
  position: relative;
  border-radius: 4px;
  overflow: hidden;
  background: #2b3037;
  margin-bottom: 1rem;
}

.dsf-footer-dealers__map {
  width: 100%;
  height: var(--footer-map-height);
  object-fit: cover;
  display: block;
}

.dsf-footer-dealers__map-placeholder {
  width: 100%;
  height: var(--footer-map-height);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  color: #c8cbd1;
  background: linear-gradient(135deg, #4a5160 0%, #3d434f 100%);
}

.dsf-footer-dealers__store-wrap {
  position: absolute;
  left: 1rem;
  right: 1rem;
  bottom: 1rem;
  border: 6px solid #e9ecf0;
  background: #fff;
}

.dsf-footer-dealers__store {
  width: 100%;
  aspect-ratio: 16/6;
  object-fit: cover;
  display: block;
}

.dsf-footer-dealers__store-placeholder {
  width: 100%;
  aspect-ratio: 16/6;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  color: #6b7280;
}

.dsf-footer-dealers__name {
  margin: 0 0 0.7rem;
  color: var(--footer-heading);
  font-size: 2rem;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-weight: 700;
  line-height: 1.2;
}

.dsf-footer-dealers__line {
  margin: 0;
  font-size: 1.75rem;
  line-height: 1.45;
}

.dsf-footer-dealers__contact {
  margin: 0.45rem 0 0;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--footer-accent);
  text-decoration: none;
  font-size: 1.75rem;
  font-weight: 700;
}

.dsf-footer-dealers__contact--link:hover {
  text-decoration: underline;
}

.dsf-footer-dealers__hours {
  margin-top: 0.9rem;
}

.dsf-footer-dealers__hours-label {
  margin: 0 0 0.35rem;
  font-size: 1.75rem;
}

.dsf-footer-dealers__hours-row {
  display: grid;
  grid-template-columns: 90px 1fr;
  gap: 0.9rem;
  font-size: 1.65rem;
  line-height: 1.35;
}

.dsf-footer-dealers__hours-row strong {
  font-weight: 500;
}

.dsf-footer-dealers__bottom {
  background: var(--footer-bottom-bg);
  padding-top: 1.15rem;
  padding-bottom: var(--footer-bottom-padding);
}

.dsf-footer-dealers__bottom .dsf-footer-dealers__container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.dsf-footer-dealers__social {
  width: 48px;
  height: 48px;
  border-radius: 2px;
  background: var(--footer-social-bg);
  color: var(--footer-social-color);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  font-size: 2rem;
  font-weight: 700;
  line-height: 1;
}

.dsf-footer-dealers__legal {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 0.9rem;
}

.dsf-footer-dealers__legal a {
  color: var(--footer-accent);
  text-decoration: none;
  font-size: 1.45rem;
}

.dsf-footer-dealers__legal a:hover {
  text-decoration: underline;
}

.dsf-footer-dealers__copyright {
  margin: 0;
  font-size: 1.25rem;
  color: #f1f4f8;
  text-align: center;
}

.dsf-footer-dealers__credit {
  margin-left: 0.5rem;
}

@media (max-width: 1280px) {
  .dsf-footer-dealers__name {
    font-size: 1.35rem;
  }

  .dsf-footer-dealers__line,
  .dsf-footer-dealers__contact,
  .dsf-footer-dealers__hours-label,
  .dsf-footer-dealers__hours-row {
    font-size: 1rem;
  }

  .dsf-footer-dealers__legal a {
    font-size: 0.95rem;
  }

  .dsf-footer-dealers__copyright {
    font-size: 0.85rem;
  }
}

@media (max-width: 980px) {
  .dsf-footer-dealers__cards {
    grid-template-columns: 1fr;
    gap: 2rem;
  }

  .dsf-footer-dealers__card {
    max-width: 520px;
  }
}
</style>
