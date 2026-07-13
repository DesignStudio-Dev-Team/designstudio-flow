import { onMounted, onUnmounted } from 'vue'
import { ensureGsap, gsap, ScrollTrigger, SplitText } from './gsapSetup'

const prefersReducedMotion = () =>
  typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches

const isDesktop = () =>
  typeof window !== 'undefined' && window.matchMedia('(min-width: 1024px)').matches

/**
 * Cinematic, scroll-driven motion for the landing blocks.
 *
 * Components opt in through data attributes so the same engine choreographs the
 * whole page:
 *   data-dsf-reveal            staggered enter reveal (fade + rise)
 *   data-dsf-card              3D card reveal
 *   data-dsf-split             SplitText word reveal for headlines
 *   data-dsf-parallax="0.3"    scroll-scrubbed vertical parallax (factor)
 *   data-dsf-scrub             enter-linked scrubbed slide (dir via data-dsf-scrub)
 *   data-dsf-builder           editor mockup that assembles itself on scroll
 *   data-dsf-draw              SVG diagram whose [data-dsf-draw-path] strokes draw in
 *   data-dsf-pin + -hscroll    pinned section whose inner track scrolls horizontally
 *   data-dsf-float/-drift/-pulse  restrained ambient loops
 *
 * Everything stays disabled in the editor and under prefers-reduced-motion.
 */
export function useLandingMotion(root, isEditor) {
  let ctx = null
  let splits = []
  let refreshTimer = null

  const handleLoad = () => ScrollTrigger.refresh()

  onMounted(() => {
    if (isEditor || !root.value || prefersReducedMotion()) return

    ensureGsap()

    ctx = gsap.context(() => {
      const scope = root.value
      const q = (selector) => Array.from(scope.querySelectorAll(selector))

      buildSplitHeadlines(q('[data-dsf-split]'), splits)
      buildReveals(q('[data-dsf-reveal]'))
      buildCardReveals(q('[data-dsf-card]'))
      buildParallax(q('[data-dsf-parallax]'))
      buildScrubSlides(q('[data-dsf-scrub]'))
      q('[data-dsf-builder]').forEach(buildEditorAssembly)
      q('[data-dsf-draw]').forEach(buildDiagram)
      buildAmbientLoops(q('[data-dsf-float]'), q('[data-dsf-drift]'), q('[data-dsf-pulse]'))

      if (isDesktop()) {
        q('[data-dsf-pin]').forEach(buildHorizontalGallery)
      }
    }, root.value)

    // Pinned/scrubbed triggers need a measure pass once layout + fonts settle.
    refreshTimer = window.setTimeout(() => ScrollTrigger.refresh(), 240)
    window.addEventListener('load', handleLoad)
  })

  onUnmounted(() => {
    window.removeEventListener('load', handleLoad)
    if (refreshTimer) window.clearTimeout(refreshTimer)
    splits.forEach((split) => split.revert())
    splits = []
    ctx?.revert()
  })
}

function buildSplitHeadlines(elements, store) {
  elements.forEach((el) => {
    try {
      const split = new SplitText(el, { type: 'lines,words', linesClass: 'dsf-split-line' })
      store.push(split)
      gsap.set(el, { autoAlpha: 1 })
      gsap.from(split.words, {
        yPercent: 118,
        autoAlpha: 0,
        rotationX: -38,
        transformOrigin: '0% 50% -30px',
        duration: 0.9,
        ease: 'power4.out',
        stagger: 0.03,
        scrollTrigger: { trigger: el, start: 'top 90%', once: true },
      })
    } catch (error) {
      // Never let a split failure hide the headline or abort the rest of the page motion.
      gsap.set(el, { autoAlpha: 1, clearProps: 'transform' })
    }
  })
}

function buildReveals(elements) {
  if (!elements.length) return
  gsap.set(elements, { autoAlpha: 0, y: 30, filter: 'blur(6px)' })
  ScrollTrigger.batch(elements, {
    start: 'top 90%',
    onEnter: (batch) =>
      gsap.to(batch, {
        autoAlpha: 1,
        y: 0,
        filter: 'blur(0px)',
        duration: 0.8,
        stagger: 0.09,
        ease: 'power3.out',
        overwrite: true,
        clearProps: 'filter',
      }),
  })
}

function buildCardReveals(elements) {
  if (!elements.length) return
  gsap.set(elements, { autoAlpha: 0, y: 44, scale: 0.955, rotationX: 6, transformOrigin: 'center bottom' })
  ScrollTrigger.batch(elements, {
    start: 'top 92%',
    onEnter: (batch) =>
      gsap.to(batch, {
        autoAlpha: 1,
        y: 0,
        scale: 1,
        rotationX: 0,
        duration: 0.85,
        stagger: 0.08,
        ease: 'power3.out',
        overwrite: true,
        clearProps: 'transform,opacity,visibility',
      }),
  })
}

function buildParallax(elements) {
  elements.forEach((el) => {
    const depth = parseFloat(el.getAttribute('data-dsf-parallax')) || 0.2
    const scope = el.closest('[data-dsf-parallax-scope]') || el.parentElement || el
    gsap.fromTo(
      el,
      { yPercent: -depth * 50 },
      {
        yPercent: depth * 50,
        ease: 'none',
        scrollTrigger: { trigger: scope, start: 'top bottom', end: 'bottom top', scrub: true },
      }
    )
  })
}

function buildScrubSlides(elements) {
  elements.forEach((el) => {
    const dir = el.getAttribute('data-dsf-scrub') || 'up'
    const from = { up: { y: 70 }, down: { y: -70 }, left: { x: 80 }, right: { x: -80 } }[dir] || { y: 70 }
    gsap.fromTo(
      el,
      { autoAlpha: 0, ...from },
      {
        autoAlpha: 1,
        x: 0,
        y: 0,
        ease: 'power2.out',
        scrollTrigger: { trigger: el, start: 'top 88%', end: 'top 55%', scrub: 0.7 },
      }
    )
  })
}

function buildEditorAssembly(shell) {
  const pick = (selector) => shell.querySelector(selector)
  const all = (selector) => Array.from(shell.querySelectorAll(selector))

  const topbar = pick('[data-dsf-builder-topbar]')
  const dock = pick('[data-dsf-builder-dock]')
  const canvasLabel = pick('[data-dsf-builder-label]')
  const selectedBlock = pick('[data-dsf-builder-selected]')
  const toolbar = pick('[data-dsf-builder-toolbar]')
  const cards = all('[data-dsf-builder-card]')
  const addButton = pick('[data-dsf-builder-add]')
  const settingsPanel = pick('[data-dsf-builder-settings]')
  const artTiles = all('[data-dsf-builder-art] i')
  const targets = [topbar, dock, canvasLabel, selectedBlock, toolbar, ...artTiles, ...cards, addButton, settingsPanel].filter(Boolean)

  // Initial (scroll-progress 0) state for every part of the mockup.
  gsap.set(targets, { autoAlpha: 0 })
  gsap.set(shell, { autoAlpha: 0, y: 48, scale: 0.965 })
  // The mockup shows either a top bar (older layout) or the new bottom dock; the
  // top bar drops in from above, the dock rises in from below.
  if (topbar) gsap.set(topbar, { y: -18 })
  if (dock) gsap.set(dock, { y: 20 })
  gsap.set(canvasLabel, { x: -14 })
  gsap.set(selectedBlock, { y: 26, scale: 0.965 })
  gsap.set(toolbar, { scale: 0.7 })
  gsap.set(artTiles, { y: 14, rotation: -12, scale: 0.7 })
  gsap.set(cards, { y: 18, scale: 0.94 })
  if (addButton) gsap.set(addButton, { y: 12 })
  gsap.set(settingsPanel, { x: 28 })

  // One-shot assemble on enter. Works above the fold (hero, fires on load) and
  // below it; avoids the half-built state a scrubbed timeline shows on refresh.
  const tl = gsap.timeline({
    defaults: { ease: 'power3.out' },
    scrollTrigger: { trigger: shell, start: 'top 80%', once: true },
  })
  tl.to(shell, { autoAlpha: 1, y: 0, scale: 1, duration: 0.68 })
  if (topbar) tl.to(topbar, { autoAlpha: 1, y: 0, duration: 0.4 }, '-=0.28')
  tl.to(canvasLabel, { autoAlpha: 1, x: 0, duration: 0.3 }, '-=0.14')
    .to(selectedBlock, { autoAlpha: 1, y: 0, scale: 1, duration: 0.5 }, '-=0.08')
    .to(toolbar, { autoAlpha: 1, scale: 1, duration: 0.3 }, '-=0.18')
    .to(artTiles, { autoAlpha: 1, y: 0, rotation: 0, scale: 1, duration: 0.32, stagger: 0.07 }, '-=0.12')
    .to(cards, { autoAlpha: 1, y: 0, scale: 1, duration: 0.4, stagger: 0.1 }, '-=0.06')
  if (addButton) tl.to(addButton, { autoAlpha: 1, y: 0, duration: 0.32 }, '-=0.12')
  tl.to(settingsPanel, { autoAlpha: 1, x: 0, duration: 0.5 }, '-=0.24')
  if (dock) tl.to(dock, { autoAlpha: 1, y: 0, duration: 0.42 }, '-=0.2')
}

function buildDiagram(el) {
  const paths = el.querySelectorAll('[data-dsf-draw-path]')
  const nodes = el.querySelectorAll('[data-dsf-draw-node]')

  if (paths.length) {
    gsap.fromTo(
      paths,
      { drawSVG: '0%' },
      {
        drawSVG: '100%',
        duration: 1.1,
        stagger: 0.16,
        ease: 'power2.inOut',
        scrollTrigger: { trigger: el, start: 'top 78%', once: true },
      }
    )
  }

  if (nodes.length) {
    gsap.from(nodes, {
      autoAlpha: 0,
      scale: 0.78,
      y: 14,
      transformOrigin: 'center',
      duration: 0.55,
      stagger: 0.18,
      ease: 'back.out(1.7)',
      scrollTrigger: { trigger: el, start: 'top 78%', once: true },
    })
  }
}

function buildAmbientLoops(floats, drifts, pulses) {
  if (floats.length) {
    gsap.to(floats, { y: -8, duration: 2.8, stagger: 0.16, repeat: -1, yoyo: true, ease: 'sine.inOut' })
  }
  if (drifts.length) {
    gsap.to(drifts, { x: 6, rotation: 1.1, duration: 3.6, stagger: 0.2, repeat: -1, yoyo: true, ease: 'sine.inOut' })
  }
  if (pulses.length) {
    gsap.to(pulses, { scale: 1.03, duration: 2.1, stagger: 0.18, repeat: -1, yoyo: true, transformOrigin: 'center', ease: 'sine.inOut' })
  }
}

function buildHorizontalGallery(container) {
  const track = container.querySelector('[data-dsf-hscroll]')
  if (!track) return

  const distance = () => Math.max(0, track.scrollWidth - container.clientWidth)
  if (distance() <= 0) return

  const tween = gsap.to(track, {
    x: () => -distance(),
    ease: 'none',
    scrollTrigger: {
      trigger: container,
      pin: true,
      scrub: 0.8,
      start: 'top top',
      end: () => '+=' + distance(),
      invalidateOnRefresh: true,
      anticipatePin: 1,
    },
  })

  // Expose the trigger so the gallery component can drive filter scroll-to.
  container._dsfPinST = tween.scrollTrigger
  container._dsfTrack = track
}
