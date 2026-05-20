;(function () {
  const wpData = window.dsfFormsBuilderData || {}

  const FIELD_LIBRARY = [
    { type: 'single_line_text', label: 'Single Line Text', icon: 'dashicons-editor-textcolor' },
    { type: 'paragraph_text', label: 'Paragraph Text', icon: 'dashicons-editor-paragraph' },
    { type: 'checkboxes', label: 'Checkboxes', icon: 'dashicons-yes-alt' },
    { type: 'radio_buttons', label: 'Radio Buttons', icon: 'dashicons-marker' },
    { type: 'drop_down', label: 'Drop Down', icon: 'dashicons-arrow-down-alt2' },
    { type: 'number', label: 'Number', icon: 'dashicons-editor-ol' },
    { type: 'phone', label: 'Phone', icon: 'dashicons-phone' },
    { type: 'date', label: 'Date', icon: 'dashicons-calendar-alt' },
    { type: 'email', label: 'Email', icon: 'dashicons-email-alt' },
    { type: 'website', label: 'Website', icon: 'dashicons-admin-site-alt3' },
    { type: 'file_upload', label: 'File Upload', icon: 'dashicons-upload' },
    { type: 'html', label: 'HTML', icon: 'dashicons-editor-code' },
    { type: 'hidden', label: 'Hidden', icon: 'dashicons-hidden' },
    { type: 'page_break', label: 'Page Break', icon: 'dashicons-controls-pause' }
  ]

  const OPTION_FIELD_TYPES = new Set(['checkboxes', 'radio_buttons', 'drop_down'])
  const PLACEHOLDER_FIELD_TYPES = new Set([
    'single_line_text',
    'paragraph_text',
    'number',
    'phone',
    'email',
    'website',
    'date'
  ])
  const DEFAULT_VALUE_FIELD_TYPES = new Set([
    'single_line_text',
    'number',
    'phone',
    'date',
    'email',
    'website',
    'hidden'
  ])
  const CONNECTION_TYPES = ['webhook', 'salesforce']
  const PAGE_BREAK_ANIMATIONS = new Set([
    'slide-left',
    'slide-right',
    'slide-up',
    'slide-down',
    'zoom',
    'fade',
    'none'
  ])
  const DEFAULT_OPTIONS = ['Option 1', 'Option 2']
  const NON_SPLIT_FIELD_TYPES = new Set(['page_break', 'hidden'])

  const state = {
    formId: Number.parseInt(wpData.formId || '0', 10) || 0,
    rows: normalizeRows(wpData.rows || []),
    settings: normalizeSettings(wpData.settings || {}),
    entriesCount: Number.parseInt(wpData.entriesCount || '0', 10) || 0,
    selectedFieldId: null,
    dragPayload: null,
    activeTab: 'build'
  }

  const refs = {
    app: document.getElementById('dsf-forms-builder'),
    titleInput: document.getElementById('dsf-form-title'),
    tabs: Array.from(document.querySelectorAll('.dsf-top-tab')),
    panes: Array.from(document.querySelectorAll('.dsf-pane')),
    saveButton: document.getElementById('dsf-form-save'),
    backButton: document.getElementById('dsf-form-back'),
    shortcode: document.getElementById('dsf-form-shortcode'),
    canvas: document.getElementById('dsf-form-canvas'),
    libraryList: document.getElementById('dsf-field-library-list'),
    sidebarStack: document.querySelector('.dsf-sidebar-stack'),
    drawer: document.getElementById('dsf-edit-drawer'),
    drawerTitle: document.getElementById('dsf-edit-drawer-title'),
    drawerBody: document.getElementById('dsf-edit-drawer-body'),
    drawerClose: document.getElementById('dsf-close-drawer'),
    submitLabel: document.getElementById('dsf-setting-submit-label'),
    nextLabel: document.getElementById('dsf-setting-next-label'),
    previousLabel: document.getElementById('dsf-setting-previous-label'),
    successMessage: document.getElementById('dsf-setting-success-message'),
    entriesTotal: document.getElementById('dsf-entries-total'),
    viewEntriesButton: document.getElementById('dsf-view-entries'),
    sendAdminNotifications: document.getElementById('dsf-setting-send-admin-notifications'),
    addAdminEmail: document.getElementById('dsf-add-admin-email'),
    adminEmailsList: document.getElementById('dsf-admin-emails-list'),
    adminEmailsEmpty: document.getElementById('dsf-admin-emails-empty'),
    notificationSubject: document.getElementById('dsf-setting-notification-subject'),
    notificationIntro: document.getElementById('dsf-setting-notification-intro'),
    sendSubmitterCopy: document.getElementById('dsf-setting-send-submitter-copy'),
    confirmationType: document.getElementById('dsf-setting-confirmation-type'),
    confirmationMessageWrap: document.getElementById('dsf-confirmation-message-wrap'),
    confirmationMessage: document.getElementById('dsf-setting-confirmation-message'),
    redirectUrl: document.getElementById('dsf-setting-redirect-url'),
    confirmationUrlWrap: document.getElementById('dsf-confirmation-url-wrap'),
    addConnection: document.getElementById('dsf-add-connection'),
    connectionsList: document.getElementById('dsf-connections-list')
  }

  if (!refs.app || !refs.canvas || !refs.libraryList) {
    return
  }

  init()

  function init() {
    bindTopTabs()
    bindSettingsInputs()
    bindEntriesEvents()
    bindNotificationEvents()
    bindConfirmationEvents()
    bindConnectionEvents()
    bindDrawerEvents()
    bindSaveButton()
    bindBackButton()
    bindTitleInput()
    bindCanvasDropSurface()
    renderLibrary()
    renderSettings()
    renderEntries()
    renderNotifications()
    renderConfirmations()
    renderConnections()
    renderCanvas()
    renderDrawer()
    renderShortcode()
  }

  function normalizeRows(rows) {
    if (!Array.isArray(rows)) return []

    return rows
      .map((row) => {
        if (!row || !Array.isArray(row.fields)) return null

        const fields = row.fields
          .map((field) => normalizeField(field))
          .filter(Boolean)
          .slice(0, 2)

        if (!fields.length) return null

        if (fields.length === 2 && fields.some((field) => field.type === 'page_break')) {
          const pageBreak = fields.find((field) => field.type === 'page_break')
          return {
            id: row.id || uid('row'),
            fields: [{ ...pageBreak, width: 'full' }]
          }
        }

        if (fields.length === 1) {
          if (fields[0].type === 'page_break' || fields[0].type === 'hidden') {
            fields[0].width = 'full'
          } else {
            fields[0].width = fields[0].width === 'half' ? 'half' : 'full'
          }
        } else {
          fields[0].width = 'half'
          fields[1].width = 'half'
        }

        return {
          id: row.id || uid('row'),
          fields
        }
      })
      .filter(Boolean)
  }

  function normalizeSettings(settings) {
    const adminEmails = Array.isArray(settings.adminEmails)
      ? settings.adminEmails
          .map((email) => String(email || '').trim())
          .filter((email) => email.length > 0)
      : []
    const connectionDefaults =
      Array.isArray(settings.connections) && settings.connections.length
        ? settings.connections.map((connection, index) => normalizeConnection(connection, index)).filter(Boolean)
        : [createConnection()]

    return {
      submitLabel: typeof settings.submitLabel === 'string' && settings.submitLabel ? settings.submitLabel : 'Submit',
      nextLabel: typeof settings.nextLabel === 'string' && settings.nextLabel ? settings.nextLabel : 'Next',
      previousLabel:
        typeof settings.previousLabel === 'string' && settings.previousLabel
          ? settings.previousLabel
          : 'Previous',
      successMessage:
        typeof settings.successMessage === 'string' && settings.successMessage
          ? settings.successMessage
          : 'Thanks! Your form has been submitted.',
      sendAdminNotifications:
        typeof settings.sendAdminNotifications === 'boolean'
          ? settings.sendAdminNotifications
          : true,
      adminEmails,
      notificationSubject:
        typeof settings.notificationSubject === 'string' && settings.notificationSubject
          ? settings.notificationSubject
          : 'New form submission - {form_title}',
      notificationIntro:
        typeof settings.notificationIntro === 'string' ? settings.notificationIntro : '',
      sendSubmitterCopy:
        typeof settings.sendSubmitterCopy === 'boolean' ? settings.sendSubmitterCopy : false,
      confirmationType:
        settings.confirmationType === 'redirect_url' || settings.confirmationType === 'message'
          ? settings.confirmationType
          : 'message',
      confirmationMessage:
        typeof settings.confirmationMessage === 'string' && settings.confirmationMessage
          ? settings.confirmationMessage
          : 'Thanks! Your form was submitted successfully.',
      redirectUrl: typeof settings.redirectUrl === 'string' ? settings.redirectUrl : '',
      connections: connectionDefaults.length ? connectionDefaults : [createConnection()]
    }
  }

  function normalizePageBreakAnimation(value) {
    const normalized = value === 'fade-in' ? 'fade' : String(value || '').trim()
    return PAGE_BREAK_ANIMATIONS.has(normalized) ? normalized : 'slide-left'
  }

  function normalizeConnection(connection, index) {
    if (!connection || typeof connection !== 'object') return null

    const type = CONNECTION_TYPES.includes(connection.type) ? connection.type : 'webhook'
    const timeout = Number.parseInt(connection.timeout || '8', 10)

    return {
      id: typeof connection.id === 'string' && connection.id ? connection.id : uid(`connection-${index}`),
      enabled: Boolean(connection.enabled),
      type,
      label: typeof connection.label === 'string' ? connection.label : '',
      endpointUrl: typeof connection.endpointUrl === 'string' ? connection.endpointUrl : '',
      secret: typeof connection.secret === 'string' ? connection.secret : '',
      timeout: Number.isFinite(timeout) && timeout > 0 ? timeout : 8
    }
  }

  function createConnection() {
    return {
      id: uid('connection'),
      enabled: false,
      type: 'webhook',
      label: 'Webhook',
      endpointUrl: '',
      secret: '',
      timeout: 8
    }
  }

  function normalizeField(field) {
    if (!field || typeof field !== 'object') return null

    const originalType = String(field.type || '').trim()
    const type = originalType === 'multiple_choice' ? 'radio_buttons' : originalType
    const libraryItem = FIELD_LIBRARY.find((item) => item.type === type)
    if (!libraryItem) return null

    const defaults = createField(type)

    return {
      ...defaults,
      id: typeof field.id === 'string' && field.id ? field.id : defaults.id,
      label: typeof field.label === 'string' && field.label ? field.label : defaults.label,
      name: typeof field.name === 'string' && field.name ? field.name : defaults.name,
      width: field.width === 'half' ? 'half' : defaults.width,
      required: Boolean(field.required),
      placeholder: typeof field.placeholder === 'string' ? field.placeholder : defaults.placeholder,
      defaultValue: typeof field.defaultValue === 'string' ? field.defaultValue : defaults.defaultValue,
      helpText: typeof field.helpText === 'string' ? field.helpText : defaults.helpText,
      options: Array.isArray(field.options)
        ? field.options.map((value) => String(value)).filter((value) => value.trim().length > 0)
        : defaults.options,
      html: typeof field.html === 'string' ? field.html : defaults.html,
      pageBreakAnimation: normalizePageBreakAnimation(field.pageBreakAnimation)
    }
  }

  function createField(type) {
    const libraryItem = FIELD_LIBRARY.find((item) => item.type === type)
    const label = libraryItem ? libraryItem.label : 'Field'
    const seed = uid('field')

    return {
      id: seed,
      type,
      label,
      name: toFieldName(`${label}_${seed}`),
      width: 'full',
      required: false,
      placeholder: '',
      defaultValue: '',
      helpText: '',
      options: OPTION_FIELD_TYPES.has(type) ? [...DEFAULT_OPTIONS] : [],
      html: type === 'html' ? '<p>Custom HTML block</p>' : '',
      pageBreakAnimation: 'slide-left'
    }
  }

  function toFieldName(value) {
    return String(value)
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '')
      .slice(0, 64) || `field_${uid('x')}`
  }

  function uid(prefix) {
    return `${prefix}-${Math.random().toString(36).slice(2, 9)}${Date.now().toString(36)}`
  }

  function bindTopTabs() {
    refs.tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        state.activeTab = tab.dataset.tab || 'build'
        refs.tabs.forEach((node) => node.classList.toggle('is-active', node === tab))
        refs.panes.forEach((pane) =>
          pane.classList.toggle('is-active', pane.dataset.pane === state.activeTab)
        )
      })
    })
  }

  function bindTitleInput() {
    if (!refs.titleInput) return

    refs.titleInput.addEventListener('blur', () => {
      if (!refs.titleInput.value.trim()) {
        refs.titleInput.value = 'Untitled Form'
      }
    })
  }

  function bindSettingsInputs() {
    const mapping = [
      ['submitLabel', refs.submitLabel],
      ['nextLabel', refs.nextLabel],
      ['previousLabel', refs.previousLabel],
      ['successMessage', refs.successMessage]
    ]

    mapping.forEach(([key, input]) => {
      if (!input) return
      input.addEventListener('input', () => {
        state.settings[key] = input.value
      })
    })
  }

  function renderSettings() {
    if (refs.submitLabel) refs.submitLabel.value = state.settings.submitLabel
    if (refs.nextLabel) refs.nextLabel.value = state.settings.nextLabel
    if (refs.previousLabel) refs.previousLabel.value = state.settings.previousLabel
    if (refs.successMessage) refs.successMessage.value = state.settings.successMessage
  }

  function renderEntries() {
    if (refs.entriesTotal) {
      refs.entriesTotal.textContent = String(state.entriesCount)
    }
  }

  function bindEntriesEvents() {
    if (!refs.viewEntriesButton) return

    refs.viewEntriesButton.addEventListener('click', () => {
      if (wpData.adminListUrl) {
        window.location.href = String(wpData.adminListUrl)
      }
    })
  }

  function bindNotificationEvents() {
    if (refs.sendAdminNotifications) {
      refs.sendAdminNotifications.addEventListener('change', (event) => {
        state.settings.sendAdminNotifications = Boolean(event.target.checked)
      })
    }

    if (refs.addAdminEmail) {
      refs.addAdminEmail.addEventListener('click', () => {
        state.settings.adminEmails.push('')
        renderNotifications()
      })
    }

    if (refs.adminEmailsList) {
      refs.adminEmailsList.addEventListener('input', (event) => {
        if (event.target.dataset.setting !== 'admin-email') return
        const index = Number.parseInt(event.target.dataset.emailIndex || '-1', 10)
        if (index < 0) return
        state.settings.adminEmails[index] = event.target.value
      })

      refs.adminEmailsList.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="remove-admin-email"]')
        if (!button) return
        const index = Number.parseInt(button.dataset.emailIndex || '-1', 10)
        if (index < 0) return
        state.settings.adminEmails.splice(index, 1)
        renderNotifications()
      })
    }

    if (refs.notificationSubject) {
      refs.notificationSubject.addEventListener('input', (event) => {
        state.settings.notificationSubject = event.target.value
      })
    }

    if (refs.notificationIntro) {
      refs.notificationIntro.addEventListener('input', (event) => {
        state.settings.notificationIntro = event.target.value
      })
    }

    if (refs.sendSubmitterCopy) {
      refs.sendSubmitterCopy.addEventListener('change', (event) => {
        state.settings.sendSubmitterCopy = Boolean(event.target.checked)
      })
    }
  }

  function renderNotifications() {
    if (refs.sendAdminNotifications) {
      refs.sendAdminNotifications.checked = Boolean(state.settings.sendAdminNotifications)
    }
    if (refs.notificationSubject) {
      refs.notificationSubject.value = state.settings.notificationSubject || ''
    }
    if (refs.notificationIntro) {
      refs.notificationIntro.value = state.settings.notificationIntro || ''
    }
    if (refs.sendSubmitterCopy) {
      refs.sendSubmitterCopy.checked = Boolean(state.settings.sendSubmitterCopy)
    }

    if (refs.adminEmailsList) {
      refs.adminEmailsList.innerHTML = ''
      state.settings.adminEmails.forEach((email, index) => {
        const row = document.createElement('div')
        row.className = 'dsf-repeat-row'
        row.innerHTML = `
          <input type="email" data-setting="admin-email" data-email-index="${index}" value="${escapeHtml(email || '')}" placeholder="admin@example.com">
          <button type="button" class="dsf-link-btn dsf-link-btn--danger" data-action="remove-admin-email" data-email-index="${index}">Remove</button>
        `
        refs.adminEmailsList.appendChild(row)
      })
    }

    if (refs.adminEmailsEmpty) {
      refs.adminEmailsEmpty.hidden = state.settings.adminEmails.length > 0
    }
  }

  function bindConfirmationEvents() {
    if (refs.confirmationType) {
      refs.confirmationType.addEventListener('change', (event) => {
        state.settings.confirmationType = event.target.value === 'redirect_url' ? 'redirect_url' : 'message'
        renderConfirmations()
      })
    }

    if (refs.confirmationMessage) {
      refs.confirmationMessage.addEventListener('input', (event) => {
        state.settings.confirmationMessage = event.target.value
        state.settings.successMessage = event.target.value
      })
    }

    if (refs.redirectUrl) {
      refs.redirectUrl.addEventListener('input', (event) => {
        state.settings.redirectUrl = event.target.value
      })
    }
  }

  function renderConfirmations() {
    const isRedirect = state.settings.confirmationType === 'redirect_url'

    if (refs.confirmationType) {
      refs.confirmationType.value = isRedirect ? 'redirect_url' : 'message'
    }
    if (refs.confirmationMessage) {
      refs.confirmationMessage.value = state.settings.confirmationMessage || ''
    }
    if (refs.redirectUrl) {
      refs.redirectUrl.value = state.settings.redirectUrl || ''
    }
    if (refs.confirmationMessageWrap) {
      refs.confirmationMessageWrap.hidden = isRedirect
    }
    if (refs.confirmationUrlWrap) {
      refs.confirmationUrlWrap.hidden = !isRedirect
    }
  }

  function bindConnectionEvents() {
    if (refs.addConnection) {
      refs.addConnection.addEventListener('click', () => {
        state.settings.connections.push(createConnection())
        renderConnections()
      })
    }

    if (!refs.connectionsList) return

    refs.connectionsList.addEventListener('click', (event) => {
      const actionButton = event.target.closest('[data-action]')
      if (!actionButton) return

      if (actionButton.dataset.action === 'remove-connection') {
        const index = Number.parseInt(actionButton.dataset.connectionIndex || '-1', 10)
        if (index < 0) return
        state.settings.connections.splice(index, 1)
        if (!state.settings.connections.length) {
          state.settings.connections.push(createConnection())
        }
        renderConnections()
      }
    })

    refs.connectionsList.addEventListener('input', (event) => {
      const index = Number.parseInt(event.target.dataset.connectionIndex || '-1', 10)
      if (index < 0) return
      const key = event.target.dataset.setting
      if (!key || !state.settings.connections[index]) return

      if (key === 'timeout') {
        state.settings.connections[index].timeout = Number.parseInt(event.target.value || '8', 10) || 8
        return
      }

      state.settings.connections[index][key] = event.target.value
    })

    refs.connectionsList.addEventListener('change', (event) => {
      const index = Number.parseInt(event.target.dataset.connectionIndex || '-1', 10)
      if (index < 0) return
      const key = event.target.dataset.setting
      if (!key || !state.settings.connections[index]) return

      if (key === 'enabled') {
        state.settings.connections[index].enabled = Boolean(event.target.checked)
      }
      if (key === 'type') {
        const value = CONNECTION_TYPES.includes(event.target.value) ? event.target.value : 'webhook'
        state.settings.connections[index].type = value
      }
    })
  }

  function renderConnections() {
    if (!refs.connectionsList) return

    refs.connectionsList.innerHTML = ''
    state.settings.connections.forEach((connection, index) => {
      const card = document.createElement('div')
      card.className = 'dsf-connection-card'

      const title = connection.label || 'Webhook'
      card.innerHTML = `
        <div class="dsf-inline-heading dsf-inline-heading--tight">
          <strong>${escapeHtml(title)}</strong>
          <button type="button" class="dsf-link-btn dsf-link-btn--danger" data-action="remove-connection" data-connection-index="${index}">Remove</button>
        </div>
        <label class="dsf-checkline">
          <input type="checkbox" data-setting="enabled" data-connection-index="${index}" ${connection.enabled ? 'checked' : ''}>
          <span>Enable this connection</span>
        </label>
        <div class="dsf-field-grid dsf-field-grid--2">
          <label class="dsf-field-block">
            <span>Connection Type</span>
            <select data-setting="type" data-connection-index="${index}">
              <option value="webhook" ${connection.type === 'webhook' ? 'selected' : ''}>Webhook</option>
              <option value="salesforce" ${connection.type === 'salesforce' ? 'selected' : ''}>Salesforce</option>
            </select>
          </label>
          <label class="dsf-field-block">
            <span>Label (optional)</span>
            <input type="text" data-setting="label" data-connection-index="${index}" value="${escapeHtml(connection.label || '')}">
          </label>
        </div>
        <label class="dsf-field-block">
          <span>Endpoint URL</span>
          <input type="url" data-setting="endpointUrl" data-connection-index="${index}" value="${escapeHtml(connection.endpointUrl || '')}" placeholder="https://example.com/webhook">
        </label>
        <div class="dsf-field-grid dsf-field-grid--2">
          <label class="dsf-field-block">
            <span>Secret (optional)</span>
            <input type="text" data-setting="secret" data-connection-index="${index}" value="${escapeHtml(connection.secret || '')}">
          </label>
          <label class="dsf-field-block">
            <span>Timeout (seconds)</span>
            <input type="number" min="1" max="120" data-setting="timeout" data-connection-index="${index}" value="${escapeHtml(String(connection.timeout || 8))}">
          </label>
        </div>
      `

      refs.connectionsList.appendChild(card)
    })
  }

  function bindDrawerEvents() {
    if (refs.drawerClose) {
      refs.drawerClose.addEventListener('click', closeDrawer)
    }

    if (!refs.drawerBody) return

    refs.drawerBody.addEventListener('input', handleDrawerUpdate)
    refs.drawerBody.addEventListener('change', handleDrawerUpdate)
    refs.drawerBody.addEventListener('click', handleDrawerClick)
  }

  function bindSaveButton() {
    if (!refs.saveButton) return

    refs.saveButton.addEventListener('click', async () => {
      await saveForm()
    })
  }

  function bindBackButton() {
    if (!refs.backButton || !wpData.adminListUrl) return

    refs.backButton.addEventListener('click', () => {
      window.location.href = String(wpData.adminListUrl)
    })
  }

  function renderShortcode() {
    if (!refs.shortcode) return
    refs.shortcode.textContent = `[dsform id='${state.formId}']`
  }

  function renderLibrary() {
    refs.libraryList.innerHTML = ''

    FIELD_LIBRARY.forEach((field) => {
      const item = document.createElement('button')
      item.type = 'button'
      item.className = 'dsf-library-item'
      item.draggable = true
      item.dataset.type = field.type
      item.innerHTML = `<span class="dashicons ${field.icon}" aria-hidden="true"></span><span>${field.label}</span>`

      item.addEventListener('dragstart', (event) => {
        state.dragPayload = { source: 'library', fieldType: field.type }
        if (event.dataTransfer) {
          event.dataTransfer.effectAllowed = 'copy'
          event.dataTransfer.setData('text/plain', field.type)
        }
      })

      item.addEventListener('dragend', clearDropHighlights)
      item.addEventListener('dblclick', () => {
        addFieldFromLibrary(field.type)
      })
      refs.libraryList.appendChild(item)
    })
  }

  function renderCanvas() {
    refs.canvas.innerHTML = ''

    if (!state.rows.length) {
      const empty = document.createElement('p')
      empty.className = 'dsf-canvas-empty-help'
      empty.textContent =
        'Start by dragging a field from the Form Fields Library into this canvas.'
      refs.canvas.appendChild(empty)
    }

    for (let index = 0; index <= state.rows.length; index += 1) {
      refs.canvas.appendChild(createBetweenDropzone(index))

      if (index < state.rows.length) {
        refs.canvas.appendChild(createRow(state.rows[index], index))
      }
    }
  }

  function createBetweenDropzone(index) {
    const zone = document.createElement('div')
    zone.className = 'dsf-canvas-dropzone dsf-canvas-dropzone--between'
    zone.dataset.zone = 'between'
    zone.dataset.index = String(index)
    bindDropzone(zone, {
      type: 'between',
      index
    })
    return zone
  }

  function createRow(row, rowIndex) {
    const rowEl = document.createElement('div')
    rowEl.className = 'dsf-canvas-row'
    rowEl.dataset.rowId = row.id
    rowEl.dataset.rowIndex = String(rowIndex)

    const fieldsWrap = document.createElement('div')
    const hasHalfSingle =
      row.fields.length === 1 &&
      row.fields[0].width === 'half' &&
      row.fields[0].type !== 'page_break' &&
      row.fields[0].type !== 'hidden'
    const columns = row.fields.length === 2 || hasHalfSingle ? 2 : 1
    fieldsWrap.className = `dsf-canvas-row__fields dsf-canvas-row__fields--cols-${columns}`

    row.fields.forEach((field, fieldIndex) => {
      fieldsWrap.appendChild(createFieldCard(field, rowIndex, fieldIndex))
    })

    rowEl.appendChild(fieldsWrap)

    return rowEl
  }

  function createFieldCard(field, rowIndex) {
    const card = document.createElement('div')
    card.className = 'dsf-canvas-field'
    card.draggable = true
    card.dataset.fieldId = field.id
    card.dataset.rowIndex = String(rowIndex)
    card.classList.toggle('is-selected', state.selectedFieldId === field.id)

    const meta = FIELD_LIBRARY.find((item) => item.type === field.type)
    const typeLabel = meta ? meta.label : 'Field'
    const headerLabel = `${field.label || typeLabel}${field.required ? ' *' : ''}`
    const row = state.rows[rowIndex]
    const widthLabel = row && row.fields.length === 2 ? 'Half width' : field.width === 'half' ? 'Half width' : 'Full width'

    card.innerHTML = `
      <div class="dsf-canvas-field__toolbar">
        <span class="dsf-canvas-field__tag">${escapeHtml(headerLabel)}</span>
        <div class="dsf-canvas-field__toolbar-btns">
          <button class="dsf-canvas-icon-btn" type="button" data-action="move-up" title="Move up">
            <span class="dashicons dashicons-arrow-up-alt2"></span>
          </button>
          <button class="dsf-canvas-icon-btn" type="button" data-action="move-down" title="Move down">
            <span class="dashicons dashicons-arrow-down-alt2"></span>
          </button>
          <button class="dsf-canvas-icon-btn" type="button" data-action="duplicate" title="Duplicate field">
            <span class="dashicons dashicons-admin-page"></span>
          </button>
          <button class="dsf-canvas-icon-btn dsf-canvas-icon-btn--danger" type="button" data-action="remove" title="Delete field">
            <span class="dashicons dashicons-trash"></span>
          </button>
        </div>
      </div>
      <div class="dsf-canvas-field__content">
        ${renderFieldPreview(field)}
      </div>
      <div class="dsf-canvas-field__meta">
        <span>${escapeHtml(typeLabel)}</span>
        <span>${escapeHtml(widthLabel)}</span>
      </div>
    `

    card.addEventListener('click', (event) => {
      const button = event.target.closest('[data-action]')
      if (button) {
        event.stopPropagation()
        const action = button.dataset.action
        if (action === 'remove') {
          requestFieldRemoval(field.id)
          return
        }
        if (action === 'duplicate') {
          duplicateField(field.id)
          return
        }
        if (action === 'move-up') {
          moveFieldRow(field.id, -1)
          return
        }
        if (action === 'move-down') {
          moveFieldRow(field.id, 1)
          return
        }
        return
      }

      setSelectedField(field.id)
    })

    card.addEventListener('dragstart', (event) => {
      state.dragPayload = { source: 'canvas', fieldId: field.id }
      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move'
        event.dataTransfer.setData('text/plain', field.id)
      }
    })

    card.addEventListener('dragend', clearDropHighlights)
    return card
  }

  function renderFieldPreview(field) {
    const safePlaceholder = escapeHtml(field.placeholder || '')
    const safeValue = escapeHtml(field.defaultValue || '')
    const safeHelpText = escapeHtml(field.helpText || '')
    const options = Array.isArray(field.options) && field.options.length ? field.options : DEFAULT_OPTIONS

    if (field.type === 'hidden') {
      return `<div class="dsf-canvas-hidden-pill">Hidden field: ${escapeHtml(field.name || 'hidden_field')}</div>`
    }

    if (field.type === 'page_break') {
      const animation = formatAnimationLabel(field.pageBreakAnimation || 'slide-left')
      return `<div class="dsf-form-html"><strong>Page Break</strong><br><small>Transition: ${escapeHtml(animation)}</small></div>`
    }

    if (field.type === 'html') {
      return `<div class="dsf-form-html">${field.html || '<p>Custom HTML block</p>'}</div>`
    }

    let control = ''

    switch (field.type) {
      case 'paragraph_text':
        control = `<textarea placeholder="${safePlaceholder}" disabled></textarea>`
        break
      case 'checkboxes':
        control = `<div class="dsf-form-options">${options
          .map(
            (option) =>
              `<label class="dsf-form-option"><input type="checkbox" disabled><span>${escapeHtml(
                option
              )}</span></label>`
          )
          .join('')}</div>`
        break
      case 'radio_buttons':
        control = `<div class="dsf-form-options">${options
          .map(
            (option) =>
              `<label class="dsf-form-option"><input type="radio" disabled><span>${escapeHtml(
                option
              )}</span></label>`
          )
          .join('')}</div>`
        break
      case 'drop_down':
        control = `<select disabled><option>Select an option</option>${options
          .map((option) => `<option>${escapeHtml(option)}</option>`)
          .join('')}</select>`
        break
      case 'number':
        control = `<input type="number" value="${safeValue}" placeholder="${safePlaceholder}" disabled>`
        break
      case 'phone':
        control = `<input type="tel" value="${safeValue}" placeholder="${safePlaceholder}" disabled>`
        break
      case 'date':
        control = `<input type="date" value="${safeValue}" disabled>`
        break
      case 'email':
        control = `<input type="email" value="${safeValue}" placeholder="${safePlaceholder}" disabled>`
        break
      case 'website':
        control = `<input type="url" value="${safeValue}" placeholder="${safePlaceholder}" disabled>`
        break
      case 'file_upload':
        control = `<input type="file" disabled>`
        break
      case 'single_line_text':
      default:
        control = `<input type="text" value="${safeValue}" placeholder="${safePlaceholder}" disabled>`
        break
    }

    return `
      <div class="dsf-form-field dsf-form-field--${escapeHtml(field.type)}">
        ${control}
        ${safeHelpText ? `<p class="dsf-form-help-text">${safeHelpText}</p>` : ''}
      </div>
    `
  }

  function bindDropzone(element, zone) {
    element.addEventListener('dragover', (event) => {
      if (!state.dragPayload) return
      event.preventDefault()
      element.classList.add('is-active')
      if (event.dataTransfer) {
        event.dataTransfer.dropEffect =
          state.dragPayload.source === 'library' ? 'copy' : 'move'
      }
    })

    element.addEventListener('dragleave', () => {
      element.classList.remove('is-active')
    })

    element.addEventListener('drop', (event) => {
      event.preventDefault()
      event.stopPropagation()
      element.classList.remove('is-active')
      handleDrop(zone)
    })
  }

  function clearDropHighlights() {
    if (refs.canvas) {
      refs.canvas.classList.remove('is-dragging-over')
    }
    document
      .querySelectorAll('.dsf-canvas-dropzone.is-active')
      .forEach((zone) => zone.classList.remove('is-active'))
  }

  function bindCanvasDropSurface() {
    if (!refs.canvas) return

    refs.canvas.addEventListener('dragover', (event) => {
      if (!state.dragPayload) return
      event.preventDefault()
      refs.canvas.classList.add('is-dragging-over')
      if (event.dataTransfer) {
        event.dataTransfer.dropEffect = state.dragPayload.source === 'library' ? 'copy' : 'move'
      }
    })

    refs.canvas.addEventListener('dragleave', (event) => {
      if (event.target === refs.canvas) {
        refs.canvas.classList.remove('is-dragging-over')
      }
    })

    refs.canvas.addEventListener('drop', (event) => {
      if (!state.dragPayload) return
      if (event.target.closest('.dsf-canvas-dropzone')) return

      event.preventDefault()
      refs.canvas.classList.remove('is-dragging-over')
      handleDrop(resolvePointerDropZone(event))
    })
  }

  function addFieldFromLibrary(fieldType) {
    const field = createField(fieldType)
    const lastRowIndex = state.rows.length - 1
    const lastRow = state.rows[lastRowIndex]

    if (canSplitRowWithType(lastRow, field.type) && lastRow.fields[0].width === 'half') {
      field.width = 'half'
      lastRow.fields[0].width = 'half'
      lastRow.fields.push(field)
    } else {
      field.width = 'full'
      state.rows.push({ id: uid('row'), fields: [field] })
    }

    cleanupRows()
    setSelectedField(field.id)
  }

  function resolvePointerDropZone(event) {
    const rowEl = event.target.closest('.dsf-canvas-row')
    if (!rowEl) {
      return { type: 'between', index: state.rows.length }
    }

    const rowIndex = Number.parseInt(rowEl.dataset.rowIndex || '-1', 10)
    if (rowIndex < 0 || rowIndex >= state.rows.length) {
      return { type: 'between', index: state.rows.length }
    }

    const row = state.rows[rowIndex]
    const rect = rowEl.getBoundingClientRect()
    const offsetY = event.clientY - rect.top
    const threshold = Math.max(20, rect.height * 0.24)

    if (offsetY <= threshold) {
      return { type: 'between', index: rowIndex }
    }

    if (offsetY >= rect.height - threshold) {
      return { type: 'between', index: rowIndex + 1 }
    }

    const incomingType = getIncomingFieldType(state.dragPayload)
    if (canSplitRowWithType(row, incomingType)) {
      return {
        type: 'split',
        rowIndex,
        side: event.clientX < rect.left + rect.width / 2 ? 'left' : 'right'
      }
    }

    return { type: 'between', index: rowIndex + 1 }
  }

  function getIncomingFieldType(payload) {
    if (!payload) return ''

    if (payload.source === 'library') {
      return typeof payload.fieldType === 'string' ? payload.fieldType : ''
    }

    if (payload.source === 'canvas' && payload.fieldId) {
      const field = getFieldById(payload.fieldId)
      return field ? field.type : ''
    }

    return ''
  }

  function getFieldById(fieldId) {
    for (const row of state.rows) {
      const match = row.fields.find((field) => field.id === fieldId)
      if (match) {
        return match
      }
    }

    return null
  }

  function isSplitFieldType(type) {
    return Boolean(type) && !NON_SPLIT_FIELD_TYPES.has(type)
  }

  function canSplitRowWithType(row, incomingType) {
    return (
      Boolean(row) &&
      row.fields.length === 1 &&
      isSplitFieldType(row.fields[0].type) &&
      isSplitFieldType(incomingType)
    )
  }

  function handleDrop(zone) {
    const payload = state.dragPayload
    state.dragPayload = null
    if (!payload) return

    const extracted = extractFieldFromPayload(payload)
    if (!extracted || !extracted.field) return

    const field = extracted.field

    if (zone.type === 'split') {
      let targetRowIndex = zone.rowIndex

      if (extracted.source === 'canvas' && extracted.removedRow && extracted.rowIndex < targetRowIndex) {
        targetRowIndex -= 1
      }

      const targetRow = state.rows[targetRowIndex]
      const canSplit = canSplitRowWithType(targetRow, field.type)

      if (canSplit) {
        field.width = 'half'
        targetRow.fields[0].width = 'half'
        targetRow.fields = zone.side === 'left'
          ? [field, targetRow.fields[0]]
          : [targetRow.fields[0], field]
        cleanupRows()
        renderCanvas()
        return
      }
    }

    let insertIndex = zone.type === 'between' ? zone.index : zone.rowIndex + 1
    if (extracted.source === 'canvas' && extracted.removedRow && extracted.rowIndex < insertIndex) {
      insertIndex -= 1
    }

    if (!isSplitFieldType(field.type)) {
      field.width = 'full'
    } else {
      field.width = field.width === 'half' ? 'half' : 'full'
    }

    state.rows.splice(insertIndex, 0, { id: uid('row'), fields: [field] })
    if (field.width === 'half') {
      tryMergeHalfRows(insertIndex)
    }
    cleanupRows()
    renderCanvas()
  }

  function extractFieldFromPayload(payload) {
    if (payload.source === 'library') {
      return {
        source: 'library',
        field: createField(payload.fieldType)
      }
    }

    if (payload.source !== 'canvas' || !payload.fieldId) {
      return null
    }

    for (let rowIndex = 0; rowIndex < state.rows.length; rowIndex += 1) {
      const row = state.rows[rowIndex]
      const fieldIndex = row.fields.findIndex((field) => field.id === payload.fieldId)
      if (fieldIndex === -1) continue

      const [field] = row.fields.splice(fieldIndex, 1)
      const removedRow = row.fields.length === 0
      if (removedRow) {
        state.rows.splice(rowIndex, 1)
      } else {
        row.fields[0].width = 'full'
      }

      if (state.selectedFieldId === payload.fieldId) {
        state.selectedFieldId = null
      }

      return {
        source: 'canvas',
        field,
        rowIndex,
        fieldIndex,
        removedRow
      }
    }

    return null
  }

  function cleanupRows() {
    state.rows = state.rows
      .map((row) => {
        if (!row || !Array.isArray(row.fields)) return null
        row.fields = row.fields.filter(Boolean).slice(0, 2)
        if (!row.fields.length) return null

        if (row.fields.length > 1 && row.fields.some((field) => field.type === 'page_break')) {
          const breakField = row.fields.find((field) => field.type === 'page_break')
          row.fields = [{ ...breakField, width: 'full' }]
        }

        if (row.fields.length > 1 && row.fields.some((field) => field.type === 'hidden')) {
          const hiddenField = row.fields.find((field) => field.type === 'hidden')
          row.fields = [{ ...hiddenField, width: 'full' }]
        }

        if (row.fields.length === 1) {
          if (row.fields[0].type === 'page_break' || row.fields[0].type === 'hidden') {
            row.fields[0].width = 'full'
          } else {
            row.fields[0].width = row.fields[0].width === 'half' ? 'half' : 'full'
          }
        } else {
          row.fields[0].width = 'half'
          row.fields[1].width = 'half'
        }

        return row
      })
      .filter(Boolean)
  }

  function getRowIndexByFieldId(fieldId) {
    for (let index = 0; index < state.rows.length; index += 1) {
      if (state.rows[index].fields.some((field) => field.id === fieldId)) {
        return index
      }
    }

    return -1
  }

  function isHalfWidthRowCandidate(row) {
    return (
      Boolean(row) &&
      row.fields.length === 1 &&
      row.fields[0].width === 'half' &&
      isSplitFieldType(row.fields[0].type)
    )
  }

  function tryMergeHalfRows(rowIndex) {
    const row = state.rows[rowIndex]
    if (!isHalfWidthRowCandidate(row)) {
      return false
    }

    const previousRow = rowIndex > 0 ? state.rows[rowIndex - 1] : null
    if (isHalfWidthRowCandidate(previousRow)) {
      previousRow.fields[0].width = 'half'
      row.fields[0].width = 'half'
      previousRow.fields = [previousRow.fields[0], row.fields[0]]
      state.rows.splice(rowIndex, 1)
      return true
    }

    const nextRow = rowIndex < state.rows.length - 1 ? state.rows[rowIndex + 1] : null
    if (isHalfWidthRowCandidate(nextRow)) {
      row.fields[0].width = 'half'
      nextRow.fields[0].width = 'half'
      row.fields = [row.fields[0], nextRow.fields[0]]
      state.rows.splice(rowIndex + 1, 1)
      return true
    }

    return false
  }

  function setSelectedField(fieldId) {
    state.selectedFieldId = fieldId
    renderCanvas()
    renderDrawer()
  }

  function closeDrawer() {
    state.selectedFieldId = null
    renderCanvas()
    renderDrawer()
  }

  function getSelectedField() {
    if (!state.selectedFieldId) return null
    for (const row of state.rows) {
      const field = row.fields.find((item) => item.id === state.selectedFieldId)
      if (field) return field
    }
    return null
  }

  function removeField(fieldId) {
    state.rows = state.rows
      .map((row) => ({
        ...row,
        fields: row.fields.filter((field) => field.id !== fieldId)
      }))
      .filter((row) => row.fields.length > 0)

    if (state.selectedFieldId === fieldId) {
      state.selectedFieldId = null
    }

    cleanupRows()
    renderCanvas()
    renderDrawer()
  }

  function requestFieldRemoval(fieldId) {
    const field = getFieldById(fieldId)
    if (!field) return

    const typeLabel = FIELD_LIBRARY.find((item) => item.type === field.type)?.label || 'Field'
    const label = String(field.label || typeLabel).trim()
    const message = `Are you sure you want to delete "${label}"?`

    if (!window.confirm(message)) return
    removeField(fieldId)
  }

  function moveFieldRow(fieldId, direction) {
    const rowIndex = getRowIndexByFieldId(fieldId)
    if (rowIndex < 0) return

    const targetIndex = rowIndex + direction
    if (targetIndex < 0 || targetIndex >= state.rows.length) return

    const current = state.rows[rowIndex]
    state.rows[rowIndex] = state.rows[targetIndex]
    state.rows[targetIndex] = current
    renderCanvas()
    renderDrawer()
  }

  function duplicateField(fieldId) {
    const rowIndex = getRowIndexByFieldId(fieldId)
    if (rowIndex < 0) return

    const row = state.rows[rowIndex]
    const source = row.fields.find((field) => field.id === fieldId)
    if (!source) return

    const clonedField = {
      ...source,
      id: uid('field'),
      name: toFieldName(`${source.name || source.type}_${uid('copy').slice(-4)}`)
    }

    if (Array.isArray(source.options)) {
      clonedField.options = [...source.options]
    }

    clonedField.width = isSplitFieldType(clonedField.type) ? source.width : 'full'

    state.rows.splice(rowIndex + 1, 0, {
      id: uid('row'),
      fields: [clonedField]
    })

    if (clonedField.width === 'half') {
      tryMergeHalfRows(rowIndex + 1)
    }

    cleanupRows()
    setSelectedField(clonedField.id)
  }

  function renderDrawer() {
    if (!refs.drawer || !refs.drawerBody || !refs.drawerTitle) return

    const field = getSelectedField()
    if (!field) {
      refs.drawer.classList.remove('is-open')
      refs.drawer.setAttribute('aria-hidden', 'true')
      refs.drawerBody.innerHTML = ''
      if (refs.sidebarStack) {
        refs.sidebarStack.classList.remove('is-drawer-open')
      }
      return
    }

    const meta = FIELD_LIBRARY.find((item) => item.type === field.type)
    refs.drawerTitle.textContent = meta ? meta.label : 'Edit Field'
    refs.drawer.classList.add('is-open')
    refs.drawer.setAttribute('aria-hidden', 'false')
    if (refs.sidebarStack) {
      refs.sidebarStack.classList.add('is-drawer-open')
    }

    let html = ''

    if (field.type !== 'page_break' && field.type !== 'hidden') {
      html += settingInput('Field Label', 'label', field.label)
    }

    if (field.type !== 'page_break' && field.type !== 'html') {
      html += settingInput('Field Name', 'name', field.name)
    }

    if (field.type !== 'page_break' && field.type !== 'hidden') {
      const widthDisabled = getRowByFieldId(field.id)?.fields.length === 2
      html += `
        <div class="dsf-field-setting">
          <label for="dsf-setting-width">Width</label>
          <select id="dsf-setting-width" data-setting="width" ${widthDisabled ? 'disabled' : ''}>
            <option value="full" ${field.width === 'full' ? 'selected' : ''}>Full width</option>
            <option value="half" ${field.width === 'half' ? 'selected' : ''}>Half width</option>
          </select>
        </div>
      `
    }

    if (!['html', 'hidden', 'page_break'].includes(field.type)) {
      html += `
        <div class="dsf-field-setting">
          <label class="dsf-field-setting__toggle">
            <input type="checkbox" data-setting="required" ${field.required ? 'checked' : ''}>
            <span>Required Field</span>
          </label>
        </div>
      `
    }

    if (PLACEHOLDER_FIELD_TYPES.has(field.type) && field.type !== 'date' && field.type !== 'number') {
      html += settingInput('Placeholder', 'placeholder', field.placeholder || '')
    }

    if (DEFAULT_VALUE_FIELD_TYPES.has(field.type)) {
      html += settingInput('Default Value', 'defaultValue', field.defaultValue || '')
    }

    if (field.type !== 'page_break' && field.type !== 'hidden' && field.type !== 'html') {
      html += settingTextarea('Help Text', 'helpText', field.helpText || '')
    }

    if (field.type === 'html') {
      html += settingTextarea('HTML Content', 'html', field.html || '')
    }

    if (OPTION_FIELD_TYPES.has(field.type)) {
      html += renderOptionsEditor(field)
    }

    if (field.type === 'page_break') {
      html += `
        <div class="dsf-field-setting">
          <label for="dsf-setting-page-break-animation">Animation Transitions</label>
          <select id="dsf-setting-page-break-animation" data-setting="pageBreakAnimation">
            <option value="slide-left" ${field.pageBreakAnimation === 'slide-left' ? 'selected' : ''}>Slide Left</option>
            <option value="slide-right" ${field.pageBreakAnimation === 'slide-right' ? 'selected' : ''}>Slide Right</option>
            <option value="slide-up" ${field.pageBreakAnimation === 'slide-up' ? 'selected' : ''}>Slide Up</option>
            <option value="slide-down" ${field.pageBreakAnimation === 'slide-down' ? 'selected' : ''}>Slide Down</option>
            <option value="zoom" ${field.pageBreakAnimation === 'zoom' ? 'selected' : ''}>Zoom</option>
            <option value="fade" ${field.pageBreakAnimation === 'fade' ? 'selected' : ''}>Fade</option>
            <option value="none" ${field.pageBreakAnimation === 'none' ? 'selected' : ''}>None</option>
          </select>
        </div>
      `
    }

    html += `
      <div class="dsf-field-setting dsf-field-setting--danger">
        <button type="button" class="dsf-remove-field-btn" data-action="remove-field">Remove Field</button>
      </div>
    `

    refs.drawerBody.innerHTML = html
  }

  function settingInput(label, key, value) {
    return `
      <div class="dsf-field-setting">
        <label for="dsf-setting-${key}">${escapeHtml(label)}</label>
        <input id="dsf-setting-${key}" type="text" data-setting="${escapeHtml(key)}" value="${escapeHtml(
      value || ''
    )}">
      </div>
    `
  }

  function settingTextarea(label, key, value) {
    return `
      <div class="dsf-field-setting">
        <label for="dsf-setting-${key}">${escapeHtml(label)}</label>
        <textarea id="dsf-setting-${key}" data-setting="${escapeHtml(key)}">${escapeHtml(
      value || ''
    )}</textarea>
      </div>
    `
  }

  function renderOptionsEditor(field) {
    const options = Array.isArray(field.options) && field.options.length ? field.options : [...DEFAULT_OPTIONS]
    const rows = options
      .map(
        (option, index) => `
        <div class="dsf-options-editor__item">
          <input type="text" data-setting="option" data-option-index="${index}" value="${escapeHtml(option)}">
          <button class="dsf-canvas-icon-btn dsf-canvas-icon-btn--danger" type="button" data-action="remove-option" data-option-index="${index}" title="Remove option">
            <span class="dashicons dashicons-no-alt"></span>
          </button>
        </div>
      `
      )
      .join('')

    return `
      <div class="dsf-field-setting">
        <label>Options</label>
        <div class="dsf-options-editor">
          ${rows}
          <button type="button" class="dsf-options-editor__add" data-action="add-option">Add Option</button>
        </div>
      </div>
    `
  }

  function handleDrawerUpdate(event) {
    const field = getSelectedField()
    if (!field) return

    const setting = event.target.dataset.setting
    if (!setting) return

    if (setting === 'required') {
      field.required = Boolean(event.target.checked)
      renderCanvas()
      return
    }

    if (setting === 'width') {
      const row = getRowByFieldId(field.id)
      if (row && row.fields.length === 1) {
        field.width = event.target.value === 'half' && isSplitFieldType(field.type) ? 'half' : 'full'
        if (field.width === 'half') {
          const rowIndex = getRowIndexByFieldId(field.id)
          if (rowIndex >= 0) {
            tryMergeHalfRows(rowIndex)
          }
        }
      }
      cleanupRows()
      renderCanvas()
      renderDrawer()
      return
    }

    if (setting === 'option') {
      const index = Number.parseInt(event.target.dataset.optionIndex || '-1', 10)
      if (!Array.isArray(field.options)) {
        field.options = [...DEFAULT_OPTIONS]
      }
      if (index >= 0) {
        field.options[index] = event.target.value
      }
      renderCanvas()
      return
    }

    if (setting === 'pageBreakAnimation') {
      field.pageBreakAnimation = normalizePageBreakAnimation(event.target.value)
      renderCanvas()
      return
    }

    if (setting === 'name') {
      field.name = toFieldName(event.target.value)
      event.target.value = field.name
    } else {
      field[setting] = event.target.value
    }

    renderCanvas()
  }

  function handleDrawerClick(event) {
    const actionButton = event.target.closest('[data-action]')
    if (!actionButton) return

    const field = getSelectedField()
    if (!field) return

    const action = actionButton.dataset.action
    if (action === 'remove-field') {
      requestFieldRemoval(field.id)
      return
    }

    if (action === 'add-option') {
      field.options = Array.isArray(field.options) ? field.options : []
      field.options.push(`Option ${field.options.length + 1}`)
      renderDrawer()
      renderCanvas()
      return
    }

    if (action === 'remove-option') {
      const index = Number.parseInt(actionButton.dataset.optionIndex || '-1', 10)
      if (!Array.isArray(field.options) || index < 0) return
      field.options.splice(index, 1)
      if (!field.options.length) {
        field.options = [...DEFAULT_OPTIONS]
      }
      renderDrawer()
      renderCanvas()
    }
  }

  function getRowByFieldId(fieldId) {
    return state.rows.find((row) => row.fields.some((field) => field.id === fieldId))
  }

  async function saveForm() {
    if (!state.formId || !wpData.ajaxUrl || !wpData.nonce) return

    const title = refs.titleInput && refs.titleInput.value.trim() ? refs.titleInput.value.trim() : 'Untitled Form'
    if (refs.titleInput) {
      refs.titleInput.value = title
    }

    if (refs.saveButton) {
      refs.saveButton.disabled = true
      refs.saveButton.textContent = 'Saving...'
    }

    const payload = new FormData()
    payload.append('action', 'dsf_save_form')
    payload.append('nonce', wpData.nonce)
    payload.append('form_id', String(state.formId))
    payload.append('title', title)
    payload.append('status', 'publish')
    payload.append('rows', JSON.stringify(state.rows))
    payload.append('settings', JSON.stringify(state.settings))

    try {
      const response = await fetch(wpData.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: payload
      })

      const result = await response.json()
      if (!result || !result.success) {
        throw new Error((result && result.data && result.data.message) || 'Unable to save form.')
      }

      showSaveIndicator(result.data?.message || 'Form saved successfully.')
      if (refs.shortcode && result.data?.shortcode) {
        refs.shortcode.textContent = result.data.shortcode
      }
      if (refs.titleInput && result.data?.post_title) {
        refs.titleInput.value = result.data.post_title
      }
    } catch (error) {
      showSaveIndicator(error.message || 'Unable to save form.', true)
    } finally {
      if (refs.saveButton) {
        refs.saveButton.disabled = false
        refs.saveButton.textContent = 'Save Form'
      }
    }
  }

  function showSaveIndicator(message, isError) {
    if (!refs.saveButton) return

    let indicator = refs.app.querySelector('.dsf-save-indicator')
    if (!indicator) {
      indicator = document.createElement('div')
      indicator.className = 'dsf-save-indicator'
      refs.saveButton.insertAdjacentElement('afterend', indicator)
    }

    indicator.textContent = message
    indicator.style.color = isError ? '#b91c1c' : '#166534'

    window.setTimeout(() => {
      if (indicator) {
        indicator.textContent = ''
      }
    }, 3500)
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;')
  }

  function formatAnimationLabel(value) {
    const animation = normalizePageBreakAnimation(value)
    if (animation === 'slide-right') return 'Slide Right'
    if (animation === 'slide-up') return 'Slide Up'
    if (animation === 'slide-down') return 'Slide Down'
    if (animation === 'zoom') return 'Zoom'
    if (animation === 'fade') return 'Fade'
    if (animation === 'none') return 'None'
    return 'Slide Left'
  }
})()
