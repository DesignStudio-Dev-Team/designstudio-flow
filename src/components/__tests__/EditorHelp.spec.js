import { describe, expect, it } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import EditorHelp from '../EditorHelp.vue'

describe('EditorHelp', () => {
  it('explains the dock as the first step of the quick tour', () => {
    const wrapper = mount(EditorHelp, { props: { visible: true, step: 'dock' } })

    expect(wrapper.text()).toContain('Settings — everything about this page')
    expect(wrapper.text()).toContain('Quick tour · 1 of 10')
    expect(wrapper.text()).toContain('Click the highlighted Settings icon to open it.')
  })

  it('walks the dock left to right and covers the save options', () => {
    const save = mount(EditorHelp, { props: { visible: true, step: 'save' } })
    expect(save.text()).toContain('Quick tour · 6 of 10')
    expect(save.text()).toContain('Save as template')
    // Structure moved next to Save, so the tour says where it went.
    expect(save.text()).toContain('Structure')

    const organize = mount(EditorHelp, { props: { visible: true, step: 'organize' } })
    expect(organize.text()).toContain('Quick tour · 7 of 10')
    expect(organize.text()).toContain('View page')
    expect(organize.text()).not.toContain('every language this page exists in')
  })

  it('mentions the language control only when a second language is enabled', () => {
    window.dsfEditorData = { translation: { active: true } }
    const wrapper = mount(EditorHelp, { props: { visible: true, step: 'organize' } })

    expect(wrapper.text()).toContain('every language this page exists in')
    delete window.dsfEditorData
  })

  it('names each control and what it does, rather than describing it vaguely', () => {
    const dock = mount(EditorHelp, { props: { visible: true, step: 'dock' } })
    const terms = dock.findAll('.dsf-editor-help__list strong').map((n) => n.text())

    expect(terms).toEqual(['Page', 'Theme'])
    expect(dock.text()).toContain('publish status')
  })

  it('draws a real arrow from the card to the highlighted control', async () => {
    const wrapper = mount(EditorHelp, { props: { visible: true, step: 'dock' }, attachTo: document.body })

    // jsdom gives every element a zero-size rect, so feed the geometry in.
    wrapper.vm.viewport = { width: 1400, height: 900 }
    wrapper.vm.targetRect = { left: 600, top: 800, width: 40, height: 40 }
    wrapper.vm.cardRect = { left: 460, top: 540, width: 350, height: 200 }
    await nextTick()

    const card = wrapper.get('.dsf-editor-help__card')
    expect(card.classes()).toContain('is-anchor-bottom')

    // Shaft leaves the card's lower edge and stops short of the target.
    const shaft = wrapper.get('.dsf-editor-help__arrow-shaft').attributes('d')
    expect(shaft).toMatch(/^M 620 742 L 620 /)

    // A solid three-point head, tip on the target, pointing down at it.
    const head = wrapper.get('.dsf-editor-help__arrow-head').attributes('points')
    const points = head.split(' ').map((pair) => pair.split(',').map(Number))
    expect(points).toHaveLength(3)
    expect(points[0]).toEqual([620, 794])
    expect(points[1][1]).toBeCloseTo(774, 0)
    expect(points[2][1]).toBeCloseTo(774, 0)
    // The head spans a real width rather than collapsing to a spike.
    expect(Math.abs(points[1][0] - points[2][0])).toBeCloseTo(20, 0)

    wrapper.unmount()
  })

  it('draws no arrow at a whole panel, where there is nothing to aim at', async () => {
    const wrapper = mount(EditorHelp, { props: { visible: true, step: 'page-settings-detail' } })

    wrapper.vm.viewport = { width: 1400, height: 900 }
    // A tall panel down the right-hand side.
    wrapper.vm.targetRect = { left: 900, top: 20, width: 460, height: 800 }
    wrapper.vm.cardRect = { left: 500, top: 20, width: 350, height: 400 }
    await nextTick()

    expect(wrapper.find('.dsf-editor-help__arrow').exists()).toBe(false)
    // The ring still frames the panel.
    expect(wrapper.find('.dsf-editor-help__target').exists()).toBe(true)
  })

  it('draws no arrow when the run is too short to read as one', async () => {
    const wrapper = mount(EditorHelp, { props: { visible: true, step: 'dock' } })

    wrapper.vm.viewport = { width: 1400, height: 900 }
    wrapper.vm.targetRect = { left: 600, top: 800, width: 40, height: 40 }
    // Card bottom edge almost touching the target.
    wrapper.vm.cardRect = { left: 460, top: 560, width: 350, height: 230 }
    await nextTick()

    expect(wrapper.find('.dsf-editor-help__arrow').exists()).toBe(false)
  })

  it('drops the arrow on small screens where the card spans the viewport', async () => {
    const wrapper = mount(EditorHelp, { props: { visible: true, step: 'dock' } })

    wrapper.vm.isMobileViewport = true
    wrapper.vm.targetRect = { left: 10, top: 700, width: 40, height: 40 }
    wrapper.vm.cardRect = { left: 14, top: 16, width: 300, height: 200 }
    await nextTick()

    expect(wrapper.find('.dsf-editor-help__arrow').exists()).toBe(false)
  })

  it('emits the appropriate actions for tour progress and dismissal', async () => {
    const wrapper = mount(EditorHelp, { props: { visible: true, step: 'settings' } })

    await wrapper.get('.dsf-editor-help__button').trigger('click')
    expect(wrapper.emitted('next')).toHaveLength(1)

    await wrapper.get('.dsf-editor-help__close').trigger('click')
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('uses the current editor context outside the tour', () => {
    const wrapper = mount(EditorHelp, {
      props: { visible: true, context: { title: 'Customize this block', tip: 'Use Style for colors.' } },
    })

    expect(wrapper.text()).toContain('Customize this block')
    expect(wrapper.text()).toContain('Restart quick tour')
  })
})
