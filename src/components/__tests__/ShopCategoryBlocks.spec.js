import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { ref } from 'vue'
import ShopCategoryHeroPreview from '../blocks/ShopCategoryHeroPreview.vue'
import ShopSubcategoryGridPreview from '../blocks/ShopSubcategoryGridPreview.vue'

const archive = ref({
  title: 'Outdoor Living',
  descriptionHtml: '<p>Built for summer.</p>',
  currentCategory: { name: 'Outdoor Living', image: 'https://example.test/outdoor.jpg', parentName: 'Shop', parentUrl: '/shop/' },
  subcategories: [
    { id: 1, name: 'Furniture', description: 'Tables and seating', image: 'https://example.test/furniture.jpg', url: '/furniture/', count: 12 },
  ],
})

describe('Shop category blocks', () => {
  it('renders the current category hero from server-built archive data', () => {
    const wrapper = mount(ShopCategoryHeroPreview, { props: { settings: {} }, global: { provide: { dsfShopContext: archive } } })
    expect(wrapper.text()).toContain('Outdoor Living')
    expect(wrapper.find('img').attributes('src')).toBe('https://example.test/outdoor.jpg')
    expect(wrapper.find('a').attributes('href')).toBe('/shop/')
  })

  it('renders only the provided immediate child categories', () => {
    const wrapper = mount(ShopSubcategoryGridPreview, { props: { isEditor: true, settings: { columns: 3 } }, global: { provide: { dsfShopContext: archive } } })
    expect(wrapper.findAll('.dsf-shop-subcategory-grid__card')).toHaveLength(1)
    expect(wrapper.text()).toContain('Furniture')
    expect(wrapper.text()).toContain('12 products')
  })
})
