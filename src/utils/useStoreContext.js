import { computed, inject, isRef } from 'vue'

export function useStoreContext() {
  const injected = inject('dsfStoreContext', null)

  const store = computed(() => {
    const value = isRef(injected) ? injected.value : injected
    if (value && typeof value === 'object') return value
    if (typeof window !== 'undefined' && window.dsfFrontendData?.storeContext) {
      return window.dsfFrontendData.storeContext
    }
    return { urls: {}, fragments: [] }
  })

  return { store }
}
