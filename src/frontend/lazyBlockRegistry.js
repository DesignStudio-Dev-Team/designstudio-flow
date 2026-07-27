import { defineAsyncComponent } from 'vue'

// Keep block implementations out of the initial frontend chunk. The loader
// map is deliberately explicit so Vite can emit stable, cacheable chunks.
const loaders = {
  content: () => import('../components/blocks/ContentPreview.vue'),
  faq: () => import('../components/blocks/FaqPreview.vue'),
  breadcrumbs: () => import('../components/blocks/BreadcrumbsPreview.vue'),
  hero: () => import('../components/blocks/HeroCenteredPreview.vue'),
  'product-grid': () => import('../components/blocks/ProductGridPreview.vue'),
  'ecommerce-showcase': () => import('../components/blocks/EcommerceShowcasePreview.vue'),
  'features-grid': () => import('../components/blocks/FeaturesGridPreview.vue'),
  'card-columns': () => import('../components/blocks/CardColumnsPreview.vue'),
  'anchor-gallery': () => import('../components/blocks/AnchorGalleryPreview.vue'),
  'bento-hero': () => import('../components/blocks/BentoHeroPreview.vue'),
  'spotlight-hero': () => import('../components/blocks/SpotlightHeroPreview.vue'),
  'expander-hero': () => import('../components/blocks/ExpanderHeroPreview.vue'),
  pricing: () => import('../components/blocks/PricingPreview.vue'),
  'pricing-tables': () => import('../components/blocks/PricingTablesPreview.vue'),
  'text-image': () => import('../components/blocks/TextImagePreview.vue'),
  'feature-image-cta': () => import('../components/blocks/FeatureImageCtaPreview.vue'),
  testimonials: () => import('../components/blocks/TestimonialsPreview.vue'),
  'cta-banner': () => import('../components/blocks/CtaBannerPreview.vue'),
  countdown: () => import('../components/blocks/CountdownPreview.vue'),
  newsletter: () => import('../components/blocks/NewsletterPreview.vue'),
  'brand-carousel': () => import('../components/blocks/BrandLogosPreview.vue'),
  'promo-banner': () => import('../components/blocks/PromoBannerPreview.vue'),
  'featured-product-banner': () => import('../components/blocks/FeaturedProductBannerPreview.vue'),
  'product-summary': () => import('../components/blocks/ProductSummaryPreview.vue'),
  'product-gallery': () => import('../components/blocks/ProductGalleryPreview.vue'),
  'product-description': () => import('../components/blocks/ProductDescriptionPreview.vue'),
  'product-specs': () => import('../components/blocks/ProductSpecsPreview.vue'),
  'product-tabs': () => import('../components/blocks/ProductTabsPreview.vue'),
  'tabbed-product-showcase': () => import('../components/blocks/TabbedProductShowcasePreview.vue'),
  'image-logo-grid': () => import('../components/blocks/ImageLogoGridPreview.vue'),
  'brand-showcase-grid': () => import('../components/blocks/BrandShowcaseGridPreview.vue'),
  'product-add-to-cart': () => import('../components/blocks/ProductAddToCartPreview.vue'),
  'product-hero': () => import('../components/blocks/ProductHeroPreview.vue'),
  'product-details-split': () => import('../components/blocks/ProductDetailsSplitPreview.vue'),
  'product-highlights': () => import('../components/blocks/ProductHighlightsPreview.vue'),
  'product-related': () => import('../components/blocks/ProductRelatedPreview.vue'),
  'product-spotlight': () => import('../components/blocks/ProductSpotlightPreview.vue'),
  'product-upsells': () => import('../components/blocks/ProductUpsellsPreview.vue'),
  'product-reviews': () => import('../components/blocks/ProductReviewsPreview.vue'),
  'product-meta': () => import('../components/blocks/ProductMetaPreview.vue'),
  'store-cart': () => import('../components/blocks/StoreCartPreview.vue'),
  'store-checkout': () => import('../components/blocks/StoreCheckoutPreview.vue'),
  'store-account': () => import('../components/blocks/StoreAccountPreview.vue'),
  'store-login': () => import('../components/blocks/StoreLoginPreview.vue'),
  'store-steps': () => import('../components/blocks/StoreStepsPreview.vue'),
  'shop-header': () => import('../components/blocks/ShopHeaderPreview.vue'),
  'shop-category-hero': () => import('../components/blocks/ShopCategoryHeroPreview.vue'),
  'shop-subcategory-grid': () => import('../components/blocks/ShopSubcategoryGridPreview.vue'),
  'shop-products': () => import('../components/blocks/ShopProductsPreview.vue'),
  'shop-filters': () => import('../components/blocks/ShopFiltersPreview.vue'),
  'store-mini-cart': () => import('../components/blocks/StoreMiniCartPreview.vue'),
  'store-thankyou': () => import('../components/blocks/StoreThankyouPreview.vue'),
  'site-login': () => import('../components/blocks/SiteLoginPreview.vue'),
  'site-search': () => import('../components/blocks/SiteSearchPreview.vue'),
  'user-dashboard': () => import('../components/blocks/UserDashboardPreview.vue'),
  'blog-header': () => import('../components/blocks/BlogHeaderPreview.vue'),
  'post-loop': () => import('../components/blocks/PostLoopPreview.vue'),
  'duo-hero': () => import('../components/blocks/DuoHeroPreview.vue'),
  'featured-promo-banner': () => import('../components/blocks/FeaturedPromoBannerPreview.vue'),
  'header-mega-menu': () => import('../components/blocks/HeaderMegaMenuPreview.vue'),
  'header-showcase-mega': () => import('../components/blocks/HeaderShowcaseMegaPreview.vue'),
  'header-cutout-mega': () => import('../components/blocks/HeaderCutoutMegaPreview.vue'),
  'header-modern-mega': () => import('../components/blocks/HeaderModernMegaPreview.vue'),
  'footer-dealers': () => import('../components/blocks/FooterDealersPreview.vue'),
  'footer-commerce': () => import('../components/blocks/FooterCommercePreview.vue'),
  'form-embed': () => import('../components/blocks/FormEmbedPreview.vue'),
  'form-with-content': () => import('../components/blocks/FormWithContentPreview.vue'),
  'landing-progress-header': () => import('../components/blocks/LandingProgressHeaderPreview.vue'),
  'landing-dock-header': () => import('../components/blocks/LandingDockHeaderPreview.vue'),
  'landing-hero': () => import('../components/blocks/LandingHeroPreview.vue'),
  'landing-showcase-hero': () => import('../components/blocks/LandingShowcaseHeroPreview.vue'),
  'landing-block-explorer': () => import('../components/blocks/LandingBlockExplorerPreview.vue'),
  'landing-block-ready': () => import('../components/blocks/LandingBlockReadyPreview.vue'),
  'steps-image': () => import('../components/blocks/StepsImagePreview.vue'),
  'landing-product-story': () => import('../components/blocks/LandingProductStoryPreview.vue'),
  'landing-trust-workflow': () => import('../components/blocks/LandingTrustWorkflowPreview.vue'),
  'landing-engagement-suite': () => import('../components/blocks/LandingEngagementSuitePreview.vue'),
  'landing-redirect-tool': () => import('../components/blocks/LandingRedirectToolPreview.vue'),
  'landing-mail-tool': () => import('../components/blocks/LandingMailToolPreview.vue'),
  'landing-marketing-footer': () => import('../components/blocks/LandingMarketingFooterPreview.vue'),
}

const components = Object.fromEntries(Object.entries(loaders).map(([type, loader]) => [
  type,
  defineAsyncComponent({ loader, suspensible: false, delay: 0, timeout: 15000 }),
]))

export function getLazyPreviewComponent(type) {
  return components[type] || null
}

export function preloadPreviewComponents(blocks = []) {
  const uniqueTypes = [...new Set((Array.isArray(blocks) ? blocks : []).map((block) => block?.type).filter((type) => loaders[type]))]
  return Promise.all(uniqueTypes.map((type) => loaders[type]()))
}

export const lazyPreviewTypes = Object.freeze(Object.keys(loaders))
