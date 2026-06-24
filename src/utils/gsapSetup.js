/**
 * Central GSAP setup for the DesignStudio Flow landing experience.
 *
 * Registers the scroll/motion plugins exactly once and re-exports the shared
 * `gsap` instance so every landing component animates through the same engine.
 * The full plugin suite already ships inside node_modules/gsap (free since
 * GSAP 3.13), so no extra dependencies are required.
 */
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'
import { SplitText } from 'gsap/SplitText'
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin'

let registered = false

export function ensureGsap() {
  if (!registered && typeof window !== 'undefined') {
    gsap.registerPlugin(ScrollTrigger, SplitText, DrawSVGPlugin)
    registered = true
  }
  return gsap
}

export { gsap, ScrollTrigger, SplitText, DrawSVGPlugin }
