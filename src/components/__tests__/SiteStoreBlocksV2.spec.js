import { describe, it, expect, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import StoreMiniCartPreview from '../blocks/StoreMiniCartPreview.vue'
import StoreThankyouPreview from '../blocks/StoreThankyouPreview.vue'
import ShopFiltersPreview from '../blocks/ShopFiltersPreview.vue'
import SiteLoginPreview from '../blocks/SiteLoginPreview.vue'
import SiteSearchPreview from '../blocks/SiteSearchPreview.vue'
import UserDashboardPreview from '../blocks/UserDashboardPreview.vue'

function mountBlock(Component, settings = {}, { isEditor = true, shopContext = null } = {}) {
  return mount(Component, {
    props: { settings, previewMode: 'desktop', blockId: 'v2', isEditor },
    global: { provide: { dsfShopContext: ref(shopContext) } },
  })
}

afterEach(() => {
  delete window.dsfFrontendData
})

describe('StoreMiniCartPreview', () => {
  it('shows sample state in the editor with a floating hint', () => {
    const w = mountBlock(StoreMiniCartPreview)
    expect(w.find('.dsf-store-mini-cart__count').text()).toBe('2')
    expect(w.find('.dsf-store-mini-cart__subtotal').text()).toContain('$128.00')
    expect(w.find('.dsf-store-mini-cart__hint').text()).toContain('bottom-right')
  })

  it('reads live count/subtotal from the store context and links to the cart', () => {
    window.dsfFrontendData = {
      storeContext: { urls: { cart: '/cart/' }, step: '', fragments: [], miniCart: { count: 3, subtotalHtml: '<span>$45</span>' } },
    }
    const w = mountBlock(StoreMiniCartPreview, {}, { isEditor: false })
    expect(w.find('.dsf-store-mini-cart__count').text()).toBe('3')
    expect(w.find('.dsf-store-mini-cart__subtotal').html()).toContain('$45')
    expect(w.find('.dsf-store-mini-cart__pill').attributes('href')).toBe('/cart/')
  })

  it('hides when empty unless configured otherwise', () => {
    window.dsfFrontendData = { storeContext: { urls: {}, miniCart: { count: 0, subtotalHtml: '' } } }
    const hidden = mountBlock(StoreMiniCartPreview, {}, { isEditor: false })
    expect(hidden.find('.dsf-store-mini-cart__pill').isVisible()).toBe(false)

    const shown = mountBlock(StoreMiniCartPreview, { hideWhenEmpty: false }, { isEditor: false })
    expect(shown.find('.dsf-store-mini-cart__pill').isVisible()).toBe(true)
  })
})

describe('StoreThankyouPreview', () => {
  it('always renders in the editor with heading, message, and hint', () => {
    const w = mountBlock(StoreThankyouPreview, { headingText: 'Woo!', messageText: 'See you soon.' })
    expect(w.find('.dsf-store-thankyou__heading').text()).toBe('Woo!')
    expect(w.find('.dsf-store-thankyou__message').text()).toBe('See you soon.')
    expect(w.find('.dsf-store-thankyou__hint').exists()).toBe(true)
  })

  it('renders on the frontend only on the order-received step', () => {
    const off = mountBlock(StoreThankyouPreview, {}, { isEditor: false })
    expect(off.find('.dsf-store-thankyou').exists()).toBe(false)

    window.dsfFrontendData = { storeContext: { step: 'complete', urls: {}, fragments: [] } }
    const on = mountBlock(StoreThankyouPreview, {}, { isEditor: false })
    expect(on.find('.dsf-store-thankyou').exists()).toBe(true)
    expect(on.find('.dsf-store-thankyou__hint').exists()).toBe(false)
  })
})

describe('ShopFiltersPreview', () => {
  const ARCHIVE = {
    categories: [
      { name: 'Boots', url: '/c/boots', count: 8, current: true },
      { name: 'Socks', url: '/c/socks', count: 3, current: false },
      'bad',
    ],
    priceFilter: { min: '10', max: '', action: '/shop/' },
  }

  it('renders price inputs with current values and category chips', () => {
    const w = mountBlock(ShopFiltersPreview, {}, { shopContext: ARCHIVE })
    expect(w.find('input[name="min_price"]').element.value).toBe('10')
    const chips = w.findAll('.dsf-shop-filters__chip')
    expect(chips).toHaveLength(2)
    expect(chips[0].classes()).toContain('is-current')
    expect(chips[0].text()).toContain('8')
    expect(w.find('.dsf-shop-filters__clear').exists()).toBe(true)
  })

  it('hides pieces per toggles and applies the panel layout', () => {
    const w = mountBlock(ShopFiltersPreview, { showPrice: false, showCounts: false, layout: 'panel' }, { shopContext: ARCHIVE })
    expect(w.find('.dsf-shop-filters__price').exists()).toBe(false)
    expect(w.find('.dsf-shop-filters--panel').exists()).toBe(true)
    expect(w.find('.dsf-shop-filters__chip-count').exists()).toBe(false)
  })
})

describe('SiteLoginPreview', () => {
  it('renders the form with core wp-login field names and hidden redirect', () => {
    window.dsfFrontendData = {
      siteContext: { isLoggedIn: false, loginAction: '/wp-login.php', redirectTo: '/my-page/', lostPasswordUrl: '/lost/', registerUrl: '/register/' },
    }
    const w = mountBlock(SiteLoginPreview, {}, { isEditor: false })
    expect(w.find('form').attributes('action')).toBe('/wp-login.php')
    expect(w.find('input[name="log"]').exists()).toBe(true)
    expect(w.find('input[name="pwd"]').exists()).toBe(true)
    expect(w.find('input[name="redirect_to"]').element.value).toBe('/my-page/')
    expect(w.find('.dsf-site-login__links a').attributes('href')).toBe('/lost/')
  })

  it('shows the signed-in state instead of the form for logged-in visitors', () => {
    window.dsfFrontendData = {
      siteContext: { isLoggedIn: true, user: { displayName: 'Ada', avatarUrl: '' }, logoutUrl: '/logout/' },
    }
    const w = mountBlock(SiteLoginPreview, {}, { isEditor: false })
    expect(w.find('form').exists()).toBe(false)
    expect(w.text()).toContain('Logged in as Ada')
    expect(w.find('.dsf-site-login__submit').attributes('href')).toBe('/logout/')
  })

  it('renders an editor mock with custom heading and remember toggle off', () => {
    const w = mountBlock(SiteLoginPreview, { headingText: 'Members', showRemember: false })
    expect(w.find('.dsf-site-login__heading').text()).toBe('Members')
    expect(w.find('.dsf-site-login__remember').exists()).toBe(false)
  })
})

describe('SiteSearchPreview', () => {
  it('renders results, badges, count, and pagination from the site context', () => {
    window.dsfFrontendData = {
      siteContext: {
        pageId: 9,
        search: {
          query: 'boots', action: '/search/', total: 2, totalPages: 2,
          results: [
            { id: 1, title: 'Alpine Boots', url: '/p/1', type: 'Product', excerpt: 'Great boots.', image: 'a.jpg' },
            { id: 2, title: 'Boot care', url: '/post/2', type: 'Article', excerpt: '', image: '' },
          ],
          pagination: [{ label: '1', url: '/s1', current: true }, { label: '2', url: '/s2', current: false }],
        },
      },
    }
    const w = mountBlock(SiteSearchPreview, {}, { isEditor: false })
    expect(w.find('input[name="s"]').element.value).toBe('boots')
    expect(w.find('input[name="page_id"]').element.value).toBe('9')
    expect(w.findAll('.dsf-site-search__result')).toHaveLength(2)
    expect(w.find('.dsf-site-search__badge').text()).toBe('Product')
    expect(w.find('.dsf-site-search__count').text()).toContain('2 results')
    expect(w.find('.dsf-site-search__page.is-current').text()).toBe('1')
  })

  it('shows a no-results message on the frontend and a hint in the editor', () => {
    window.dsfFrontendData = { siteContext: { pageId: 9, search: { query: 'zzz', action: '/s/', results: [], total: 0, pagination: [] } } }
    const none = mountBlock(SiteSearchPreview, {}, { isEditor: false })
    expect(none.find('.dsf-site-search__empty').text()).toContain('Nothing found')

    delete window.dsfFrontendData
    const editor = mountBlock(SiteSearchPreview)
    expect(editor.find('.dsf-site-search__empty').text()).toContain('render here')
  })
})

describe('UserDashboardPreview', () => {
  it('shows the guest prompt with a sign-in link for logged-out visitors', () => {
    window.dsfFrontendData = { siteContext: { isLoggedIn: false, loginUrl: '/wp-login.php?redirect_to=x' } }
    const w = mountBlock(UserDashboardPreview, { loginPromptText: 'Members only.' }, { isEditor: false })
    expect(w.find('.dsf-user-dashboard__guest-heading').text()).toBe('Members only.')
    expect(w.find('.dsf-user-dashboard__cta').attributes('href')).toContain('wp-login.php')
  })

  it('renders greeting, quick links, and recent orders for members', () => {
    window.dsfFrontendData = {
      siteContext: {
        isLoggedIn: true,
        user: { displayName: 'Ada', avatarUrl: 'a.png' },
        logoutUrl: '/logout/',
        accountUrls: { orders: '/o/', downloads: '', addresses: '/a/', editAccount: '/e/' },
        recentOrders: [{ number: '55', date: 'July 1, 2026', status: 'Completed', total: '<span>$10</span>', url: '/v/55' }],
      },
    }
    const w = mountBlock(UserDashboardPreview, {}, { isEditor: false })
    expect(w.find('.dsf-user-dashboard__name').text()).toBe('Ada')
    const links = w.findAll('.dsf-user-dashboard__link')
    expect(links.map((l) => l.text().replace('→', '').trim())).toEqual(['Orders', 'Addresses', 'Account settings'])
    expect(w.find('.dsf-user-dashboard__order-number').text()).toBe('#55')
    expect(w.find('.dsf-user-dashboard__order-total').html()).toContain('$10')
  })

  it('uses a sample member and sample orders in the editor', () => {
    const w = mountBlock(UserDashboardPreview)
    expect(w.find('.dsf-user-dashboard__name').text()).toBe('Sam Sample')
    expect(w.findAll('.dsf-user-dashboard__order')).toHaveLength(2)
    expect(w.findAll('.dsf-user-dashboard__link')).toHaveLength(4)
  })

  it('hides orders and quick links per toggles', () => {
    const w = mountBlock(UserDashboardPreview, { showOrders: false, showQuickLinks: false })
    expect(w.find('.dsf-user-dashboard__orders').exists()).toBe(false)
    expect(w.find('.dsf-user-dashboard__links').exists()).toBe(false)
  })
})
