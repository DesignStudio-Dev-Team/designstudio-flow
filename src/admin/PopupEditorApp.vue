<template>
  <div class="dsf-popup-editor-app dsf-wrapper">
    <PopupSettingsFields :model-value="settings" :show-enable="false" @update:model-value="onUpdate" />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import PopupSettingsFields from '../components/common/PopupSettingsFields.vue'

const props = defineProps({
  initialSettings: { type: Object, default: () => ({}) },
  onChange: { type: Function, default: null },
})

const settings = ref({ ...props.initialSettings })

function onUpdate(value) {
  settings.value = value
  if (props.onChange) props.onChange(JSON.stringify(value))
}
</script>

<style scoped>
.dsf-popup-editor-app { padding: 6px 2px; }
</style>
