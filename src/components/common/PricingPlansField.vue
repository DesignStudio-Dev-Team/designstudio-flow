<template>
  <div class="dsf-pricing-plans-field">
    <draggable
      v-model="localPlans"
      item-key="id"
      handle=".dsf-pricing-plans-field__drag"
      ghost-class="dsf-pricing-plans-field__item--ghost"
      @end="emitUpdate"
    >
      <template #item="{ element, index }">
        <div class="dsf-pricing-plans-field__item">
          <div class="dsf-pricing-plans-field__header" @click="toggleItem(index)">
            <button class="dsf-pricing-plans-field__drag" type="button" @click.stop>
              <GripVertical :size="14" />
            </button>
            <span class="dsf-pricing-plans-field__title">{{ element.name || `Plan ${index + 1}` }}</span>
            <div class="dsf-pricing-plans-field__actions">
              <ChevronDown
                :size="16"
                class="dsf-pricing-plans-field__chevron"
                :class="{ 'dsf-pricing-plans-field__chevron--open': openItems.includes(index) }"
              />
              <button type="button" class="dsf-pricing-plans-field__delete" @click.stop="removeItem(index)">
                <Trash2 :size="14" />
              </button>
            </div>
          </div>

          <div v-show="openItems.includes(index)" class="dsf-pricing-plans-field__body">
            <PricingTextField label="Plan Name" :value="element.name" @update="updateField(index, 'name', $event)" />
            <div class="dsf-form-group">
              <label class="dsf-label">Description</label>
              <textarea class="dsf-input" rows="3" :value="element.description" @input="updateField(index, 'description', $event.target.value)"></textarea>
            </div>
            <div class="dsf-pricing-plans-field__two-col">
              <PricingTextField label="Monthly Price" :value="element.monthlyPrice" @update="updateField(index, 'monthlyPrice', $event)" />
              <PricingTextField label="Annual Price" :value="element.annualPrice" @update="updateField(index, 'annualPrice', $event)" />
            </div>
            <div class="dsf-pricing-plans-field__two-col">
              <PricingTextField label="Price Prefix" :value="element.pricePrefix" @update="updateField(index, 'pricePrefix', $event)" />
              <PricingTextField label="Price Suffix" :value="element.priceSuffix" @update="updateField(index, 'priceSuffix', $event)" />
            </div>
            <PricingTextField label="Button Text" :value="element.buttonText" @update="updateField(index, 'buttonText', $event)" />
            <PricingTextField label="Button URL" :value="element.buttonUrl" @update="updateField(index, 'buttonUrl', $event)" />
            <div class="dsf-form-group dsf-pricing-plans-field__toggle-row">
              <label class="dsf-label">Most Popular</label>
              <button type="button" class="dsf-toggle" :class="{ 'dsf-toggle--active': element.popular }" @click="updateField(index, 'popular', !element.popular)">
                <span class="dsf-toggle__thumb"></span>
              </button>
            </div>
            <PricingTextField v-if="element.popular" label="Badge Text" :value="element.badgeText" @update="updateField(index, 'badgeText', $event)" />
            <div class="dsf-form-group">
              <label class="dsf-label">Features</label>
              <textarea
                class="dsf-input"
                rows="6"
                placeholder="One feature per line"
                :value="element.features"
                @input="updateField(index, 'features', $event.target.value)"
              ></textarea>
            </div>
          </div>
        </div>
      </template>
    </draggable>

    <button type="button" class="dsf-pricing-plans-field__add" :disabled="localPlans.length >= 4" @click="addItem">
      <Plus :size="16" />
      {{ localPlans.length >= 4 ? 'Maximum 4 Plans' : 'Add Plan' }}
    </button>
  </div>
</template>

<script setup>
import { h, ref, watch } from 'vue'
import draggable from 'vuedraggable'
import { ChevronDown, GripVertical, Plus, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue'])
const localPlans = ref([])
const openItems = ref([0])

watch(() => props.modelValue, (newValue) => {
  const previousIds = localPlans.value.map((plan) => plan.id)
  localPlans.value = (newValue || []).slice(0, 4).map((plan, index) => ({
    name: `Plan ${index + 1}`,
    description: 'Plan description.',
    monthlyPrice: '19',
    annualPrice: '15',
    pricePrefix: '$',
    priceSuffix: '/month',
    buttonText: 'Choose plan',
    buttonUrl: '#',
    popular: false,
    badgeText: 'Most popular',
    features: 'Feature one\nFeature two',
    ...plan,
    id: plan.id || previousIds[index] || `pricing-plan-${index}-${Date.now()}`,
  }))
}, { immediate: true, deep: true })

function emitUpdate() {
  emit('update:modelValue', localPlans.value.map(({ id, ...plan }) => plan))
}

function toggleItem(index) {
  const currentIndex = openItems.value.indexOf(index)
  if (currentIndex >= 0) openItems.value.splice(currentIndex, 1)
  else openItems.value.push(index)
}

function updateField(index, key, value) {
  localPlans.value[index][key] = value
  emitUpdate()
}

function addItem() {
  if (localPlans.value.length >= 4) return
  const number = localPlans.value.length + 1
  localPlans.value.push({
    id: `pricing-plan-${Date.now()}`,
    name: `Plan ${number}`,
    description: 'Plan description.',
    monthlyPrice: '19',
    annualPrice: '15',
    pricePrefix: '$',
    priceSuffix: '/month',
    buttonText: 'Choose plan',
    buttonUrl: '#',
    popular: false,
    badgeText: 'Most popular',
    features: 'Feature one\nFeature two',
  })
  openItems.value.push(localPlans.value.length - 1)
  emitUpdate()
}

function removeItem(index) {
  localPlans.value.splice(index, 1)
  openItems.value = openItems.value
    .filter((itemIndex) => itemIndex !== index)
    .map((itemIndex) => itemIndex > index ? itemIndex - 1 : itemIndex)
  emitUpdate()
}

const PricingTextField = {
  props: ['label', 'value'],
  emits: ['update'],
  setup(componentProps, { emit: emitField }) {
    return () => h('div', { class: 'dsf-form-group' }, [
      h('label', { class: 'dsf-label' }, componentProps.label),
      h('input', {
        type: 'text',
        class: 'dsf-input',
        value: componentProps.value,
        onInput: (event) => emitField('update', event.target.value),
      }),
    ])
  },
}
</script>

<style scoped>
.dsf-pricing-plans-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.dsf-pricing-plans-field__item {
  overflow: hidden;
  background: white;
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
}

.dsf-pricing-plans-field__item--ghost { opacity: 0.5; }

.dsf-pricing-plans-field__header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem 0.75rem;
  background: var(--dsf-gray-50);
  cursor: pointer;
}

.dsf-pricing-plans-field__drag,
.dsf-pricing-plans-field__delete {
  display: flex;
  padding: 0.25rem;
  border: 0;
  background: transparent;
  color: var(--dsf-gray-400);
  cursor: pointer;
}

.dsf-pricing-plans-field__title {
  min-width: 0;
  flex: 1;
  overflow: hidden;
  color: var(--dsf-gray-800);
  font-size: 0.8125rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dsf-pricing-plans-field__actions { display: flex; align-items: center; gap: 0.25rem; }
.dsf-pricing-plans-field__chevron { color: var(--dsf-gray-400); transition: transform 0.15s ease; }
.dsf-pricing-plans-field__chevron--open { transform: rotate(180deg); }

.dsf-pricing-plans-field__body {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 0.75rem;
}

.dsf-pricing-plans-field__two-col {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.5rem;
}

.dsf-pricing-plans-field__toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.dsf-pricing-plans-field__add {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.75rem;
  border: 1px dashed var(--dsf-gray-300);
  border-radius: var(--dsf-radius-md);
  background: white;
  color: var(--dsf-primary-600);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.dsf-pricing-plans-field__add:disabled { color: var(--dsf-gray-400); cursor: not-allowed; }
</style>
