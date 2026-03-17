<template>
  <div class="dsf-footer-dealers-field">
    <div
      v-for="(dealer, index) in localDealers"
      :key="dealer.__id"
      class="dsf-footer-dealers-field__card"
    >
      <div class="dsf-footer-dealers-field__head">
        <strong>Dealer {{ index + 1 }}</strong>
        <button class="dsf-footer-dealers-field__danger" @click="removeDealer(index)">Remove</button>
      </div>

      <div class="dsf-footer-dealers-field__grid">
        <div class="dsf-form-group">
          <label class="dsf-label">Dealer Name</label>
          <input
            type="text"
            class="dsf-input"
            :value="dealer.name"
            @input="updateDealer(index, 'name', $event.target.value)"
          />
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label">Map Image URL</label>
          <input
            type="text"
            class="dsf-input"
            :value="dealer.mapImage"
            @input="updateDealer(index, 'mapImage', $event.target.value)"
          />
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label">Store Photo URL</label>
          <input
            type="text"
            class="dsf-input"
            :value="dealer.photoImage"
            @input="updateDealer(index, 'photoImage', $event.target.value)"
          />
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label">Address Line 1</label>
          <input
            type="text"
            class="dsf-input"
            :value="dealer.addressLine1"
            @input="updateDealer(index, 'addressLine1', $event.target.value)"
          />
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label">Address Line 2</label>
          <input
            type="text"
            class="dsf-input"
            :value="dealer.addressLine2"
            @input="updateDealer(index, 'addressLine2', $event.target.value)"
          />
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label">Phone</label>
          <input
            type="text"
            class="dsf-input"
            :value="dealer.phone"
            @input="updateDealer(index, 'phone', $event.target.value)"
          />
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label">Directions Label</label>
          <input
            type="text"
            class="dsf-input"
            :value="dealer.directionsLabel"
            @input="updateDealer(index, 'directionsLabel', $event.target.value)"
          />
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label">Directions URL</label>
          <input
            type="text"
            class="dsf-input"
            :value="dealer.directionsUrl"
            @input="updateDealer(index, 'directionsUrl', $event.target.value)"
          />
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label">Hours Label</label>
          <input
            type="text"
            class="dsf-input"
            :value="dealer.hoursLabel"
            @input="updateDealer(index, 'hoursLabel', $event.target.value)"
          />
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label">Day 1</label>
          <input
            type="text"
            class="dsf-input"
            :value="dealer.day1"
            @input="updateDealer(index, 'day1', $event.target.value)"
          />
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label">Hours 1</label>
          <input
            type="text"
            class="dsf-input"
            :value="dealer.hours1"
            @input="updateDealer(index, 'hours1', $event.target.value)"
          />
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label">Day 2</label>
          <input
            type="text"
            class="dsf-input"
            :value="dealer.day2"
            @input="updateDealer(index, 'day2', $event.target.value)"
          />
        </div>

        <div class="dsf-form-group">
          <label class="dsf-label">Hours 2</label>
          <input
            type="text"
            class="dsf-input"
            :value="dealer.hours2"
            @input="updateDealer(index, 'hours2', $event.target.value)"
          />
        </div>
      </div>
    </div>

    <button class="dsf-footer-dealers-field__add" @click="addDealer">Add Dealer</button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue'])

const localDealers = ref([])

function makeId() {
  return `dealer-${Math.random().toString(36).slice(2, 10)}`
}

function createDealer() {
  return {
    __id: makeId(),
    name: 'Dealer Name',
    mapImage: '',
    photoImage: '',
    addressLine1: 'Address line 1',
    addressLine2: 'Address line 2',
    phone: '0255-555555',
    directionsLabel: 'Routebeschrijving',
    directionsUrl: '#',
    hoursLabel: 'Openingstijden:',
    day1: 'ma - do',
    hours1: '08:00 - 17:00',
    day2: 'vr',
    hours2: '08:00 - 16:30',
  }
}

function normalizeDealer(dealer) {
  const fallback = createDealer()
  return {
    __id: dealer?.__id || makeId(),
    name: dealer?.name || fallback.name,
    mapImage: dealer?.mapImage || '',
    photoImage: dealer?.photoImage || '',
    addressLine1: dealer?.addressLine1 || fallback.addressLine1,
    addressLine2: dealer?.addressLine2 || fallback.addressLine2,
    phone: dealer?.phone || fallback.phone,
    directionsLabel: dealer?.directionsLabel || fallback.directionsLabel,
    directionsUrl: dealer?.directionsUrl || '#',
    hoursLabel: dealer?.hoursLabel || fallback.hoursLabel,
    day1: dealer?.day1 || fallback.day1,
    hours1: dealer?.hours1 || fallback.hours1,
    day2: dealer?.day2 || fallback.day2,
    hours2: dealer?.hours2 || fallback.hours2,
  }
}

watch(
  () => props.modelValue,
  (value) => {
    const source = Array.isArray(value) ? value : []
    localDealers.value = (source.length ? source : [createDealer(), createDealer()]).map(normalizeDealer)
  },
  { immediate: true, deep: true }
)

function cleanDealers() {
  return localDealers.value.map(({ __id, ...dealer }) => dealer)
}

function emitUpdate() {
  emit('update:modelValue', cleanDealers())
}

function updateDealer(index, key, value) {
  localDealers.value[index][key] = value
  emitUpdate()
}

function addDealer() {
  localDealers.value.push(createDealer())
  emitUpdate()
}

function removeDealer(index) {
  localDealers.value.splice(index, 1)
  if (!localDealers.value.length) {
    localDealers.value.push(createDealer())
  }
  emitUpdate()
}
</script>

<style scoped>
.dsf-footer-dealers-field {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.dsf-footer-dealers-field__card {
  border: 1px solid var(--dsf-gray-200);
  background: #fff;
  border-radius: var(--dsf-radius-md);
  padding: 0.75rem;
}

.dsf-footer-dealers-field__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.dsf-footer-dealers-field__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.5rem;
}

.dsf-footer-dealers-field__add,
.dsf-footer-dealers-field__danger {
  border: 1px solid var(--dsf-gray-200);
  border-radius: var(--dsf-radius-md);
  background: #fff;
  color: var(--dsf-gray-700);
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.45rem 0.7rem;
  cursor: pointer;
}

.dsf-footer-dealers-field__add:hover {
  background: var(--dsf-gray-100);
}

.dsf-footer-dealers-field__danger {
  color: #b42318;
  border-color: #fecdca;
  background: #fff5f3;
}

.dsf-footer-dealers-field__danger:hover {
  background: #ffe4df;
}

@media (max-width: 900px) {
  .dsf-footer-dealers-field__grid {
    grid-template-columns: 1fr;
  }
}
</style>
