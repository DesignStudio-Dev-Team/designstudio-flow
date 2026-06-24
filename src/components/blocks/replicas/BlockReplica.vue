<template>
  <div class="dsf-replica" :class="`dsf-replica--${kind}`" aria-hidden="true">
    <!-- Hero (centered editorial) -->
    <div v-if="kind === 'hero'" class="r-hero">
      <span class="r-eyebrow"></span>
      <div class="r-head"><i></i><i></i></div>
      <div class="r-sub"></div>
      <div class="r-cta"><b></b><u></u></div>
      <div class="r-media"></div>
    </div>

    <!-- Bento hero (asymmetric tiles) -->
    <div v-else-if="kind === 'bento'" class="r-bento">
      <div class="r-bento__lead"><span></span><i></i><i></i><b></b></div>
      <div class="r-bento__tile r-bento__tile--a"></div>
      <div class="r-bento__tile r-bento__tile--b"></div>
      <div class="r-bento__tile r-bento__tile--c"></div>
    </div>

    <!-- Spotlight hero (single image, overlaid copy) -->
    <div v-else-if="kind === 'spotlight'" class="r-spotlight">
      <div class="r-spotlight__scene"></div>
      <div class="r-spotlight__copy"><span></span><i></i><i></i><b></b></div>
    </div>

    <!-- Duo hero (split media + copy) -->
    <div v-else-if="kind === 'duo'" class="r-duo">
      <div class="r-duo__copy"><span></span><i></i><i></i><b></b></div>
      <div class="r-duo__media"><u></u></div>
    </div>

    <!-- Expander hero (accordion panels) -->
    <div v-else-if="kind === 'expander'" class="r-expander">
      <div class="r-expander__panel is-open"><i></i></div>
      <div class="r-expander__panel"></div>
      <div class="r-expander__panel"></div>
    </div>

    <!-- Content (rich text column) -->
    <div v-else-if="kind === 'content'" class="r-content">
      <i class="w70"></i><i class="w90"></i><i class="w85"></i><i class="w60"></i>
      <div class="r-content__pull"></div>
      <i class="w80"></i><i class="w50"></i>
    </div>

    <!-- FAQ (accordion rows) -->
    <div v-else-if="kind === 'faq'" class="r-faq">
      <div class="r-faq__row is-open"><i></i><b>–</b></div>
      <div class="r-faq__row"><i></i><b>+</b></div>
      <div class="r-faq__row"><i></i><b>+</b></div>
      <div class="r-faq__row"><i></i><b>+</b></div>
    </div>

    <!-- Text + image (two column) -->
    <div v-else-if="kind === 'text-image'" class="r-textimage">
      <div class="r-textimage__media"><u></u></div>
      <div class="r-textimage__copy"><span></span><i></i><i class="w80"></i><i class="w60"></i><b></b></div>
    </div>

    <!-- Features grid (icon cards) -->
    <div v-else-if="kind === 'features'" class="r-features">
      <div v-for="n in 6" :key="n" class="r-features__cell"><em></em><i></i><i class="w60"></i></div>
    </div>

    <!-- Testimonials (quote card) -->
    <div v-else-if="kind === 'testimonials'" class="r-testimonial">
      <div class="r-testimonial__card">
        <span class="r-quote">&#8220;</span>
        <i></i><i class="w90"></i><i class="w70"></i>
        <div class="r-testimonial__author"><em></em><div><i class="w50"></i><i class="w30"></i></div></div>
      </div>
      <div class="r-dots"><u class="is-on"></u><u></u><u></u></div>
    </div>

    <!-- Countdown (timer digits) -->
    <div v-else-if="kind === 'countdown'" class="r-countdown">
      <span class="r-eyebrow"></span>
      <i class="w60"></i>
      <div class="r-countdown__clock"><b v-for="n in 4" :key="n"><u></u></b></div>
      <div class="r-cta"><b></b></div>
    </div>

    <!-- Pricing (3 plans, middle highlighted) -->
    <div v-else-if="kind === 'pricing'" class="r-pricing">
      <div class="r-pricing__toggle"><u class="is-on"></u><u></u></div>
      <div class="r-pricing__grid">
        <div class="r-pricing__plan"><i class="w40"></i><strong></strong><span></span><span></span><b></b></div>
        <div class="r-pricing__plan is-feature"><em>POPULAR</em><i class="w40"></i><strong></strong><span></span><span></span><b></b></div>
        <div class="r-pricing__plan"><i class="w40"></i><strong></strong><span></span><span></span><b></b></div>
      </div>
    </div>

    <!-- Product grid (woo cards) -->
    <div v-else-if="kind === 'product-grid'" class="r-products">
      <aside class="r-products__filters"><i class="w70"></i><u></u><u></u><u></u><i class="w50"></i><u></u><u></u></aside>
      <div class="r-products__grid">
        <div v-for="n in 6" :key="n" class="r-products__card"><div class="r-products__shot"></div><i class="w70"></i><div class="r-products__buy"><span></span><b></b></div></div>
      </div>
    </div>

    <!-- Featured promo banner (split with curved divider) -->
    <div v-else-if="kind === 'featured-promo'" class="r-promo">
      <div class="r-promo__copy"><span></span><i></i><i class="w70"></i><b></b></div>
      <div class="r-promo__media"><svg viewBox="0 0 120 100" preserveAspectRatio="none"><path d="M120,100 L20,100 C70,80 70,20 38,0 L120,0 Z" /></svg></div>
    </div>

    <!-- CTA banner (centered band) -->
    <div v-else-if="kind === 'cta-banner'" class="r-cta-banner">
      <i></i><i class="w60"></i><div class="r-cta"><b></b><u></u></div>
    </div>

    <!-- Form (labeled fields) -->
    <div v-else-if="kind === 'form'" class="r-form">
      <div class="r-form__field"><span></span><u></u></div>
      <div class="r-form__field"><span></span><u></u></div>
      <div class="r-form__row"><div class="r-form__field"><span></span><u></u></div><div class="r-form__field"><span></span><u></u></div></div>
      <div class="r-form__field"><span></span><u class="tall"></u></div>
      <b class="r-form__submit"></b>
    </div>

    <!-- Mega menu header (logo + nav + panel) -->
    <div v-else-if="kind === 'mega-menu'" class="r-mega">
      <div class="r-mega__bar"><em></em><div class="r-mega__nav"><u class="is-on"></u><u></u><u></u><u></u></div><b></b></div>
      <div class="r-mega__panel"><div class="r-mega__col"><i class="w60"></i><u></u><u></u><u></u></div><div class="r-mega__col"><i class="w60"></i><u></u><u></u><u></u></div><div class="r-mega__feature"></div></div>
    </div>

    <!-- Footer (columns + brand) -->
    <div v-else-if="kind === 'footer'" class="r-footer">
      <div class="r-footer__brand"><em></em><i class="w80"></i><i class="w60"></i></div>
      <div v-for="n in 3" :key="n" class="r-footer__col"><i class="w50"></i><u></u><u></u><u></u></div>
    </div>

    <!-- Fallback -->
    <div v-else class="r-generic"><i class="w60"></i><i class="w90"></i><b></b></div>
  </div>
</template>

<script setup>
defineProps({
  kind: { type: String, default: 'generic' },
})
</script>

<style scoped>
.dsf-replica {
  --p: var(--dsf-theme-primary, #0091ff);
  --s: var(--dsf-theme-secondary, #ff7100);
  --ink: #0c1c2b;
  --soft: #cdd9e2;
  --line: #e4ebf0;
  --mute: #9fb1bf;
  --paper: #ffffff;
  position: absolute;
  inset: 0;
  display: flex;
  font-family: var(--dsf-theme-body-font, 'Source Sans 3', sans-serif);
}
.dsf-replica > * { width: 100%; height: 100%; }

/* shared atoms */
.r-eyebrow { display: block; width: 34px; height: 6px; border-radius: 3px; background: var(--s); }
.r-cta { display: flex; align-items: center; gap: 7px; }
.r-cta b { display: block; width: 52px; height: 17px; border-radius: 5px; background: var(--p); }
.r-cta u { display: block; width: 38px; height: 17px; border: 1px solid var(--soft); border-radius: 5px; }
i.w30 { width: 30%; } i.w40 { width: 40%; } i.w50 { width: 50%; } i.w60 { width: 60%; }
i.w70 { width: 70%; } i.w80 { width: 80%; } i.w85 { width: 85%; } i.w90 { width: 90%; }

/* HERO */
.r-hero { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 18px; gap: 8px; background: linear-gradient(180deg, #fbfcfd, #eef4f8); text-align: center; }
.r-hero .r-head { display: flex; flex-direction: column; align-items: center; gap: 5px; margin-top: 4px; }
.r-hero .r-head i { width: 130px; height: 12px; border-radius: 5px; background: var(--ink); }
.r-hero .r-head i:last-child { width: 90px; }
.r-hero .r-sub { width: 150px; height: 6px; border-radius: 3px; background: var(--soft); }
.r-hero .r-media { width: 78%; height: 30%; margin-top: 6px; border-radius: 8px; background: linear-gradient(120deg, var(--p), #a7d3f4); }

/* BENTO */
.r-bento { display: grid; grid-template-columns: 1.4fr 0.8fr 0.8fr; grid-template-rows: 1fr 1fr; gap: 7px; padding: 9px; background: var(--p); }
.r-bento__lead { grid-row: span 2; display: flex; flex-direction: column; gap: 6px; padding: 13px; border-radius: 8px; background: #fff5ef; }
.r-bento__lead span { width: 26px; height: 5px; border-radius: 3px; background: var(--s); }
.r-bento__lead i { width: 80%; height: 9px; border-radius: 4px; background: var(--ink); }
.r-bento__lead i:nth-of-type(2) { width: 55%; }
.r-bento__lead b { width: 40px; height: 14px; margin-top: auto; border-radius: 4px; background: var(--p); }
.r-bento__tile { border-radius: 8px; background: rgba(255, 255, 255, 0.55); }
.r-bento__tile--b { grid-column: 2 / span 2; background: var(--s); }
.r-bento__tile--c { background: #fff; }

/* SPOTLIGHT */
.r-spotlight { position: relative; padding: 0; }
.r-spotlight__scene { position: absolute; inset: 0; background: linear-gradient(135deg, #11314a, var(--p)); }
.r-spotlight__copy { position: absolute; left: 16px; bottom: 14px; display: flex; flex-direction: column; gap: 6px; }
.r-spotlight__copy span { width: 26px; height: 5px; border-radius: 3px; background: var(--s); }
.r-spotlight__copy i { width: 120px; height: 9px; border-radius: 4px; background: #fff; }
.r-spotlight__copy i:nth-of-type(2) { width: 80px; }
.r-spotlight__copy b { width: 48px; height: 15px; margin-top: 3px; border-radius: 5px; background: var(--s); }

/* DUO */
.r-duo { display: grid; grid-template-columns: 1fr 1fr; }
.r-duo__copy { display: flex; flex-direction: column; justify-content: center; gap: 7px; padding: 18px; background: #fbfcfd; }
.r-duo__copy span { width: 26px; height: 5px; border-radius: 3px; background: var(--s); }
.r-duo__copy i { width: 90%; height: 10px; border-radius: 4px; background: var(--ink); }
.r-duo__copy i:nth-of-type(2) { width: 60%; }
.r-duo__copy b { width: 50px; height: 16px; margin-top: 4px; border-radius: 5px; background: var(--p); }
.r-duo__media { background: linear-gradient(135deg, var(--p), #a7d3f4); }
.r-duo__media u { display: block; width: 46px; height: 46px; margin: calc(50% - 23px) auto; border-radius: 50%; background: rgba(255, 255, 255, 0.85); }

/* EXPANDER */
.r-expander { display: flex; flex-direction: column; gap: 8px; padding: 16px; background: #fbfcfd; }
.r-expander__panel { height: 22px; border-radius: 7px; background: #fff; box-shadow: inset 0 0 0 1px var(--line); }
.r-expander__panel.is-open { flex: 1; background: linear-gradient(120deg, var(--p), #a7d3f4); box-shadow: none; }
.r-expander__panel.is-open i { display: block; width: 40%; height: 8px; margin: 12px; border-radius: 4px; background: rgba(255, 255, 255, 0.8); }

/* CONTENT */
.r-content { display: flex; flex-direction: column; gap: 8px; padding: 20px 22px; background: #fff; }
.r-content i { height: 7px; border-radius: 4px; background: var(--soft); }
.r-content__pull { height: 30px; margin: 5px 0; border-left: 3px solid var(--p); border-radius: 2px; background: #eef5fb; }

/* FAQ */
.r-faq { display: flex; flex-direction: column; gap: 8px; padding: 16px; background: #fff; }
.r-faq__row { display: flex; align-items: center; justify-content: space-between; height: 26px; padding: 0 11px; border-radius: 7px; background: #f4f8fb; }
.r-faq__row i { width: 60%; height: 6px; border-radius: 3px; background: var(--ink); opacity: 0.7; }
.r-faq__row b { color: var(--p); font-size: 13px; font-style: normal; }
.r-faq__row.is-open { height: 46px; align-items: flex-start; padding-top: 9px; background: #eaf4fb; }

/* TEXT + IMAGE */
.r-textimage { display: grid; grid-template-columns: 0.9fr 1.1fr; gap: 14px; padding: 16px; background: #fbfcfd; }
.r-textimage__media { border-radius: 9px; background: linear-gradient(135deg, var(--p), #a7d3f4); }
.r-textimage__media u { display: block; width: 30px; height: 30px; margin: calc(50% - 15px) auto; border-radius: 8px; background: rgba(255, 255, 255, 0.85); }
.r-textimage__copy { display: flex; flex-direction: column; justify-content: center; gap: 7px; }
.r-textimage__copy span { width: 26px; height: 5px; border-radius: 3px; background: var(--s); }
.r-textimage__copy i { height: 9px; border-radius: 4px; background: var(--ink); }
.r-textimage__copy i.w80, .r-textimage__copy i.w60 { background: var(--soft); height: 6px; }
.r-textimage__copy b { width: 50px; height: 15px; margin-top: 3px; border-radius: 5px; background: var(--p); }

/* FEATURES */
.r-features { display: grid; grid-template-columns: repeat(3, 1fr); grid-template-rows: 1fr 1fr; gap: 8px; padding: 14px; background: #fbfcfd; }
.r-features__cell { display: flex; flex-direction: column; gap: 5px; padding: 9px; border-radius: 8px; background: #fff; box-shadow: inset 0 0 0 1px var(--line); }
.r-features__cell em { width: 16px; height: 16px; border-radius: 5px; background: var(--p); }
.r-features__cell i { height: 5px; border-radius: 3px; background: var(--soft); }
.r-features__cell i:first-of-type { width: 80%; background: var(--ink); opacity: 0.6; }

/* TESTIMONIALS */
.r-testimonial { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; padding: 16px; background: linear-gradient(180deg, #fbfcfd, #eef4f8); }
.r-testimonial__card { position: relative; width: 86%; padding: 16px 16px 13px; border-radius: 11px; background: #fff; box-shadow: 0 10px 24px rgba(20, 50, 70, 0.1); }
.r-quote { position: absolute; top: 4px; left: 12px; color: var(--p); font-size: 30px; line-height: 1; font-family: Georgia, serif; }
.r-testimonial__card i { display: block; height: 6px; margin: 6px 0; border-radius: 3px; background: var(--soft); }
.r-testimonial__card i:first-of-type { margin-top: 14px; }
.r-testimonial__author { display: flex; align-items: center; gap: 8px; margin-top: 10px; }
.r-testimonial__author em { width: 24px; height: 24px; border-radius: 50%; background: linear-gradient(135deg, var(--p), var(--s)); }
.r-testimonial__author i { height: 5px; margin: 3px 0; }
.r-dots { display: flex; gap: 5px; }
.r-dots u { width: 6px; height: 6px; border-radius: 50%; background: var(--soft); }
.r-dots u.is-on { width: 16px; border-radius: 3px; background: var(--p); }

/* COUNTDOWN */
.r-countdown { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 9px; padding: 16px; background: linear-gradient(135deg, #11314a, var(--p)); }
.r-countdown .r-eyebrow { background: var(--s); }
.r-countdown i { height: 8px; border-radius: 4px; background: rgba(255, 255, 255, 0.85); }
.r-countdown__clock { display: flex; gap: 8px; }
.r-countdown__clock b { display: grid; place-items: center; width: 34px; height: 38px; border-radius: 7px; background: rgba(255, 255, 255, 0.14); box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.22); }
.r-countdown__clock u { width: 16px; height: 13px; border-radius: 3px; background: #fff; }
.r-countdown .r-cta b { background: var(--s); }

/* PRICING */
.r-pricing { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 14px; background: #fbfcfd; }
.r-pricing__toggle { display: flex; gap: 3px; padding: 3px; border-radius: 999px; background: #e8eef3; }
.r-pricing__toggle u { width: 30px; height: 11px; border-radius: 999px; }
.r-pricing__toggle u.is-on { background: var(--p); }
.r-pricing__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; width: 100%; flex: 1; }
.r-pricing__plan { position: relative; display: flex; flex-direction: column; gap: 5px; padding: 11px 9px; border-radius: 9px; background: #fff; box-shadow: inset 0 0 0 1px var(--line); }
.r-pricing__plan i { height: 5px; border-radius: 3px; background: var(--mute); }
.r-pricing__plan strong { width: 60%; height: 13px; border-radius: 4px; background: var(--ink); }
.r-pricing__plan span { height: 4px; border-radius: 2px; background: var(--soft); }
.r-pricing__plan b { height: 13px; margin-top: auto; border-radius: 5px; background: #eef5fb; }
.r-pricing__plan.is-feature { background: linear-gradient(180deg, #0e2c44, var(--p)); box-shadow: 0 12px 22px rgba(12, 95, 168, 0.28); transform: translateY(-5px); }
.r-pricing__plan.is-feature i, .r-pricing__plan.is-feature span { background: rgba(255, 255, 255, 0.5); }
.r-pricing__plan.is-feature strong { background: #fff; }
.r-pricing__plan.is-feature b { background: var(--s); }
.r-pricing__plan em { position: absolute; top: -7px; right: 7px; padding: 2px 5px; border-radius: 999px; background: var(--s); color: #fff; font-size: 5px; font-style: normal; font-weight: 800; letter-spacing: 0.06em; }

/* PRODUCT GRID */
.r-products { display: grid; grid-template-columns: 0.5fr 1.5fr; gap: 10px; padding: 12px; background: #fbfcfd; }
.r-products__filters { display: flex; flex-direction: column; gap: 6px; padding: 10px; border-radius: 8px; background: #fff; box-shadow: inset 0 0 0 1px var(--line); }
.r-products__filters i { height: 6px; border-radius: 3px; background: var(--ink); opacity: 0.6; }
.r-products__filters u { height: 5px; border-radius: 3px; background: var(--soft); }
.r-products__grid { display: grid; grid-template-columns: repeat(3, 1fr); grid-template-rows: 1fr 1fr; gap: 8px; }
.r-products__card { display: flex; flex-direction: column; gap: 5px; padding: 7px; border-radius: 8px; background: #fff; box-shadow: inset 0 0 0 1px var(--line); }
.r-products__shot { flex: 1; border-radius: 5px; background: linear-gradient(135deg, #dbe9f3, #c2dcee); }
.r-products__card i { height: 5px; border-radius: 3px; background: var(--ink); opacity: 0.6; }
.r-products__buy { display: flex; align-items: center; justify-content: space-between; }
.r-products__buy span { width: 30%; height: 6px; border-radius: 3px; background: var(--s); }
.r-products__buy b { width: 16px; height: 16px; border-radius: 5px; background: var(--p); }

/* FEATURED PROMO */
.r-promo { position: relative; display: grid; grid-template-columns: 1fr 1fr; background: var(--dsf-theme-background, #e6f1f9); overflow: hidden; }
.r-promo__copy { display: flex; flex-direction: column; justify-content: center; gap: 7px; padding: 18px; z-index: 1; }
.r-promo__copy span { width: 26px; height: 5px; border-radius: 3px; background: var(--s); }
.r-promo__copy i { width: 90%; height: 9px; border-radius: 4px; background: var(--ink); }
.r-promo__copy b { width: 52px; height: 16px; margin-top: 4px; border-radius: 5px; background: var(--p); }
.r-promo__media { position: relative; background: linear-gradient(135deg, var(--p), #11314a); }
.r-promo__media svg { position: absolute; inset: 0; width: 102%; height: 100%; }
.r-promo__media path { fill: var(--dsf-theme-background, #e6f1f9); }

/* CTA BANNER */
.r-cta-banner { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 9px; padding: 20px; background: linear-gradient(120deg, var(--p), #0e2c44); }
.r-cta-banner i { width: 70%; height: 11px; border-radius: 5px; background: #fff; }
.r-cta-banner i.w60 { height: 6px; background: rgba(255, 255, 255, 0.6); }
.r-cta-banner .r-cta b { background: var(--s); }
.r-cta-banner .r-cta u { border-color: rgba(255, 255, 255, 0.5); }

/* FORM */
.r-form { display: flex; flex-direction: column; gap: 9px; padding: 16px 18px; background: #fff; }
.r-form__row { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; }
.r-form__field { display: flex; flex-direction: column; gap: 4px; }
.r-form__field span { width: 36%; height: 4px; border-radius: 2px; background: var(--mute); }
.r-form__field u { display: block; height: 13px; border-radius: 5px; background: #f1f5f8; box-shadow: inset 0 0 0 1px var(--line); }
.r-form__field u.tall { height: 26px; }
.r-form__submit { width: 64px; height: 18px; margin-top: 2px; border-radius: 5px; background: var(--p); }

/* MEGA MENU */
.r-mega { display: flex; flex-direction: column; background: #fbfcfd; }
.r-mega__bar { display: flex; align-items: center; gap: 10px; height: 30px; padding: 0 13px; background: #fff; box-shadow: inset 0 -1px 0 var(--line); }
.r-mega__bar em { width: 18px; height: 18px; border-radius: 5px; background: linear-gradient(135deg, var(--p), var(--s)); }
.r-mega__nav { display: flex; flex: 1; gap: 9px; }
.r-mega__nav u { width: 24px; height: 5px; border-radius: 3px; background: var(--soft); }
.r-mega__nav u.is-on { background: var(--p); }
.r-mega__bar b { width: 38px; height: 15px; border-radius: 5px; background: var(--p); }
.r-mega__panel { display: grid; grid-template-columns: 1fr 1fr 1.2fr; gap: 12px; flex: 1; padding: 14px; }
.r-mega__col { display: flex; flex-direction: column; gap: 6px; }
.r-mega__col i { height: 6px; border-radius: 3px; background: var(--ink); opacity: 0.6; }
.r-mega__col u { height: 5px; border-radius: 3px; background: var(--soft); }
.r-mega__feature { border-radius: 8px; background: linear-gradient(135deg, var(--p), #a7d3f4); }

/* FOOTER */
.r-footer { display: grid; grid-template-columns: 1.4fr 1fr 1fr 1fr; gap: 14px; padding: 18px; background: #0c1c2b; }
.r-footer__brand em { display: block; width: 22px; height: 22px; margin-bottom: 8px; border-radius: 6px; background: linear-gradient(135deg, var(--p), var(--s)); }
.r-footer__brand i { display: block; height: 5px; margin: 4px 0; border-radius: 3px; background: rgba(255, 255, 255, 0.28); }
.r-footer__col { display: flex; flex-direction: column; gap: 7px; }
.r-footer__col i { height: 6px; border-radius: 3px; background: rgba(255, 255, 255, 0.55); }
.r-footer__col u { height: 4px; border-radius: 2px; background: rgba(255, 255, 255, 0.2); }

/* GENERIC */
.r-generic { display: flex; flex-direction: column; gap: 9px; justify-content: center; padding: 20px; background: #fbfcfd; }
.r-generic i { height: 9px; border-radius: 4px; background: var(--soft); }
.r-generic b { width: 50px; height: 16px; border-radius: 5px; background: var(--p); }
</style>
