<template>
  <section class="dsf-site-login" :style="blockStyle">
    <div class="dsf-site-login__card" :style="cardStyle">
      <!-- Already signed in: a friendly state instead of a useless form. -->
      <template v-if="!isEditor && loggedInUser">
        <img v-if="loggedInUser.avatarUrl" class="dsf-site-login__avatar" :src="loggedInUser.avatarUrl" alt="" />
        <h1 class="dsf-site-login__heading">You're signed in</h1>
        <p class="dsf-site-login__subheading">Logged in as {{ loggedInUser.displayName }}</p>
        <a class="dsf-site-login__submit" :href="logoutUrl || '#'">Log out</a>
      </template>

      <template v-else>
        <h1 class="dsf-site-login__heading">{{ settings.headingText || 'Welcome back' }}</h1>
        <p v-if="settings.subheadingText" class="dsf-site-login__subheading">{{ settings.subheadingText }}</p>

        <!-- Posts to core wp-login.php — authentication never leaves WordPress. -->
        <form
          class="dsf-site-login__form"
          method="post"
          :action="loginAction || undefined"
          @submit="isEditor && $event.preventDefault()"
        >
          <label class="dsf-site-login__field">
            <span>Email or username</span>
            <input type="text" name="log" autocomplete="username" required />
          </label>
          <label class="dsf-site-login__field">
            <span>Password</span>
            <input type="password" name="pwd" autocomplete="current-password" required />
          </label>
          <label v-if="settings.showRemember !== false" class="dsf-site-login__remember">
            <input type="checkbox" name="rememberme" value="forever" />
            <span>Remember me</span>
          </label>
          <input type="hidden" name="redirect_to" :value="redirectTo" />
          <button type="submit" class="dsf-site-login__submit">Sign in</button>
        </form>

        <div v-if="settings.showLinks !== false" class="dsf-site-login__links">
          <a
            :href="lostPasswordUrl || '#'"
            @click="isEditor && $event.preventDefault()"
          >Forgot password?</a>
          <a
            v-if="registerUrl || isEditor"
            :href="registerUrl || '#'"
            @click="isEditor && $event.preventDefault()"
          >Create account</a>
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

const loggedInUser = computed(() => (site.value?.isLoggedIn && site.value?.user ? site.value.user : null))
const loginAction = computed(() => (typeof site.value?.loginAction === 'string' ? site.value.loginAction : ''))
const redirectTo = computed(() => (typeof site.value?.redirectTo === 'string' ? site.value.redirectTo : ''))
const lostPasswordUrl = computed(() => (typeof site.value?.lostPasswordUrl === 'string' ? site.value.lostPasswordUrl : ''))
const registerUrl = computed(() => (typeof site.value?.registerUrl === 'string' ? site.value.registerUrl : ''))
const logoutUrl = computed(() => (typeof site.value?.logoutUrl === 'string' ? site.value.logoutUrl : ''))

const blockStyle = computed(() => {
  const paddingY = getResponsiveValue(props.settings || {}, props.previewMode, 'padding') ?? 48
  return {
    paddingTop: `${paddingY}px`,
    paddingBottom: `${paddingY}px`,
    '--dsf-login-accent': props.settings?.accentColor || 'var(--dsf-theme-primary, #2c5f5d)',
  }
})

const cardStyle = computed(() => ({
  maxWidth: `${Number(props.settings?.maxWidth) || 440}px`,
  backgroundColor: props.settings?.backgroundColor || 'var(--dsf-theme-surface, #fff)',
}))
</script>

<style scoped>
.dsf-site-login {
  width: 100%;
  font-family: var(--dsf-theme-body-font, inherit);
}

.dsf-site-login__card {
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
  margin: 0 auto;
  padding: clamp(1.5rem, 3.5vw, 2.5rem);
  border-radius: 22px;
  border: 1px solid rgba(0, 0, 0, 0.08);
  box-shadow: 0 18px 44px -24px rgba(0, 0, 0, 0.25);
  text-align: center;
}

.dsf-site-login__avatar {
  width: 64px;
  height: 64px;
  margin: 0 auto;
  border-radius: 999px;
}

.dsf-site-login__heading {
  margin: 0;
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: clamp(1.4rem, 2.6vw, 1.9rem);
  font-weight: 800;
  letter-spacing: -0.02em;
}

.dsf-site-login__subheading {
  margin: -0.35rem 0 0;
  opacity: 0.65;
  font-size: var(--dsf-theme-text-sm, 0.9rem);
}

.dsf-site-login__form {
  display: flex;
  flex-direction: column;
  gap: 0.8rem;
  text-align: left;
}

.dsf-site-login__field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.dsf-site-login__field span {
  font-size: var(--dsf-theme-text-sm, 0.82rem);
  font-weight: 600;
}

.dsf-site-login__field input {
  padding: 0.7rem 0.9rem;
  border: 1px solid rgba(0, 0, 0, 0.14);
  border-radius: 12px;
  font: inherit;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.dsf-site-login__field input:focus {
  outline: none;
  border-color: var(--dsf-login-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--dsf-login-accent) 18%, transparent);
}

.dsf-site-login__remember {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  font-size: var(--dsf-theme-text-sm, 0.85rem);
  opacity: 0.8;
}

.dsf-site-login__submit {
  display: inline-block;
  padding: 0.8rem 1.5rem;
  border: 0;
  border-radius: 999px;
  background: var(--dsf-login-accent);
  color: #fff;
  font-weight: 700;
  font-size: 0.95rem;
  text-decoration: none;
  text-align: center;
  cursor: pointer;
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.dsf-site-login__submit:hover {
  opacity: 0.92;
  transform: translateY(-1px);
}

.dsf-site-login__links {
  display: flex;
  justify-content: center;
  gap: 1.25rem;
  font-size: var(--dsf-theme-text-sm, 0.85rem);
}

.dsf-site-login__links a {
  color: var(--dsf-login-accent);
  text-decoration: none;
}

.dsf-site-login__links a:hover {
  text-decoration: underline;
}
</style>
