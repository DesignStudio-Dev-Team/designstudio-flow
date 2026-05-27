;(function () {
  const wpData = window.dsfFormsFrontendData || {}
  const TRANSITION_DURATION = 230
  const MOUNTED_FLAG = '1'
  const CONDITIONAL_HIDDEN_CLASS = 'dsf-conditional-hidden'

  function getPages(form) {
    return Array.from(form.querySelectorAll('.dsf-form-page'))
  }

  function findActivePage(form) {
    const pages = getPages(form)
    if (!pages.length) return null

    const active = pages.find((page) => page.classList.contains('is-active'))
    return active || pages[0]
  }

  function getCurrentPageIndex(form) {
    const active = findActivePage(form)
    if (!active) return 0
    return Number.parseInt(active.dataset.dsfPageIndex || '0', 10) || 0
  }

  function clearTransitionClasses(page) {
    page.classList.remove(
      'is-enter',
      'is-exit',
      'transition-slide-left',
      'transition-slide-right',
      'transition-slide-up',
      'transition-slide-down',
      'transition-fade',
      'transition-fade-in',
      'transition-zoom'
    )
  }

  function normalizeTransitionName(value) {
    const candidate = String(value || '')
      .trim()
      .toLowerCase()

    if (candidate === 'fade-in') return 'fade'
    if (
      candidate === 'slide-left' ||
      candidate === 'slide-right' ||
      candidate === 'slide-up' ||
      candidate === 'slide-down' ||
      candidate === 'zoom' ||
      candidate === 'fade' ||
      candidate === 'none'
    ) {
      return candidate
    }

    return 'slide-left'
  }

  function reverseTransition(name) {
    if (name === 'slide-left') return 'slide-right'
    if (name === 'slide-right') return 'slide-left'
    if (name === 'slide-up') return 'slide-down'
    if (name === 'slide-down') return 'slide-up'
    return name
  }

  function validateOptionGroup(wrapper) {
    const options = wrapper.querySelectorAll('input[type="checkbox"], input[type="radio"]')
    if (!options.length) return true

    const oneChecked = Array.from(options).some((option) => option.checked)
    if (oneChecked) return true

    options[0].focus()
    return false
  }

  function validatePage(page) {
    if (!page) return true

    const fieldWrappers = Array.from(page.querySelectorAll('.dsf-form-field'))

    for (const wrapper of fieldWrappers) {
      if (wrapper.classList.contains(CONDITIONAL_HIDDEN_CLASS)) continue
      if (wrapper.dataset.requiredGroup === '1' && !validateOptionGroup(wrapper)) {
        return false
      }

      const input = wrapper.querySelector('input, textarea, select')
      if (!input || input.type === 'hidden' || !input.hasAttribute('required')) {
        continue
      }

      if (!input.checkValidity()) {
        input.reportValidity()
        return false
      }
    }

    return true
  }

  // ── Conditional Logic ─────────────────────────────────────────────────────

  function readFieldValueByName(form, name) {
    if (!name) return ''

    const inputs = form.querySelectorAll(
      `[name="${cssEscape(name)}"], [name="${cssEscape(name)}[]"]`
    )
    if (!inputs.length) return ''

    // If the source field's wrapper or page is conditionally hidden, treat its
    // value as empty (matches behavior of Gravity Forms / Typeform).
    const first = inputs[0]
    if (isAncestorConditionallyHidden(first)) return ''

    const isCheckboxGroup = Array.from(inputs).every((el) => el.type === 'checkbox')
    if (isCheckboxGroup && inputs.length > 1) {
      return Array.from(inputs)
        .filter((el) => el.checked)
        .map((el) => el.value)
    }

    if (first.type === 'radio') {
      const checked = Array.from(inputs).find((el) => el.checked)
      return checked ? checked.value : ''
    }

    if (first.type === 'checkbox') {
      return first.checked ? first.value : ''
    }

    return first.value || ''
  }

  function isAncestorConditionallyHidden(el) {
    let node = el
    while (node && node !== document) {
      if (node.classList && node.classList.contains(CONDITIONAL_HIDDEN_CLASS)) return true
      node = node.parentNode
    }
    return false
  }

  function cssEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(value)
    return String(value).replace(/[^a-zA-Z0-9_-]/g, (c) => '\\' + c)
  }

  function evaluateRule(rule, form) {
    if (!rule || !rule.fieldName) return false
    const actual = readFieldValueByName(form, rule.fieldName)
    const expected = rule.value == null ? '' : String(rule.value)
    const op = rule.operator || 'equals'

    const actualArr = Array.isArray(actual) ? actual : [actual]
    const actualStr = Array.isArray(actual) ? actual.join(',') : String(actual)

    switch (op) {
      case 'equals':
        return Array.isArray(actual) ? actual.includes(expected) : actualStr === expected
      case 'not_equals':
        return Array.isArray(actual) ? !actual.includes(expected) : actualStr !== expected
      case 'contains':
        return Array.isArray(actual)
          ? actual.includes(expected)
          : actualStr.toLowerCase().includes(expected.toLowerCase())
      case 'not_contains':
        return Array.isArray(actual)
          ? !actual.includes(expected)
          : !actualStr.toLowerCase().includes(expected.toLowerCase())
      case 'is_empty':
        return actualArr.every((v) => String(v).trim() === '')
      case 'is_not_empty':
        return actualArr.some((v) => String(v).trim() !== '')
      case 'greater_than':
        return Number(actualStr) > Number(expected)
      case 'less_than':
        return Number(actualStr) < Number(expected)
      default:
        return false
    }
  }

  function evaluateLogic(logic, form) {
    if (!logic || !Array.isArray(logic.rules) || !logic.rules.length) return true
    const results = logic.rules.map((rule) => evaluateRule(rule, form))
    return logic.logicType === 'any' ? results.some(Boolean) : results.every(Boolean)
  }

  function applyConditionalVisibility(form) {
    // Iterate in document order so source values resolve against the latest
    // visibility state of upstream fields/pages.
    const nodes = form.querySelectorAll('[data-dsf-conditional]')
    nodes.forEach((node) => {
      let logic
      try {
        logic = JSON.parse(node.dataset.dsfConditional || 'null')
      } catch (_) {
        logic = null
      }
      if (!logic) return

      const matches = evaluateLogic(logic, form)
      const shouldShow = logic.action === 'hide' ? !matches : matches
      node.classList.toggle(CONDITIONAL_HIDDEN_CLASS, !shouldShow)
    })
  }

  function findNextVisiblePageIndex(form, fromIndex, direction) {
    const pages = getPages(form)
    const step = direction === 'forward' ? 1 : -1
    let i = fromIndex + step
    while (i >= 0 && i < pages.length) {
      if (!pages[i].classList.contains(CONDITIONAL_HIDDEN_CLASS)) return i
      i += step
    }
    return -1
  }

  function updateProgress(form) {
    const wrap = form.closest('.dsf-form-wrap') || form.parentNode
    const progress = wrap ? wrap.querySelector('[data-dsf-progress]') : null
    if (!progress) return

    const pages = getPages(form)
    const visiblePages = pages.filter((p) => !p.classList.contains(CONDITIONAL_HIDDEN_CLASS))
    const total = visiblePages.length || pages.length
    if (!total) return

    const active = findActivePage(form)
    let currentVisibleIdx = active ? visiblePages.indexOf(active) : -1
    if (currentVisibleIdx === -1) currentVisibleIdx = 0

    const step = currentVisibleIdx + 1
    const percent = Math.round((step / total) * 100)

    const bar = progress.querySelector('[data-dsf-progress-bar]')
    const currentEl = progress.querySelector('[data-dsf-progress-current]')
    const totalEl = progress.querySelector('[data-dsf-progress-total]')
    const percentEl = progress.querySelector('[data-dsf-progress-percent]')

    if (bar) bar.style.width = percent + '%'
    if (currentEl) currentEl.textContent = String(step)
    if (totalEl) totalEl.textContent = String(total)
    if (percentEl) percentEl.textContent = percent + '%'

    progress.setAttribute('aria-valuemin', '1')
    progress.setAttribute('aria-valuemax', String(total))
    progress.setAttribute('aria-valuenow', String(step))
  }

  function stripHiddenFieldsFromFormData(form, formData) {
    const hiddenNames = new Set()
    form
      .querySelectorAll(
        `.${CONDITIONAL_HIDDEN_CLASS} input[name], .${CONDITIONAL_HIDDEN_CLASS} textarea[name], .${CONDITIONAL_HIDDEN_CLASS} select[name]`
      )
      .forEach((el) => {
        if (el.name) hiddenNames.add(el.name)
      })
    hiddenNames.forEach((name) => formData.delete(name))
  }


  function switchToPage(form, targetIndex, direction) {
    const pages = getPages(form)
    if (!pages.length || targetIndex < 0 || targetIndex >= pages.length) {
      return
    }

    const currentPage = findActivePage(form)
    const currentIndex = getCurrentPageIndex(form)

    if (!currentPage || targetIndex === currentIndex) {
      return
    }

    const targetPage = pages[targetIndex]
    const transition =
      direction === 'forward'
        ? normalizeTransitionName(currentPage.dataset.dsfNextTransition)
        : reverseTransition(normalizeTransitionName(targetPage.dataset.dsfNextTransition))

    if (transition === 'none') {
      currentPage.hidden = true
      currentPage.classList.remove('is-active')
      targetPage.hidden = false
      targetPage.classList.add('is-active')
      clearTransitionClasses(currentPage)
      clearTransitionClasses(targetPage)
      updateProgress(form)
      return
    }

    targetPage.hidden = false
    targetPage.classList.add('is-active', 'is-enter', `transition-${transition}`)
    currentPage.classList.add('is-exit', `transition-${transition}`)
    updateProgress(form)

    window.setTimeout(() => {
      currentPage.hidden = true
      currentPage.classList.remove('is-active')
      clearTransitionClasses(currentPage)
      targetPage.classList.remove('is-enter')
      clearTransitionClasses(targetPage)
    }, TRANSITION_DURATION)
  }

  function resetToFirstPage(form) {
    const pages = getPages(form)
    if (!pages.length) return

    pages.forEach((page, index) => {
      clearTransitionClasses(page)
      if (index === 0) {
        page.hidden = false
        page.classList.add('is-active')
      } else {
        page.hidden = true
        page.classList.remove('is-active')
      }
    })

    updateProgress(form)
  }

  function setMessage(form, message, isError) {
    const messageNode = form.querySelector('.dsf-form-message')
    if (!messageNode) return

    messageNode.textContent = message || ''
    messageNode.classList.toggle('dsf-form-message--error', Boolean(isError))
  }

  function getRecaptchaConfig() {
    const recaptcha = wpData.recaptcha || {}
    if (!recaptcha.enabled || !recaptcha.siteKey) return null

    return {
      siteKey: recaptcha.siteKey,
      action: recaptcha.action || 'dsf_form_submit'
    }
  }

  function executeRecaptcha(config) {
    if (!config) return Promise.resolve('')

    if (typeof window.grecaptcha === 'undefined') {
      return Promise.reject(new Error('reCAPTCHA is not available. Please reload and try again.'))
    }

    return new Promise((resolve, reject) => {
      window.grecaptcha.ready(() => {
        window.grecaptcha
          .execute(config.siteKey, { action: config.action })
          .then((token) => resolve(token || ''))
          .catch(() => reject(new Error('reCAPTCHA verification failed. Please try again.')))
      })
    })
  }

  async function submitToServer(form) {
    if (!wpData.ajaxUrl || !wpData.nonce) {
      throw new Error('Form submit endpoint is not configured.')
    }

    const recaptchaConfig = getRecaptchaConfig()
    const formData = new FormData(form)
    stripHiddenFieldsFromFormData(form, formData)
    formData.append('action', 'dsf_submit_form')
    formData.append('nonce', wpData.nonce)

    if (recaptchaConfig) {
      const token = await executeRecaptcha(recaptchaConfig)
      formData.append('recaptcha_token', token)
      formData.append('recaptcha_action', recaptchaConfig.action)
    }

    const response = await fetch(wpData.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    })

    const json = await response.json()
    if (!json || !json.success) {
      throw new Error((json && json.data && json.data.message) || 'Unable to submit the form.')
    }

    return json.data || {}
  }

  function lockFormButtons(form, isLoading) {
    const buttons = form.querySelectorAll('button')
    buttons.forEach((button) => {
      button.disabled = isLoading
    })
  }

  function mountForm(form) {
    if (form.dataset.dsfMounted === MOUNTED_FLAG) {
      return
    }
    form.dataset.dsfMounted = MOUNTED_FLAG

    const pages = getPages(form)
    if (!pages.length) return

    pages.forEach((page, index) => {
      page.dataset.dsfPageIndex = String(index)
      page.hidden = index !== 0
      if (index === 0) {
        page.classList.add('is-active')
      } else {
        page.classList.remove('is-active')
      }
    })

    form.addEventListener('click', (event) => {
      const button = event.target.closest('[data-dsf-nav]')
      if (!button) return

      const direction = button.dataset.dsfNav
      const currentIndex = getCurrentPageIndex(form)
      const currentPage = findActivePage(form)

      if ('next' === direction) {
        if (!validatePage(currentPage)) return
        const target = findNextVisiblePageIndex(form, currentIndex, 'forward')
        if (target !== -1) switchToPage(form, target, 'forward')
      }

      if ('prev' === direction) {
        const target = findNextVisiblePageIndex(form, currentIndex, 'backward')
        if (target !== -1) switchToPage(form, target, 'backward')
      }
    })

    const handleConditionalChange = () => {
      applyConditionalVisibility(form)
      updateProgress(form)
    }
    form.addEventListener('input', handleConditionalChange)
    form.addEventListener('change', handleConditionalChange)

    // Initial evaluation after the form mounts. If the first page is hidden,
    // advance to the next visible page so the user doesn't see an empty form.
    applyConditionalVisibility(form)
    if (findActivePage(form)?.classList.contains(CONDITIONAL_HIDDEN_CLASS)) {
      const firstVisible = findNextVisiblePageIndex(form, -1, 'forward')
      if (firstVisible !== -1) switchToPage(form, firstVisible, 'forward')
    }
    updateProgress(form)

    form.addEventListener('submit', async (event) => {
      event.preventDefault()
      const currentPage = findActivePage(form)
      if (!validatePage(currentPage)) {
        return
      }

      setMessage(form, '', false)
      lockFormButtons(form, true)

      try {
        const data = await submitToServer(form)
        if (data.redirectUrl) {
          window.location.href = data.redirectUrl
          return
        }
        setMessage(
          form,
          data.message || form.dataset.dsfSuccessMessage || 'Thanks! Your form has been submitted.',
          false
        )
        form.reset()
        resetToFirstPage(form)
      } catch (error) {
        setMessage(form, error.message || 'Unable to submit the form.', true)
      } finally {
        lockFormButtons(form, false)
      }
    })
  }

  function init(root = document) {
    if (!root || typeof root.querySelectorAll !== 'function') return

    const forms = root.querySelectorAll('.dsf-form')
    if (!forms.length) return

    forms.forEach((form) => mountForm(form))
  }

  function watchForInjectedForms() {
    if (typeof MutationObserver === 'undefined' || !document.body) {
      return
    }

    const observer = new MutationObserver((mutations) => {
      for (const mutation of mutations) {
        if (!mutation.addedNodes?.length) continue

        mutation.addedNodes.forEach((node) => {
          if (!node || node.nodeType !== 1) return
          if (typeof node.matches === 'function' && node.matches('.dsf-form')) {
            mountForm(node)
            return
          }
          init(node)
        })
      }
    })

    observer.observe(document.body, { childList: true, subtree: true })
  }

  function boot() {
    init(document)
    watchForInjectedForms()
    window.dsfInitForms = init
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot)
  } else {
    boot()
  }
})()
