<template>
  <div class="dsf-block-preview dsf-form-with-content" :style="blockStyle">
    <!-- Section header: title + optional divider -->
    <div
      v-if="isEditor || settings.sectionTitle || settings.showDivider"
      class="dsf-form-with-content__header"
    >
      <InlineText
        v-if="isEditor || settings.sectionTitle"
        tagName="h2"
        class="dsf-form-with-content__section-title"
        :style="{ color: settings.titleColor || '#1F2937' }"
        v-model="settings.sectionTitle"
        :is-editor="isEditor"
        placeholder="Enter Section Title"
      />
      <hr
        v-if="settings.showDivider"
        class="dsf-form-with-content__divider"
        :style="{ borderColor: settings.dividerColor || '#E5E7EB' }"
      />
    </div>

    <!-- Two-column grid -->
    <div
      class="dsf-form-with-content__grid"
      :class="
        formSide === 'left'
          ? 'dsf-form-with-content__grid--form-left'
          : 'dsf-form-with-content__grid--form-right'
      "
    >
      <!-- Content column -->
      <div
        class="dsf-form-with-content__col dsf-form-with-content__col--content"
        :style="contentColStyle"
      >
        <!-- Rich text (inline-editable in editor mode) -->
        <div
          ref="contentEditor"
          class="dsf-form-with-content__content"
          :class="{ 'dsf-form-with-content__content--editable': isEditor }"
          :style="contentTextStyle"
          :contenteditable="isEditor"
          spellcheck="true"
          @blur="onContentBlur"
          @paste="onContentPaste"
        />

        <!-- Media wrapper: logo + image or video -->
        <div
          v-if="showImage || showVideoFile || showVideoEmbed"
          class="dsf-form-with-content__media-wrap"
        >
          <img
            v-if="settings.logo"
            :src="settings.logo"
            class="dsf-form-with-content__logo"
            alt="Logo"
          />

          <!-- Image -->
          <img
            v-if="showImage"
            :src="settings.image"
            class="dsf-form-with-content__image"
            alt=""
          />

          <!-- Hosted video file (mp4 / webm) -->
          <div
            v-else-if="showVideoFile"
            class="dsf-form-with-content__video-wrap"
          >
            <video
              class="dsf-form-with-content__video dsf-form-with-content__video--file"
              autoplay
              muted
              loop
            >
              <source :src="settings.videoFile" :type="videoFileType" />
            </video>
          </div>

          <!-- Embed iframe (YouTube / Vimeo) -->
          <div
            v-else-if="showVideoEmbed"
            class="dsf-form-with-content__video-wrap"
          >
            <iframe
              :src="videoEmbedUrl"
              class="dsf-form-with-content__video"
              frameborder="0"
              allow="autoplay; fullscreen; picture-in-picture"
              allowfullscreen
            />
          </div>
        </div>

        <!-- Editor placeholder when embed URL isn't recognised -->
        <div
          v-else-if="settings.video && isEditor && !isImageMode.value"
          class="dsf-form-with-content__video-placeholder"
        >
          <span>Video: {{ settings.video }}</span>
        </div>
      </div>

      <!-- Form column -->
      <div
        class="dsf-form-with-content__col dsf-form-with-content__col--form"
        :style="formColStyle"
      >
        <template v-if="isDsfFormSource">
          <!-- Editor: show badge placeholder -->
          <div v-if="isEditor" class="dsf-form-with-content__form-placeholder">
            <div class="dsf-form-with-content__badge">
              DesignStudio Flow Form
            </div>
            <div class="dsf-form-with-content__form-name">
              {{ selectedFormTitle }}
            </div>
            <p class="dsf-form-with-content__hint">
              The live form will render here on the frontend.
            </p>
            <code class="dsf-form-with-content__code">{{
              shortcodeLabel
            }}</code>
          </div>

          <!-- Frontend: render the form HTML -->
          <div
            v-else
            ref="frontendRoot"
            class="dsf-form-with-content__form-frontend"
            data-dsf-form-with-content-form
          >
            <div v-if="renderedHtml" v-html="renderedHtml" />
            <div v-else class="dsf-form-with-content__empty">
              {{
                normalizedFormId
                  ? "Form preview is loading."
                  : "Select a form in the block settings."
              }}
            </div>
          </div>
        </template>

        <div
          v-else
          ref="frontendRoot"
          class="dsf-form-with-content__form-frontend"
          data-dsf-form-with-content-form
        >
          <div v-if="customFormHtml" v-html="customFormHtml" />
          <div v-else class="dsf-form-with-content__empty">
            Add a shortcode or embed code in the block settings.
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  computed,
  ref,
  onBeforeUnmount,
  onMounted,
  onUpdated,
  inject,
  nextTick,
  watch,
} from "vue";
import InlineText from "../common/InlineText.vue";

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  isEditor: { type: Boolean, default: false },
  previewMode: { type: String, default: "desktop" },
});

// Set to 'snapshot' by the offscreen snapshot generator so embed scripts don't
// execute and leak DOM into document.body when we are only capturing innerHTML.
const renderMode = inject("dsfRenderMode", "live");

const frontendRoot = ref(null);
const contentEditor = ref(null);
const executedEmbedScriptSignature = ref("");
let gravityPageLoadedHandler = null;
let gravityNativePageChangeHandler = null;
let pendingScriptTimeoutId = null;
let isUnmounted = false;
const gravityOverrideStyleId = "dsf-form-with-content-gravity-overrides";

const defaultContent =
  "<p><b>Your dream backyard starts here!</b></p><p>Fill out the form and we'll be in touch as soon as possible.</p>";

const formSide = computed(() => props.settings?.formSide || "right");

const formSource = computed(() => props.settings?.formSource || "dsf");

const isDsfFormSource = computed(() => formSource.value !== "embed");

const blockStyle = computed(() => ({
  backgroundColor: props.settings?.backgroundColor || "#FFFFFF",
  padding: `${props.settings?.padding ?? 60}px ${props.settings?.paddingX ?? 24}px`,
}));

const contentColStyle = computed(() => {
  const bg = props.settings?.contentBg;
  return bg ? { backgroundColor: bg } : {};
});

const contentTextStyle = computed(() => ({
  color: props.settings?.textColor || "#1F2937",
  "--dsf-form-content-font-size": `${props.settings?.contentFontSize ?? 24}px`,
}));

const formColStyle = computed(() => {
  const bg = props.settings?.formBg;
  return bg ? { backgroundColor: bg } : {};
});

// Whether the block is in image mode (default 'video' for backwards compat)
const isImageMode = computed(() => props.settings?.mediaType === "image");

const showImage = computed(() => isImageMode.value && !!props.settings?.image);

const showVideoFile = computed(
  () => !isImageMode.value && !!props.settings?.videoFile,
);

const showVideoEmbed = computed(
  () => !isImageMode.value && !!videoEmbedUrl.value,
);

// Determine MIME type from videoFile extension
const videoFileType = computed(() => {
  const url = (props.settings?.videoFile || "").toLowerCase();
  if (url.endsWith(".webm")) return "video/webm";
  if (url.endsWith(".ogg") || url.endsWith(".ogv")) return "video/ogg";
  return "video/mp4";
});

// Convert a user-supplied YouTube or Vimeo URL into an embed URL
const videoEmbedUrl = computed(() => {
  const url = (props.settings?.video || "").trim();
  if (!url) return "";

  // Already an embed URL
  if (url.includes("/embed/") || url.includes("player.vimeo.com")) return url;

  // YouTube: youtu.be/ID or youtube.com/watch?v=ID or youtube.com/shorts/ID
  const ytShort = url.match(/youtu\.be\/([^?&]+)/);
  if (ytShort) return `https://www.youtube.com/embed/${ytShort[1]}`;
  const ytWatch = url.match(/[?&]v=([^&]+)/);
  if (ytWatch) return `https://www.youtube.com/embed/${ytWatch[1]}`;
  const ytShorts = url.match(/shorts\/([^?&]+)/);
  if (ytShorts) return `https://www.youtube.com/embed/${ytShorts[1]}`;

  // Vimeo: vimeo.com/ID
  const vimeo = url.match(/vimeo\.com\/(\d+)/);
  if (vimeo) return `https://player.vimeo.com/video/${vimeo[1]}`;

  return "";
});

const editorForms =
  typeof window !== "undefined" ? window.dsfEditorData?.forms || [] : [];

const normalizedFormId = computed(() => {
  const raw = props.settings?.formId;
  const parsed = Number.parseInt(raw, 10);
  return Number.isFinite(parsed) && parsed > 0 ? String(parsed) : "";
});

const selectedFormTitle = computed(() => {
  const explicit = (props.settings?.formTitle || "").trim();
  if (explicit) return explicit;
  if (!normalizedFormId.value) return "No form selected";
  const match = editorForms.find(
    (f) => String(f?.id || "") === normalizedFormId.value,
  );
  return match?.title || `Form #${normalizedFormId.value}`;
});

const shortcodeLabel = computed(() =>
  normalizedFormId.value
    ? `[dsform id='${normalizedFormId.value}']`
    : "[dsform id='']",
);

const renderedHtml = computed(() => props.settings?.renderedFormHtml || "");

const customFormHtml = computed(() => {
  if (isDsfFormSource.value) return "";
  return props.settings?.renderedEmbedHtml || props.settings?.embedCode || "";
});

const customFormScripts = computed(() => {
  if (
    isDsfFormSource.value ||
    !Array.isArray(props.settings?.renderedEmbedScripts)
  )
    return [];
  return props.settings.renderedEmbedScripts;
});

const customFormScriptSignature = computed(() => {
  if (!customFormScripts.value.length) return "";
  return JSON.stringify(
    customFormScripts.value.map((script) => script?.code || ""),
  );
});

function mountEmbeddedForms() {
  if (
    props.isEditor ||
    renderMode === "snapshot" ||
    isUnmounted ||
    (!renderedHtml.value && !customFormHtml.value) ||
    !frontendRoot.value
  )
    return;
  injectGravityFormOverrides();
  normalizeEmbeddedFormLegends();
  if (typeof window?.dsfInitForms === "function") {
    window.dsfInitForms(frontendRoot.value);
  }
  runEmbeddedScripts();
  bindGravityPageScroll();
}

function injectGravityFormOverrides() {
  if (typeof document === "undefined") return;

  const existingStyle = document.getElementById(gravityOverrideStyleId);
  if (existingStyle) {
    existingStyle.remove();
  }

  const style = document.createElement("style");
  style.id = gravityOverrideStyleId;
  style.textContent = `
body [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper,
body [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper *,
body [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper.gravity-theme,
body [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper.gravity-theme * {
  font-family: var(--dsf-theme-body-font, inherit) !important;
  line-height: 1.65 !important;
  margin-bottom: 5px !important; 
}

body [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] legend,
body [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper legend.gfield_label,
body [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper.gravity-theme legend.gfield_label {
  margin-bottom: 0 !important;
}

body [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_ajax_spinner,
body [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform-loader,
body [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] [id^="gform_ajax_spinner_"] {
  width: 16px !important;
  height: 16px !important;
  max-width: 16px !important;
  max-height: 16px !important;
  min-width: 16px !important;
  min-height: 16px !important;
  margin-left: .5rem !important;
  border-width: 2px !important;
}

body [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gchoice {
  display: flex !important;
  align-items: center !important;
  gap: 0.5rem !important;
  margin-bottom: 0 !important;
}

body [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gchoice > input[type="checkbox"],
body [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gchoice > input[type="radio"] {
  flex-shrink: 0 !important;
  margin: 0 !important;
}

body [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gchoice > label {
  margin: 0 !important;
  display: inline !important;
}
`;
  document.head.appendChild(style);
}

function normalizeEmbeddedFormLegends() {
  const root = frontendRoot.value;
  if (!root) return;

  root
    .querySelectorAll(
      ".gform_wrapper legend, .gform_wrapper legend.gfield_label, .gform_wrapper.gravity-theme legend.gfield_label",
    )
    .forEach((legend) => {
      legend.style.setProperty("margin-bottom", "0", "important");
      legend.style.setProperty("margin-block-end", "0", "important");
    });
}

function runEmbeddedScripts() {
  if (!customFormScripts.value.length || !customFormScriptSignature.value)
    return;
  if (executedEmbedScriptSignature.value === customFormScriptSignature.value)
    return;

  executedEmbedScriptSignature.value = customFormScriptSignature.value;

  executeEmbedScriptsWhenReady(customFormScripts.value);
}

function executeEmbedScriptsWhenReady(scripts, attempt = 0) {
  if (isUnmounted) return;

  const needsGravityForms = scripts.some((scriptPayload) =>
    /\bgform\b|gravity_form|gform_wrapper/.test(scriptPayload?.code || ""),
  );

  if (
    needsGravityForms &&
    typeof window !== "undefined" &&
    !window.gform &&
    attempt < 80
  ) {
    pendingScriptTimeoutId = window.setTimeout(() => {
      pendingScriptTimeoutId = null;
      executeEmbedScriptsWhenReady(scripts, attempt + 1);
    }, 50);
    return;
  }

  scripts.forEach((scriptPayload) => {
    const code = (scriptPayload?.code || "").trim();
    if (!code) return;

    const script = document.createElement("script");
    script.type = "text/javascript";
    script.text = code;
    document.body.appendChild(script);
    script.remove();
  });
}

function bindGravityPageScroll() {
  if (
    props.isEditor ||
    gravityPageLoadedHandler ||
    typeof window === "undefined"
  )
    return;

  gravityPageLoadedHandler = (_event, formId) => {
    normalizeEmbeddedFormLegends();
    scrollToEmbeddedFormTop(formId);
  };

  gravityNativePageChangeHandler = (event) => {
    normalizeEmbeddedFormLegends();
    scrollToEmbeddedFormTop(event?.detail?.formId);
  };

  if (window.jQuery) {
    window.jQuery(document).on("gform_page_loaded", gravityPageLoadedHandler);
  }

  document.addEventListener(
    "gform/ajax/post_page_change",
    gravityNativePageChangeHandler,
  );
}

function scrollToEmbeddedFormTop(formId) {
  const root = frontendRoot.value;
  if (!root || typeof window === "undefined") return;

  const normalizedFormId = Number.parseInt(formId, 10);
  if (
    Number.isFinite(normalizedFormId) &&
    normalizedFormId > 0 &&
    !root.querySelector(`#gform_${normalizedFormId}`)
  ) {
    return;
  }

  const top = root.getBoundingClientRect().top + window.pageYOffset - 24;
  window.scrollTo({
    top: Math.max(top, 0),
    behavior: "smooth",
  });
}

function syncContentEditor() {
  if (!contentEditor.value) return;
  const next = props.settings?.content || defaultContent;
  if (contentEditor.value.innerHTML !== next) {
    contentEditor.value.innerHTML = next;
  }
}

function onContentBlur() {
  if (!props.isEditor || !contentEditor.value) return;
  const html = contentEditor.value.innerHTML;
  if (html !== props.settings?.content) {
    props.settings.content = html;
  }
}

function onContentPaste(event) {
  if (!props.isEditor) return;
  // Strip formatting to avoid pasting marketing HTML that can introduce
  // stray comment tokens (`-->`) which corrupt the saved snapshot HTML.
  event.preventDefault();
  const text = event.clipboardData?.getData("text/plain") || "";
  const selection = window.getSelection();
  if (!selection?.rangeCount) return;
  selection.deleteFromDocument();
  selection.getRangeAt(0).insertNode(document.createTextNode(text));
  selection.collapseToEnd();
  onContentBlur();
}

onMounted(() => {
  syncContentEditor();
  nextTick(mountEmbeddedForms);
});
onUpdated(() => nextTick(mountEmbeddedForms));
// Keep the inline editor in sync when settings.content changes from the
// side panel (WYSIWYG field), but skip while the user is actively editing
// to avoid caret jumping.
watch(
  () => props.settings?.content,
  () => {
    if (typeof document !== "undefined" &&
        document.activeElement === contentEditor.value) {
      return;
    }
    syncContentEditor();
  },
);
onBeforeUnmount(() => {
  isUnmounted = true;
  if (pendingScriptTimeoutId !== null) {
    clearTimeout(pendingScriptTimeoutId);
    pendingScriptTimeoutId = null;
  }
  if (
    typeof window !== "undefined" &&
    window.jQuery &&
    gravityPageLoadedHandler
  ) {
    window.jQuery(document).off("gform_page_loaded", gravityPageLoadedHandler);
  }
  if (typeof document !== "undefined" && gravityNativePageChangeHandler) {
    document.removeEventListener(
      "gform/ajax/post_page_change",
      gravityNativePageChangeHandler,
    );
  }
});
</script>

<style scoped>
.dsf-form-with-content {
  container-type: inline-size;
}

/* ── Section header ─────────────────────────────────── */
.dsf-form-with-content__header {
  text-align: center;
  margin-bottom: 2rem;
}

.dsf-form-with-content__section-title {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-size: 2rem;
  font-weight: 700;
  line-height: 1.2;
  margin-bottom: 1rem;
}

.dsf-form-with-content__divider {
  border: none;
  border-top: 2px solid #e5e7eb;
  margin: 0;
  width: 100%;
}

/* ── Two-column grid ────────────────────────────────── */
.dsf-form-with-content__grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 3rem;
  max-width: 1200px;
  margin: 0 auto;
  align-items: start;
}

/* Form right (default): content | form */
.dsf-form-with-content__grid--form-right .dsf-form-with-content__col--content {
  order: 1;
}
.dsf-form-with-content__grid--form-right .dsf-form-with-content__col--form {
  order: 2;
}

/* Form left: form | content */
.dsf-form-with-content__grid--form-left .dsf-form-with-content__col--form {
  order: 1;
}
.dsf-form-with-content__grid--form-left .dsf-form-with-content__col--content {
  order: 2;
}

.dsf-form-with-content__col {
  min-width: 0;
  border-radius: var(--dsf-radius-lg);
  padding: 1rem;
}

/* ── Rich text ──────────────────────────────────────── */
.dsf-form-with-content__content--editable {
  outline: none;
  transition: outline 0.2s, background-color 0.2s;
  border-radius: 4px;
}

.dsf-form-with-content__content--editable:hover {
  outline: 1px dashed var(--dsf-primary-300, #93c5fd);
  cursor: text;
}

.dsf-form-with-content__content--editable:focus {
  outline: 2px solid var(--dsf-primary-500, #3b82f6);
  background-color: rgba(255, 255, 255, 0.4);
}

.dsf-form-with-content__content :deep(h1),
.dsf-form-with-content__content :deep(h2),
.dsf-form-with-content__content :deep(h3),
.dsf-form-with-content__content :deep(h4) {
  font-family: var(--dsf-theme-heading-font, inherit);
  font-weight: 700;
  line-height: 1.2;
  margin-bottom: 0.75rem;
}

.dsf-form-with-content__content :deep(h2) {
  font-size: 2rem;
}
.dsf-form-with-content__content :deep(h3) {
  font-size: 1.5rem;
}

.dsf-form-with-content__content :deep(p) {
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-form-content-font-size, 24px);
  line-height: 1.65;
  margin-bottom: 1rem;
}

.dsf-form-with-content__content :deep(ul),
.dsf-form-with-content__content :deep(ol) {
  font-family: var(--dsf-theme-body-font, inherit);
  font-size: var(--dsf-form-content-font-size, 24px);
  padding-left: 1.5rem;
  margin-bottom: 1rem;
  line-height: 1.65;
}

.dsf-form-with-content__content :deep(li),
.dsf-form-with-content__content :deep(em),
.dsf-form-with-content__content :deep(span) {
  font-size: inherit;
  line-height: inherit;
}

.dsf-form-with-content__content :deep(strong),
.dsf-form-with-content__content :deep(b) {
  font-size: inherit;
  font-weight: 700;
  line-height: inherit;
}

.dsf-form-with-content__content :deep(a) {
  color: var(--dsf-primary, #2c5f5d);
  text-decoration: underline;
}

/* ── Shared media wrapper (image or video) ──────────── */
.dsf-form-with-content__media-wrap {
  position: relative;
  isolation: isolate;
  width: 100%;
  margin-top: 4rem;
}

/* ── Logo (absolute, centred above media) ───────────── */
.dsf-form-with-content__logo {
  position: absolute;
  z-index: 3;
  left: 50%;
  transform: translateX(-50%);
  top: -35px;
  width: 50%;
  height: 120px;
  background: #fff;
  border-radius: var(--dsf-radius-md);
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
  box-sizing: border-box;
  object-fit: contain;
  pointer-events: none;
  transition: opacity 1s ease-out;
}

/* ── Image ──────────────────────────────────────────── */
.dsf-form-with-content__image {
  position: relative;
  z-index: 1;
  width: 100%;
  height: auto;
  display: block;
  object-fit: cover;
  border-radius: var(--dsf-radius-lg);
}

/* ── Video inner wrap ───────────────────────────────── */
.dsf-form-with-content__video-wrap {
  position: relative;
  z-index: 1;
  width: 100%;
  border-radius: var(--dsf-radius-lg);
  overflow: hidden;
}

/* iframe embeds need the 16:9 padding trick */
.dsf-form-with-content__video-wrap:has(iframe) {
  padding-top: 56.25%;
}

.dsf-form-with-content__video-wrap:has(iframe) iframe {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}

/* Native video: just full width, natural aspect ratio */
.dsf-form-with-content__video--file {
  width: 100%;
  height: auto;
  display: block;
  border-radius: var(--dsf-radius-lg);
}

.dsf-form-with-content__video-placeholder {
  margin-top: 1rem;
  padding: 0.75rem 1rem;
  background: var(--dsf-gray-50);
  border: 1px dashed var(--dsf-gray-300);
  border-radius: var(--dsf-radius-md);
  font-size: 0.8125rem;
  color: var(--dsf-gray-500);
}

/* ── Form placeholder (editor) ──────────────────────── */
.dsf-form-with-content__form-placeholder {
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-lg);
  padding: 1.5rem;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  height: 100%;
  min-height: 200px;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.dsf-form-with-content__badge {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.625rem;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--dsf-primary-700, #1a3f3d);
  background: #e0ebff;
  width: fit-content;
}

.dsf-form-with-content__form-name {
  font-size: 1rem;
  font-weight: 600;
  color: var(--dsf-gray-900);
}

.dsf-form-with-content__hint {
  color: var(--dsf-gray-500);
  font-size: 0.8125rem;
  line-height: 1.45;
  flex: 1;
}

.dsf-form-with-content__code {
  display: inline-flex;
  border-radius: var(--dsf-radius-md);
  border: 1px solid var(--dsf-gray-300);
  padding: 0.3rem 0.5rem;
  color: var(--dsf-gray-700);
  background: #fff;
  font-size: 0.75rem;
}

.dsf-form-with-content__empty {
  border: 1px dashed var(--dsf-gray-300);
  border-radius: var(--dsf-radius-lg);
  color: var(--dsf-gray-500);
  font-size: 0.875rem;
  padding: 1.5rem;
  text-align: center;
}

.dsf-form-with-content__form-frontend :deep(iframe) {
  max-width: 100%;
}

.dsf-form-with-content__form-frontend {
  min-width: 0;
  overflow-wrap: anywhere;
  font-family: var(--dsf-theme-body-font, inherit) !important;
  line-height: 1.65 !important;
}

.dsf-form-with-content__form-frontend :deep(*) {
  font-family: var(--dsf-theme-body-font, inherit) !important;
  line-height: 1.65 !important;
}

.dsf-form-with-content__form-frontend :deep(.gform-field-label),
.dsf-form-with-content__form-frontend :deep(.gfield_label),
.dsf-form-with-content__form-frontend :deep(.gfield_description),
.dsf-form-with-content__form-frontend :deep(.gchoice),
.dsf-form-with-content__form-frontend :deep(.gchoice label),
.dsf-form-with-content__form-frontend :deep(.gfield_checkbox label),
.dsf-form-with-content__form-frontend :deep(.gfield_radio label),
.dsf-form-with-content__form-frontend :deep(.gform_required_legend),
.dsf-form-with-content__form-frontend :deep(.gf_progressbar_title) {
  font-family: var(--dsf-theme-body-font, inherit) !important;
  line-height: 1.65 !important;
}

.dsf-form-with-content.dsf-form-with-content
  .dsf-form-with-content__form-frontend.dsf-form-with-content__form-frontend
  :deep(.gform_wrapper .gfield_required),
.dsf-form-with-content.dsf-form-with-content
  .dsf-form-with-content__form-frontend.dsf-form-with-content__form-frontend
  :deep(.gform_wrapper.gravity-theme .gchoice label),
.dsf-form-with-content.dsf-form-with-content
  .dsf-form-with-content__form-frontend.dsf-form-with-content__form-frontend
  :deep(.gform_wrapper .gchoice label),
.dsf-form-with-content.dsf-form-with-content
  .dsf-form-with-content__form-frontend.dsf-form-with-content__form-frontend
  :deep(.gform_wrapper.gravity-theme .gfield_checkbox label),
.dsf-form-with-content.dsf-form-with-content
  .dsf-form-with-content__form-frontend.dsf-form-with-content__form-frontend
  :deep(.gform_wrapper.gravity-theme .gfield_radio label) {
  font-family: var(--dsf-theme-body-font, inherit) !important;
  line-height: 1.65 !important;
}

.dsf-form-with-content__form-frontend :deep(.gform_wrapper),
.dsf-form-with-content__form-frontend :deep(.gform_body),
.dsf-form-with-content__form-frontend :deep(.gform_fields),
.dsf-form-with-content__form-frontend :deep(.gfield),
.dsf-form-with-content__form-frontend :deep(.ginput_container) {
  min-width: 0;
}

.dsf-form-with-content__form-frontend :deep(legend),
.dsf-form-with-content__form-frontend :deep(.gform_wrapper legend.gfield_label),
.dsf-form-with-content__form-frontend :deep(.gform_wrapper.gravity-theme legend.gfield_label) {
  margin-bottom: 0 !important;
}

.dsf-form-with-content__form-frontend :deep(.gform_title) {
  font-size: 1.75rem !important;
  line-height: 1.25 !important;
}

.dsf-form-with-content__form-frontend
  :deep(
    input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not(
        [type="button"]
      ):not([type="image"])
  ),
.dsf-form-with-content__form-frontend :deep(select),
.dsf-form-with-content__form-frontend :deep(textarea) {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

.dsf-form-with-content__form-frontend :deep(.ginput_complex) {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem 1rem;
  width: 100%;
}

.dsf-form-with-content__form-frontend :deep(.ginput_complex > span),
.dsf-form-with-content__form-frontend :deep(.ginput_complex .name_first),
.dsf-form-with-content__form-frontend :deep(.ginput_complex .name_last) {
  display: block;
  width: auto;
  max-width: none;
  margin: 0;
}

.dsf-form-with-content__form-frontend :deep(.ginput_complex > span input),
.dsf-form-with-content__form-frontend :deep(.ginput_complex .name_first input),
.dsf-form-with-content__form-frontend :deep(.ginput_complex .name_last input) {
  width: 100%;
  max-width: 100%;
}

.dsf-form-with-content__form-frontend :deep(.gform_fields) {
  row-gap: 1rem;
}

.dsf-form-with-content__form-frontend :deep(.gform_ajax_spinner),
.dsf-form-with-content__form-frontend :deep(.gform-loader),
.dsf-form-with-content__form-frontend :deep([id^="gform_ajax_spinner_"]) {
  width: 16px !important;
  height: 16px !important;
  max-width: 16px !important;
  max-height: 16px !important;
  margin-left: 0.5rem !important;
  vertical-align: middle !important;
}

.dsf-form-with-content__form-frontend :deep(.gform-loader) {
  border-width: 2px !important;
}

.dsf-form-with-content__form-frontend :deep(input[type="checkbox"]),
.dsf-form-with-content__form-frontend :deep(input[type="radio"]) {
  position: static;
  display: inline-block;
  width: auto;
  height: auto;
  margin: 0 0.5rem 0 0;
  opacity: 1;
  appearance: auto;
  vertical-align: middle;
  flex-shrink: 0;
}

/* Keep the checkbox/radio inline with its label inside Gravity Forms choices. */
.dsf-form-with-content__form-frontend :deep(.gchoice) {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.dsf-form-with-content__form-frontend :deep(.gchoice > label),
.dsf-form-with-content__form-frontend :deep(.gchoice > input + label) {
  margin: 0 !important;
  display: inline;
}

/* ── Responsive: stack below 680px ─────────────────── */
@container (max-width: 680px) {
  .dsf-form-with-content__grid {
    grid-template-columns: 1fr;
    gap: 2rem;
  }

  .dsf-form-with-content__grid--form-left .dsf-form-with-content__col--form,
  .dsf-form-with-content__grid--form-right
    .dsf-form-with-content__col--content {
    order: 1;
  }

  .dsf-form-with-content__grid--form-left .dsf-form-with-content__col--content,
  .dsf-form-with-content__grid--form-right .dsf-form-with-content__col--form {
    order: 2;
  }

  .dsf-form-with-content__form-frontend :deep(.ginput_complex) {
    grid-template-columns: 1fr;
  }
}
</style>
