<template>
  <footer class="dsf-fcom" :class="previewClass" :style="cssVars">
    <!-- Features / trust bar -->
    <div v-if="settings.showFeatures !== false && features.length" class="dsf-fcom__features">
      <div class="dsf-fcom__container dsf-fcom__features-grid">
        <div v-for="(feature, index) in features" :key="`feat-${index}`" class="dsf-fcom__feature">
          <span class="dsf-fcom__feature-icon"><component :is="iconFor(feature.icon)" :size="22" /></span>
          <span class="dsf-fcom__feature-body">
            <strong>{{ feature.title }}</strong>
            <small>{{ feature.description }}</small>
          </span>
        </div>
      </div>
    </div>

    <!-- Main columns -->
    <div class="dsf-fcom__main">
      <div class="dsf-fcom__container dsf-fcom__main-grid">
        <!-- Brand -->
        <div class="dsf-fcom__brand">
          <a class="dsf-fcom__brand-mark" href="#" @click="guard">
            <img v-if="settings.logoImage" :src="settings.logoImage" :alt="settings.logoText || 'Logo'" />
            <InlineText
              v-else
              tagName="span"
              class="dsf-fcom__brand-text"
              v-model="settings.logoText"
              :is-editor="isEditor"
              placeholder="Brand name"
              @click.stop
            />
          </a>
          <InlineText
            tagName="p"
            class="dsf-fcom__brand-desc"
            v-model="settings.brandText"
            :is-editor="isEditor"
            placeholder="A short sentence about your company."
            @click.stop
          />
          <div v-if="socialLinks.length" class="dsf-fcom__social-wrap">
            <InlineText
              tagName="span"
              class="dsf-fcom__social-label"
              v-model="settings.socialLabel"
              :is-editor="isEditor"
              placeholder="Follow us on"
              @click.stop
            />
            <div class="dsf-fcom__socials">
              <a
                v-for="(social, index) in socialLinks"
                :key="`social-${index}`"
                class="dsf-fcom__social"
                :href="url(social.url)"
                :aria-label="social.label || 'Social link'"
                @click="guard"
              >
                <component :is="socialIcon(social.label)" :size="18" />
              </a>
            </div>
          </div>
        </div>

        <!-- Link column 1 -->
        <nav class="dsf-fcom__col" :aria-label="settings.column1Heading || 'Links'">
          <InlineText
            tagName="h4"
            class="dsf-fcom__col-heading"
            v-model="settings.column1Heading"
            :is-editor="isEditor"
            placeholder="Heading"
            @click.stop
          />
          <a v-for="(link, index) in column1Links" :key="`c1-${index}`" class="dsf-fcom__col-link" :href="url(link.url)" @click="guard">{{ link.label }}</a>
        </nav>

        <!-- Link column 2 -->
        <nav class="dsf-fcom__col" :aria-label="settings.column2Heading || 'Links'">
          <InlineText
            tagName="h4"
            class="dsf-fcom__col-heading"
            v-model="settings.column2Heading"
            :is-editor="isEditor"
            placeholder="Heading"
            @click.stop
          />
          <a v-for="(link, index) in column2Links" :key="`c2-${index}`" class="dsf-fcom__col-link" :href="url(link.url)" @click="guard">{{ link.label }}</a>
        </nav>

        <!-- Newsletter -->
        <div v-if="settings.showNewsletter !== false" class="dsf-fcom__news">
          <InlineText
            tagName="h4"
            class="dsf-fcom__col-heading"
            v-model="settings.newsletterHeading"
            :is-editor="isEditor"
            placeholder="Subscribe to our Newsletter"
            @click.stop
          />
          <InlineText
            tagName="p"
            class="dsf-fcom__news-text"
            v-model="settings.newsletterText"
            :is-editor="isEditor"
            placeholder="Sign up for the latest news and offers."
            @click.stop
          />

          <!-- Embedded DSF or Gravity form (reuses the Form block renderer). -->
          <div v-if="newsletterSource !== 'inline'" class="dsf-fcom__news-embed">
            <FormEmbedPreview :settings="newsletterFormSettings" :is-editor="isEditor" :preview-mode="previewMode" />
          </div>

          <!-- Default: a simple email sign-up field. -->
          <form v-else class="dsf-fcom__news-form" :action="formAction" method="post" @submit="onSubmit">
            <input
              type="email"
              name="email"
              class="dsf-fcom__news-input"
              :placeholder="settings.newsletterPlaceholder || 'Enter your email address'"
              aria-label="Email address"
              required
            />
            <button type="submit" class="dsf-fcom__news-btn">{{ settings.newsletterButton || 'Subscribe' }}</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Bottom bar -->
    <div class="dsf-fcom__bottom">
      <div class="dsf-fcom__container dsf-fcom__bottom-grid">
        <div class="dsf-fcom__bottom-left">
          <template v-if="settings.showLocale !== false">
            <span class="dsf-fcom__locale"><Globe :size="16" /> {{ settings.localeText || 'English' }} <ChevronDown :size="14" /></span>
            <span class="dsf-fcom__locale"><DollarSign :size="16" /> {{ settings.currencyText || 'USD' }} <ChevronDown :size="14" /></span>
          </template>
        </div>

        <InlineText
          tagName="span"
          class="dsf-fcom__copyright"
          v-model="settings.copyrightText"
          :is-editor="isEditor"
          placeholder="© 2025 Your Company. All rights reserved."
          @click.stop
        />

        <div class="dsf-fcom__bottom-right">
          <div v-if="settings.showPayments !== false && payments.length" class="dsf-fcom__payments">
            <span v-for="(pay, index) in payments" :key="`pay-${index}`" class="dsf-fcom__pay">
              <img v-if="pay.logo" :src="pay.logo" :alt="pay.name || 'Payment method'" />
              <span v-else>{{ pay.name }}</span>
            </span>
          </div>
        </div>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { computed } from 'vue'
import { ChevronDown, DollarSign, Facebook, Github, Globe, Instagram, Linkedin, Twitter, Youtube } from 'lucide-vue-next'
import InlineText from '../common/InlineText.vue'
import FormEmbedPreview from './FormEmbedPreview.vue'
import { iconFor } from '../../utils/landingIcons'
import { safePublicUrl } from '../../utils/safeUrl'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: { type: Boolean, default: false },
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const SOCIAL_ICONS = {
  facebook: Facebook,
  twitter: Twitter,
  x: Twitter,
  instagram: Instagram,
  linkedin: Linkedin,
  youtube: Youtube,
  github: Github,
}

const listOf = (key) => computed(() => (Array.isArray(props.settings?.[key]) ? props.settings[key] : []))
const features = listOf('features')
const socialLinks = listOf('socialLinks')
const column1Links = listOf('column1Links')
const column2Links = listOf('column2Links')
const payments = listOf('payments')

const previewClass = computed(() => ({
  'preview-tablet': props.isEditor && props.previewMode === 'tablet',
  'preview-mobile': props.isEditor && props.previewMode === 'mobile',
}))

const cssVars = computed(() => ({
  '--fcom-bg': props.settings?.background || '#ffffff',
  '--fcom-text': props.settings?.textColor || '#64748b',
  '--fcom-heading': props.settings?.headingColor || '#0f172a',
  '--fcom-link': props.settings?.linkColor || '#475569',
  '--fcom-accent': props.settings?.accentColor || '#4f46e5',
  '--fcom-border': props.settings?.borderColor || '#e2e8f0',
  '--fcom-bottom-bg': props.settings?.bottomBackground || '#ffffff',
}))

// Newsletter can be a simple email field, a DSF form, or an embedded shortcode
// (e.g. a Gravity Form). The last two reuse the Form block renderer, driven by a
// settings object the server fills with the rendered form HTML.
const newsletterSource = computed(() => props.settings?.newsletterSource || 'inline')
const newsletterFormSettings = computed(() => ({
  formId: newsletterSource.value === 'dsf' ? (props.settings?.newsletterFormId || '') : '',
  renderedFormHtml: props.settings?.newsletterRenderedFormHtml || '',
  formAlignment: 'left',
  formMaxWidth: 400,
  showTitle: false,
  marginY: 0,
}))

// In the editor the form never submits; on the frontend it only submits when a
// real action URL is set (otherwise it's a decorative sign-up field).
const formAction = computed(() => (props.isEditor ? undefined : (props.settings?.newsletterAction ? safePublicUrl(props.settings.newsletterAction) : undefined)))

function url(value) {
  return safePublicUrl(value || '#')
}

function guard(event) {
  if (props.isEditor) event.preventDefault()
}

function onSubmit(event) {
  if (props.isEditor || !props.settings?.newsletterAction) {
    event.preventDefault()
  }
}

function socialIcon(label) {
  const key = String(label || '').toLowerCase().replace(/[^a-z]/g, '')
  return SOCIAL_ICONS[key] || Globe
}
</script>

<style scoped>
.dsf-fcom {
  --fcom-bg: #ffffff;
  --fcom-text: #64748b;
  --fcom-heading: #0f172a;
  --fcom-link: #475569;
  --fcom-accent: #4f46e5;
  --fcom-border: #e2e8f0;
  --fcom-bottom-bg: #ffffff;
  width: 100%;
  background: var(--fcom-bg);
  color: var(--fcom-text);
  font-family: var(--dsf-theme-body-font, 'Inter', sans-serif);
}

.dsf-fcom__container {
  width: min(var(--dsf-theme-container-width, 1200px), 100%);
  margin: 0 auto;
  padding: 0 1.5rem;
}

/* Features bar */
.dsf-fcom__features {
  border-bottom: 1px solid var(--fcom-border);
}

.dsf-fcom__features-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1.5rem;
  padding-top: 1.5rem;
  padding-bottom: 1.5rem;
}

.dsf-fcom__feature {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.dsf-fcom__feature-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  flex: 0 0 auto;
  border-radius: 999px;
  background: rgba(100, 116, 139, 0.1);
  color: var(--fcom-heading);
}

.dsf-fcom__feature-body {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.dsf-fcom__feature-body strong {
  color: var(--fcom-heading);
  font-size: 0.98rem;
  font-weight: 700;
}

.dsf-fcom__feature-body small {
  font-size: 0.85rem;
}

/* Main columns */
.dsf-fcom__main-grid {
  display: grid;
  grid-template-columns: 1.6fr 1fr 1fr 1.4fr;
  gap: 2rem;
  padding-top: 3rem;
  padding-bottom: 3rem;
}

.dsf-fcom__brand-mark {
  display: inline-flex;
  align-items: center;
  text-decoration: none;
  color: var(--fcom-heading);
}

.dsf-fcom__brand-mark img {
  max-height: 40px;
  width: auto;
  object-fit: contain;
}

.dsf-fcom__brand-text {
  font-family: var(--dsf-theme-heading-font, 'Inter', sans-serif);
  font-size: 1.5rem;
  font-weight: 800;
}

.dsf-fcom__brand-desc {
  margin: 1rem 0 0;
  max-width: 22rem;
  font-size: 0.92rem;
  line-height: 1.6;
}

.dsf-fcom__social-wrap {
  margin-top: 1.75rem;
}

.dsf-fcom__social-label {
  display: block;
  margin-bottom: 0.6rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--fcom-link);
}

.dsf-fcom__socials {
  display: flex;
  gap: 0.6rem;
}

.dsf-fcom__social {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 999px;
  background: rgba(100, 116, 139, 0.1);
  color: var(--fcom-link);
  text-decoration: none;
  transition: background 0.15s ease, color 0.15s ease;
}

.dsf-fcom__social:hover {
  background: var(--fcom-accent);
  color: #fff;
}

/* Link columns */
.dsf-fcom__col {
  display: flex;
  flex-direction: column;
}

.dsf-fcom__col-heading {
  margin: 0 0 1.1rem;
  color: var(--fcom-heading);
  font-size: 1.05rem;
  font-weight: 700;
}

.dsf-fcom__col-link {
  margin-bottom: 0.7rem;
  color: var(--fcom-link);
  font-size: 0.92rem;
  text-decoration: none;
  transition: color 0.15s ease;
}

.dsf-fcom__col-link:hover {
  color: var(--fcom-accent);
}

/* Newsletter */
.dsf-fcom__news-text {
  margin: 0 0 1.1rem;
  font-size: 0.92rem;
  line-height: 1.6;
}

.dsf-fcom__news-form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-width: 22rem;
}

.dsf-fcom__news-input {
  width: 100%;
  padding: 0.7rem 0.9rem;
  border: 1px solid var(--fcom-border);
  border-radius: 8px;
  background: #fff;
  color: var(--fcom-heading);
  font-size: 0.9rem;
}

.dsf-fcom__news-input:focus {
  outline: none;
  border-color: var(--fcom-accent);
}

.dsf-fcom__news-btn {
  width: 100%;
  padding: 0.7rem 1rem;
  border: none;
  border-radius: 8px;
  background: var(--fcom-accent);
  color: #fff;
  font-size: 0.92rem;
  font-weight: 600;
  cursor: pointer;
}

/* Bottom bar */
.dsf-fcom__bottom {
  border-top: 1px solid var(--fcom-border);
  background: var(--fcom-bottom-bg);
}

.dsf-fcom__bottom-grid {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: 1rem;
  padding-top: 1.25rem;
  padding-bottom: 1.25rem;
}

.dsf-fcom__bottom-left {
  display: flex;
  align-items: center;
  gap: 1.25rem;
}

.dsf-fcom__locale {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.88rem;
  color: var(--fcom-link);
  cursor: default;
}

.dsf-fcom__copyright {
  font-size: 0.88rem;
  text-align: center;
}

.dsf-fcom__bottom-right {
  display: flex;
  justify-content: flex-end;
}

.dsf-fcom__payments {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.dsf-fcom__pay {
  display: inline-flex;
  align-items: center;
  color: var(--fcom-link);
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.dsf-fcom__pay img {
  height: 22px;
  width: auto;
  object-fit: contain;
}

/* Responsive */
@media (max-width: 980px) {
  .dsf-fcom__main-grid {
    grid-template-columns: 1fr 1fr;
    gap: 2rem 1.5rem;
  }

  .dsf-fcom__brand,
  .dsf-fcom__news {
    grid-column: 1 / -1;
  }

  .dsf-fcom__features-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .dsf-fcom__bottom-grid {
    grid-template-columns: 1fr;
    justify-items: center;
    text-align: center;
  }

  .dsf-fcom__bottom-left,
  .dsf-fcom__bottom-right {
    justify-content: center;
  }

  .dsf-fcom__payments {
    justify-content: center;
  }
}

@media (max-width: 560px) {
  .dsf-fcom__main-grid,
  .dsf-fcom__features-grid {
    grid-template-columns: 1fr;
  }
}

.dsf-fcom.preview-mobile .dsf-fcom__main-grid,
.dsf-fcom.preview-mobile .dsf-fcom__features-grid {
  grid-template-columns: 1fr;
}

.dsf-fcom.preview-tablet .dsf-fcom__main-grid {
  grid-template-columns: 1fr 1fr;
}

.dsf-fcom.preview-tablet .dsf-fcom__features-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.dsf-fcom.preview-mobile .dsf-fcom__bottom-grid,
.dsf-fcom.preview-tablet .dsf-fcom__bottom-grid {
  grid-template-columns: 1fr;
  justify-items: center;
  text-align: center;
}
</style>
