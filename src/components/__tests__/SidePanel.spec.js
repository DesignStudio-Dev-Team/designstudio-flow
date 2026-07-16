import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SidePanel from '../SidePanel.vue'

const settingFieldStub = {
  props: ['fieldKey', 'allSettings'],
  template: '<div class="setting-stub">{{ fieldKey }}</div>',
}

const bentoDefinition = {
  id: 'bento-hero',
  name: 'Bento Hero',
  settings: {
    heroImage: { type: 'image', label: 'Hero Image' },
    heroTitle: { type: 'text', label: 'Hero Title' },
    heroType: {
      type: 'select',
      label: 'Hero Type',
      options: {
        Search: 'search',
        Button: 'button',
      },
    },
    searchPlaceholder: { type: 'text', label: 'Search Placeholder', showWhen: { heroType: 'search' } },
    searchUrl: { type: 'text', label: 'Search URL', showWhen: { heroType: 'search' } },
    boxCount: {
      type: 'select',
      label: 'Box Count',
      options: {
        '4': '4',
        '6': '6',
      },
    },
    showTopBar: { type: 'toggle', label: 'Show Top Bar' },
    box1Image: { type: 'image', label: 'Box 1 Image' },
    box1Title: { type: 'text', label: 'Box 1 Title' },
    box1Url: { type: 'text', label: 'Box 1 URL' },
    box5Title: { type: 'text', label: 'Box 5 Title', showWhen: { boxCount: '6' } },
    ctaType: {
      type: 'select',
      label: 'Last Box Type',
      options: {
        CTA: 'cta',
      },
      showWhen: { boxCount: '6' },
    },
    boxBackground: { type: 'color', label: 'Box Background' },
    boxImageSize: { type: 'slider', label: 'Box Image Size' },
    titleColor: { type: 'color', label: 'Title Color' },
    sectionBarBackground: { type: 'color', label: 'Section Bar Background' },
    sectionBarTextColor: { type: 'color', label: 'Section Bar Text Color' },
    sectionBarHeight: { type: 'slider', label: 'Section Bar Height' },
    ctaColor: { type: 'color', label: 'CTA Background', showWhen: { boxCount: '6', ctaType: 'cta' } },
    ctaTextColor: { type: 'color', label: 'CTA Text Color', showWhen: { boxCount: '6', ctaType: 'cta' } },
  },
}

const simpleDefinition = {
  id: 'features-grid',
  name: 'Features Grid',
  settings: {
    bottomButtonText: { type: 'text', label: 'Bottom Button Text' },
    bottomButtonAction: {
      type: 'select',
      label: 'Bottom Button Action',
      showWhenNotEmpty: ['bottomButtonText'],
    },
  },
}

const formWithContentDefinition = {
  id: 'form-with-content',
  name: 'Form with Content',
  settings: {
    sectionTitle: { type: 'text', label: 'Section Title' },
    showDivider: { type: 'toggle', label: 'Show Divider' },
    dividerColor: { type: 'color', label: 'Divider Color' },
    formSource: { type: 'select', label: 'Form Source' },
    formId: { type: 'select', label: 'Form', showWhen: { formSource: 'dsf' } },
    embedCode: { type: 'wysiwyg', label: 'Embed', showWhen: { formSource: 'embed' } },
    formSide: { type: 'select', label: 'Position' },
    columnRatio: { type: 'select', label: 'Ratio' },
    content: { type: 'wysiwyg', label: 'Content' },
    logo: { type: 'image', label: 'Brand Logo' },
    logoPadding: { type: 'toggle', label: 'Logo Padding', showWhenNotEmpty: ['logo'] },
    mediaType: { type: 'select', label: 'Media Type' },
    image: { type: 'image', label: 'Image', showWhen: { mediaType: 'image' } },
    video: { type: 'text', label: 'Video URL', showWhen: { mediaType: 'video' } },
    videoFile: { type: 'video', label: 'Video File', showWhen: { mediaType: 'video' } },
  },
}

function mountSidePanel(blockSettings = {}) {
  return mount(SidePanel, {
    props: {
      block: {
        type: 'bento-hero',
        settings: {
          heroType: 'search',
          boxCount: '6',
          ctaType: 'cta',
          ...blockSettings,
        },
      },
      blockDefinition: bentoDefinition,
    },
    global: {
      stubs: {
        SettingField: settingFieldStub,
      },
    },
  })
}

function mountSimpleSidePanel(blockSettings = {}) {
  return mount(SidePanel, {
    props: {
      block: {
        type: 'features-grid',
        settings: blockSettings,
      },
      blockDefinition: simpleDefinition,
    },
    global: {
      stubs: {
        SettingField: settingFieldStub,
      },
    },
  })
}

function mountFormWithContentPanel(blockSettings = {}) {
  return mount(SidePanel, {
    props: { block: { type: 'form-with-content', settings: { formSource: 'dsf', mediaType: 'image', ...blockSettings } }, blockDefinition: formWithContentDefinition },
    global: { stubs: { SettingField: settingFieldStub } },
  })
}

describe('SidePanel', () => {
  it('renders Bento content settings inside expanders', async () => {
    const wrapper = mountSidePanel()

    expect(wrapper.find('[data-section="hero"]').exists()).toBe(true)
    expect(wrapper.find('[data-section="layout"]').exists()).toBe(true)
    expect(wrapper.find('[data-section="box1"]').exists()).toBe(true)
    expect(wrapper.findAll('.setting-stub').map((node) => node.text())).not.toContain('box1Title')

    await wrapper.find('[data-section="box1"] .dsf-settings-expander__trigger').trigger('click')

    expect(wrapper.findAll('.setting-stub').map((node) => node.text())).toContain('box1Title')
  })

  it('hides Bento sections that are not relevant for 4-box layouts', () => {
    const wrapper = mountSidePanel({ boxCount: '4' })

    expect(wrapper.find('[data-section="box5"]').exists()).toBe(false)
    expect(wrapper.find('[data-section="lastTile"]').exists()).toBe(false)
  })

  it('renders Bento style settings inside expanders', async () => {
    const wrapper = mountSidePanel()

    await wrapper.findAll('.dsf-segmented-btn').find((node) => node.text().includes('Style')).trigger('click')

    expect(wrapper.find('[data-style-section="tiles"]').exists()).toBe(true)
    expect(wrapper.find('[data-style-section="spacing"]').exists()).toBe(true)
    expect(wrapper.findAll('.setting-stub').map((node) => node.text())).not.toContain('ctaColor')

    await wrapper.find('[data-style-section="lastTile"] .dsf-settings-expander__trigger').trigger('click')

    expect(wrapper.findAll('.setting-stub').map((node) => node.text())).toContain('ctaColor')
  })

  it('hides last-tile style section for 4-box Bento layouts', async () => {
    const wrapper = mountSidePanel({ boxCount: '4' })

    await wrapper.findAll('.dsf-segmented-btn').find((node) => node.text().includes('Style')).trigger('click')

    expect(wrapper.find('[data-style-section="lastTile"]').exists()).toBe(false)
  })

  it('supports showing fields only after a dependency has content', async () => {
    const emptyWrapper = mountSimpleSidePanel({ bottomButtonText: '' })

    await emptyWrapper.find('.dsf-settings-expander__trigger').trigger('click')

    expect(emptyWrapper.findAll('.setting-stub').map((node) => node.text())).toEqual(['bottomButtonText'])

    const filledWrapper = mountSimpleSidePanel({ bottomButtonText: 'Start Project' })

    await filledWrapper.find('.dsf-settings-expander__trigger').trigger('click')

    expect(filledWrapper.findAll('.setting-stub').map((node) => node.text())).toContain('bottomButtonAction')
  })

  it('separates the optional logo from primary image and video controls', async () => {
    const wrapper = mountFormWithContentPanel()

    expect(wrapper.find('[data-section="brand"]').exists()).toBe(true)
    expect(wrapper.find('[data-section="media"]').exists()).toBe(true)

    await wrapper.find('[data-section="brand"] .dsf-settings-expander__trigger').trigger('click')
    expect(wrapper.findAll('.setting-stub').map((node) => node.text())).toContain('logo')
    expect(wrapper.findAll('.setting-stub').map((node) => node.text())).not.toContain('logoPadding')

    await wrapper.find('[data-section="media"] .dsf-settings-expander__trigger').trigger('click')
    expect(wrapper.findAll('.setting-stub').map((node) => node.text())).toContain('image')
    expect(wrapper.findAll('.setting-stub').map((node) => node.text())).not.toContain('video')
  })
})
