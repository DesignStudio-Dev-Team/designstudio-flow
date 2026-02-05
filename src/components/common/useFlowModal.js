import { inject, provide } from 'vue'

const MODAL_KEY = Symbol('dsf-flow-modal')

export function provideFlowModal(api) {
  provide(MODAL_KEY, api)
}

export function useFlowModal() {
  return inject(MODAL_KEY, {
    openModal: () => {},
    closeModal: () => {},
  })
}
