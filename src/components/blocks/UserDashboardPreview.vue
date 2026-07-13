<template>
  <section class="dsf-user-dashboard" :style="blockStyle">
    <div class="dsf-user-dashboard__inner" :style="innerStyle">
      <!-- Guest state -->
      <div v-if="!user" class="dsf-user-dashboard__guest" :style="cardBg">
        <h2 class="dsf-user-dashboard__guest-heading">{{ settings.loginPromptText || 'Sign in to see your dashboard.' }}</h2>
        <a
          class="dsf-user-dashboard__cta"
          :href="loginUrl || '#'"
          @click="isEditor && $event.preventDefault()"
        >Sign in</a>
      </div>

      <template v-else>
        <header class="dsf-user-dashboard__hero" :style="cardBg">
          <img v-if="user.avatarUrl" class="dsf-user-dashboard__avatar" :src="user.avatarUrl" alt="" />
          <div class="dsf-user-dashboard__hero-text">
            <p class="dsf-user-dashboard__welcome">{{ settings.welcomeText || 'Welcome back,' }}</p>
            <h1 class="dsf-user-dashboard__name">{{ user.displayName }}</h1>
          </div>
          <a
            class="dsf-user-dashboard__logout"
            :href="logoutUrl || '#'"
            @click="isEditor && $event.preventDefault()"
          >Log out</a>
        </header>

        <nav
          v-if="settings.showQuickLinks !== false && quickLinks.length"
          class="dsf-user-dashboard__links"
          aria-label="Account shortcuts"
        >
          <a
            v-for="link in quickLinks"
            :key="link.key"
            :href="link.url || '#'"
            class="dsf-user-dashboard__link"
            @click="isEditor && $event.preventDefault()"
          >
            <span class="dsf-user-dashboard__link-label">{{ link.label }}</span>
            <span class="dsf-user-dashboard__link-arrow" aria-hidden="true">→</span>
          </a>
        </nav>

        <div v-if="settings.showOrders !== false" class="dsf-user-dashboard__orders">
          <h2 class="dsf-user-dashboard__orders-heading">Recent orders</h2>
          <ul v-if="orders.length" class="dsf-user-dashboard__orders-list">
            <li v-for="(order, i) in orders" :key="i">
              <a
                :href="order.url || '#'"
                class="dsf-user-dashboard__order"
                @click="isEditor && $event.preventDefault()"
              >
                <span class="dsf-user-dashboard__order-number">#{{ order.number }}</span>
                <span class="dsf-user-dashboard__order-date">{{ order.date }}</span>
                <span class="dsf-user-dashboard__order-status">{{ order.status }}</span>
                <!-- Order total is Woo price markup, kses'd server-side (build_recent_orders). -->
                <span class="dsf-user-dashboard__order-total" v-html="order.total"></span>
              </a>
            </li>
          </ul>
          <p v-else class="dsf-user-dashboard__empty">
            {{ isEditor ? "The member's recent orders appear here (WooCommerce)." : 'No orders yet.' }}
          </p>
        </div>
      </template>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { getResponsiveValue } from '../../utils/responsiveSettings'
import { useSiteContext } from '../../utils/useSiteContext'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: Boolean,
  blockId: { type: [String, Number], default: '' },
  previewMode: { type: String, default: 'desktop' },
})

const { site } = useSiteContext()

const EDITOR_USER = { displayName: 'Sam Sample', avatarUrl: '' }

const user = computed(() => {
  if (props.isEditor) return EDITOR_USER
  return site.value?.isLoggedIn && site.value?.user ? site.value.user : null
})

const loginUrl = computed(() => (typeof site.value?.loginUrl === 'string' ? site.value.loginUrl : ''))
const logoutUrl = computed(() => (typeof site.value?.logoutUrl === 'string' ? site.value.logoutUrl : ''))

const quickLinks = computed(() => {
  const urls = site.value?.accountUrls && typeof site.value.accountUrls === 'object' ? site.value.accountUrls : {}
  const candidates = [
    { key: 'orders', label: 'Orders', url: urls.orders },
    { key: 'downloads', label: 'Downloads', url: urls.downloads },
    { key: 'addresses', label: 'Addresses', url: urls.addresses },
    { key: 'editAccount', label: 'Account settings', url: urls.editAccount },
  ]
  if (props.isEditor) {
    return candidates.map((c) => ({ ...c, url: c.url || '#' }))
  }
  return candidates.filter((c) => typeof c.url === 'string' && c.url)
})

const EDITOR_ORDERS = [
  { number: '1024', date: 'July 2, 2026', status: 'Completed', total: '$89.00', url: '#' },
  { number: '1019', date: 'June 21, 2026', status: 'Processing', total: '$42.50', url: '#' },
]

const orders = computed(() => {
  if (props.isEditor) return EDITOR_ORDERS
  const raw = Array.isArray(site.value?.recentOrders) ? site.value.recentOrders : []
  return raw.filter((o) => o && typeof o === 'object')
})

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 32
  return {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    '--dsf-dash-accent': props.settings?.accentColor || 'var(--dsf-theme-primary, #2c5f5d)',
  }
})

const innerStyle = computed(() => ({ maxWidth: `${Number(props.settings?.maxWidth) || 1000}px` }))

const cardBg = computed(() => ({
  backgroundColor:
    props.settings?.backgroundColor ||
    'color-mix(in srgb, var(--dsf-dash-accent) 5%, var(--dsf-theme-surface, #fff))',
}))
</script>

<style scoped>
.dsf-user-dashboard {
  width: 100%;
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-user-dashboard__inner {
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
  margin: 0 auto;
}

/* Guest */
.dsf-user-dashboard__guest {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.9rem;
  padding: clamp(1.75rem, 4vw, 3rem);
  border-radius: 22px;
  border: 1px solid rgba(0, 0, 0, 0.08);
  text-align: center;
}

.dsf-user-dashboard__guest-heading {
  margin: 0;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: clamp(1.2rem, 2.4vw, 1.6rem);
  font-weight: 700;
}

.dsf-user-dashboard__cta,
.dsf-user-dashboard__logout {
  display: inline-block;
  padding: 0.6rem 1.4rem;
  border-radius: 999px;
  background: var(--dsf-dash-accent);
  color: #fff;
  font-weight: 700;
  font-size: var(--dsf-theme-text-sm, 0.9rem);
  text-decoration: none;
  transition: opacity 0.15s ease;
}

.dsf-user-dashboard__cta:hover,
.dsf-user-dashboard__logout:hover {
  opacity: 0.9;
}

/* Hero */
.dsf-user-dashboard__hero {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.4rem 1.6rem;
  border-radius: 22px;
  border: 1px solid rgba(0, 0, 0, 0.08);
}

.dsf-user-dashboard__avatar {
  width: 56px;
  height: 56px;
  border-radius: 999px;
}

.dsf-user-dashboard__hero-text {
  min-width: 0;
}

.dsf-user-dashboard__welcome {
  margin: 0;
  font-size: var(--dsf-theme-text-sm, 0.85rem);
  opacity: 0.65;
}

.dsf-user-dashboard__name {
  margin: 0;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: clamp(1.3rem, 2.6vw, 1.8rem);
  font-weight: 800;
  letter-spacing: -0.02em;
}

.dsf-user-dashboard__logout {
  margin-left: auto;
}

/* Quick links */
.dsf-user-dashboard__links {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.7rem;
}

.dsf-user-dashboard__link {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.95rem 1.1rem;
  border: 1px solid rgba(0, 0, 0, 0.09);
  border-radius: 16px;
  color: inherit;
  text-decoration: none;
  font-weight: 600;
  transition: border-color 0.15s ease, transform 0.15s ease;
}

.dsf-user-dashboard__link:hover {
  border-color: var(--dsf-dash-accent);
  transform: translateY(-2px);
}

.dsf-user-dashboard__link-arrow {
  color: var(--dsf-dash-accent);
}

/* Orders */
.dsf-user-dashboard__orders-heading {
  margin: 0 0 0.6rem;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: var(--dsf-theme-h3, 1.2rem);
  font-weight: 800;
}

.dsf-user-dashboard__orders-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.dsf-user-dashboard__order {
  display: grid;
  grid-template-columns: auto 1fr auto auto;
  align-items: center;
  gap: 0.9rem;
  padding: 0.8rem 1rem;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 14px;
  color: inherit;
  text-decoration: none;
  font-size: var(--dsf-theme-text-sm, 0.88rem);
  transition: border-color 0.15s ease;
}

.dsf-user-dashboard__order:hover {
  border-color: var(--dsf-dash-accent);
}

.dsf-user-dashboard__order-number {
  font-weight: 700;
}

.dsf-user-dashboard__order-date {
  opacity: 0.65;
}

.dsf-user-dashboard__order-status {
  padding: 0.15rem 0.6rem;
  border-radius: 999px;
  background: color-mix(in srgb, var(--dsf-dash-accent) 12%, transparent);
  color: var(--dsf-dash-accent);
  font-size: 0.72rem;
  font-weight: 700;
}

.dsf-user-dashboard__order-total {
  font-weight: 700;
}

.dsf-user-dashboard__empty {
  margin: 0;
  opacity: 0.6;
  font-style: italic;
  font-size: var(--dsf-theme-text-sm, 0.875rem);
}

@media (max-width: 560px) {
  .dsf-user-dashboard__order {
    grid-template-columns: auto 1fr;
    grid-auto-rows: auto;
  }
}
</style>
