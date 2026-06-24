<template>
  <div class="dsf-showcase-field">
    <section>
      <div class="dsf-showcase-field__heading"><strong>Utility Bar</strong><button type="button" @click="addUtility" :disabled="model.utility.length >= 4">Add Item</button></div>
      <article v-for="(item, index) in model.utility" :key="`utility-${index}`">
        <div class="dsf-showcase-field__heading"><span>{{ item.label || `Utility ${index + 1}` }}</span><button type="button" class="is-danger" @click="remove('utility', index)">Remove</button></div>
        <div class="dsf-showcase-field__grid">
          <label>Label<input v-model="item.label" class="dsf-input" @input="emitValue" /></label>
          <label>URL<input v-model="item.url" class="dsf-input" @input="emitValue" /></label>
          <label>Icon<select v-model="item.icon" class="dsf-input" @change="emitValue"><option value="settings">Services</option><option value="book">Resources</option><option value="map-pin">Location</option><option value="phone">Phone</option></select></label>
          <label>Behavior<select v-model="item.kind" class="dsf-input" @change="emitValue"><option value="link">Link</option><option value="dropdown">Small Dropdown</option><option value="mega">Mega Menu</option><option value="locations">Locations Drawer</option><option value="calls">Call Drawer</option></select></label>
        </div>
        <div v-if="item.kind === 'dropdown'" class="dsf-showcase-field__nested">
          <div class="dsf-showcase-field__heading"><span>Dropdown Links</span><button type="button" @click="addLink(item)" :disabled="item.links.length >= 6">Add Link</button></div>
          <div v-for="(link, linkIndex) in item.links" :key="`ul-${linkIndex}`" class="dsf-showcase-field__link-row">
            <input v-model="link.label" class="dsf-input" placeholder="Label" @input="emitValue" /><input v-model="link.url" class="dsf-input" placeholder="URL" @input="emitValue" /><button type="button" class="is-danger" @click="removeNested(item.links, linkIndex)">x</button>
          </div>
        </div>
        <PanelEditor v-if="item.kind === 'mega'" :panel="item.panel" @change="emitValue" />
      </article>
    </section>

    <section>
      <div class="dsf-showcase-field__heading"><strong>Main Navigation</strong><button type="button" @click="addMenu" :disabled="model.menu.length >= 8">Add Item</button></div>
      <article v-for="(item, index) in model.menu" :key="`menu-${index}`">
        <div class="dsf-showcase-field__heading"><span>{{ item.label || `Menu ${index + 1}` }}</span><button type="button" class="is-danger" @click="remove('menu', index)">Remove</button></div>
        <div class="dsf-showcase-field__grid">
          <label>Label<input v-model="item.label" class="dsf-input" @input="emitValue" /></label>
          <label>URL<input v-model="item.url" class="dsf-input" @input="emitValue" /></label>
          <label class="dsf-showcase-field__check"><input v-model="item.hasMega" type="checkbox" @change="emitValue" /> Enable mega menu</label>
        </div>
        <PanelEditor v-if="item.hasMega" :panel="item.panel" @change="emitValue" />
      </article>
    </section>

    <section>
      <div class="dsf-showcase-field__heading"><strong>Locations</strong><button type="button" @click="addLocation" :disabled="model.locations.length >= 6">Add Location</button></div>
      <article v-for="(location, index) in model.locations" :key="`location-${index}`">
        <div class="dsf-showcase-field__heading"><span>{{ location.name || `Location ${index + 1}` }}</span><button type="button" class="is-danger" @click="remove('locations', index)">Remove</button></div>
        <div class="dsf-showcase-field__grid">
          <label>Name<input v-model="location.name" class="dsf-input" @input="emitValue" /></label><label>Image URL<input v-model="location.image" class="dsf-input" @input="emitValue" /></label>
          <label>Address<textarea v-model="location.address" class="dsf-input" @input="emitValue"></textarea></label><label>Hours<textarea v-model="location.hours" class="dsf-input" @input="emitValue"></textarea></label>
          <label>Phone<input v-model="location.phone" class="dsf-input" @input="emitValue" /></label><label>Phone URL<input v-model="location.phoneUrl" class="dsf-input" @input="emitValue" /></label><label>Directions URL<input v-model="location.directionsUrl" class="dsf-input" @input="emitValue" /></label>
        </div>
      </article>
    </section>

    <section>
      <div class="dsf-showcase-field__heading"><strong>Call Groups</strong><button type="button" @click="addCall" :disabled="model.calls.length >= 8">Add Group</button></div>
      <div v-for="(call, index) in model.calls" :key="`call-${index}`" class="dsf-showcase-field__link-row">
        <input v-model="call.label" class="dsf-input" placeholder="Label" @input="emitValue" /><input v-model="call.url" class="dsf-input" placeholder="tel:+1..." @input="emitValue" /><button type="button" class="is-danger" @click="remove('calls', index)">x</button>
      </div>
    </section>
  </div>
</template>

<script>
import { defineComponent, h } from 'vue'

const blankPanel = () => ({ introTitle: 'Explore our collection', introText: 'Find the right products and services for your space.', buttonText: 'View All', buttonUrl: '#', accentText: '', accentUrl: '#', promoImage: '', promoTitle: 'Featured Special', promoSubtitle: 'Limited time only', promoUrl: '#', cards: [] })
const normalizePanel = (value = {}) => ({ ...blankPanel(), ...value, cards: Array.isArray(value.cards) ? value.cards.slice(0, 6).map((card) => ({ eyebrow: '', title: '', url: '#', image: '', ...card })) : [] })

const PanelEditor = defineComponent({
  name: 'PanelEditor', props: { panel: { type: Object, required: true } }, emits: ['change'],
  setup(props, { emit }) {
    const field = (label, key, tag = 'input') => h('label', [label, h(tag, { class: 'dsf-input', value: props.panel[key], onInput: (event) => { props.panel[key] = event.target.value; emit('change') } })])
    return () => h('div', { class: 'dsf-showcase-field__nested' }, [
      h('strong', 'Mega Panel'), h('div', { class: 'dsf-showcase-field__grid' }, [field('Intro title', 'introTitle'), field('Intro text', 'introText', 'textarea'), field('Button text', 'buttonText'), field('Button URL', 'buttonUrl'), field('Accent link text', 'accentText'), field('Accent URL', 'accentUrl'), field('Promo image URL', 'promoImage'), field('Promo title', 'promoTitle'), field('Promo subtitle', 'promoSubtitle'), field('Promo URL', 'promoUrl')]),
      h('div', { class: 'dsf-showcase-field__heading' }, [h('span', 'Cards'), h('button', { type: 'button', disabled: props.panel.cards.length >= 6, onClick: () => { props.panel.cards.push({ eyebrow: 'Collection', title: 'Card title', url: '#', image: '' }); emit('change') } }, 'Add Card')]),
      ...props.panel.cards.map((card, index) => h('div', { class: 'dsf-showcase-field__card-row' }, [fieldFor(card, 'Eyebrow', 'eyebrow', emit), fieldFor(card, 'Title', 'title', emit), fieldFor(card, 'URL', 'url', emit), fieldFor(card, 'Image URL', 'image', emit), h('button', { type: 'button', class: 'is-danger', onClick: () => { props.panel.cards.splice(index, 1); emit('change') } }, 'x')]))
    ])
  }
})

function fieldFor(object, label, key, emit) { return h('label', [label, h('input', { class: 'dsf-input', value: object[key], onInput: (event) => { object[key] = event.target.value; emit('change') } })]) }

export default { name: 'ShowcaseHeaderNavigationField', components: { PanelEditor }, props: { modelValue: { type: Object, default: () => ({}) } }, emits: ['update:modelValue'], data() { return { model: this.normalize(this.modelValue) } }, watch: { modelValue: { deep: true, handler(value) { this.model = this.normalize(value) } } }, methods: {
  normalize(value = {}) { return { utility: (value.utility || []).slice(0, 4).map((item) => ({ label: '', url: '#', icon: 'settings', kind: 'link', links: [], ...item, links: (item.links || []).slice(0, 6).map((link) => ({ ...link })), panel: normalizePanel(item.panel) })), menu: (value.menu || []).slice(0, 8).map((item) => ({ label: '', url: '#', hasMega: false, ...item, panel: normalizePanel(item.panel) })), locations: (value.locations || []).slice(0, 6).map((location) => ({ ...location })), calls: (value.calls || []).slice(0, 8).map((call) => ({ ...call })) } },
  emitValue() { this.$emit('update:modelValue', JSON.parse(JSON.stringify(this.model))) }, remove(key, index) { this.model[key].splice(index, 1); this.emitValue() }, removeNested(items, index) { items.splice(index, 1); this.emitValue() },
  addUtility() { this.model.utility.push({ label: 'Utility Link', url: '#', icon: 'settings', kind: 'link', links: [], panel: blankPanel() }); this.emitValue() }, addMenu() { this.model.menu.push({ label: 'Menu Item', url: '#', hasMega: false, panel: blankPanel() }); this.emitValue() }, addLocation() { this.model.locations.push({ name: 'Location', image: '', address: '', hours: '', phone: '', phoneUrl: 'tel:', directionsUrl: '#' }); this.emitValue() }, addCall() { this.model.calls.push({ label: 'Call Us', url: 'tel:' }); this.emitValue() }, addLink(item) { item.links.push({ label: 'Link', url: '#' }); this.emitValue() }
} }
</script>

<style scoped>
.dsf-showcase-field { display:grid; gap:18px; }.dsf-showcase-field section,.dsf-showcase-field article,.dsf-showcase-field__nested{display:grid;gap:10px}.dsf-showcase-field section{border-top:1px solid #dfe3e8;padding-top:14px}.dsf-showcase-field article,.dsf-showcase-field__nested{padding:12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff}.dsf-showcase-field__heading,.dsf-showcase-field__link-row,.dsf-showcase-field__card-row{display:flex;align-items:center;gap:8px}.dsf-showcase-field__heading{justify-content:space-between}.dsf-showcase-field button{border:1px solid #cbd5e1;border-radius:6px;background:#fff;padding:6px 9px;cursor:pointer}.dsf-showcase-field button:disabled{opacity:.45}.dsf-showcase-field .is-danger{color:#b42318}.dsf-showcase-field__grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.dsf-showcase-field label{display:grid;gap:4px;font-size:12px}.dsf-showcase-field textarea{min-height:70px}.dsf-showcase-field__check{display:flex!important;align-items:center}.dsf-showcase-field__link-row input,.dsf-showcase-field__card-row label{min-width:0;flex:1}.dsf-showcase-field__card-row{align-items:end}@media(max-width:520px){.dsf-showcase-field__grid,.dsf-showcase-field__card-row{grid-template-columns:1fr;display:grid}}
</style>
