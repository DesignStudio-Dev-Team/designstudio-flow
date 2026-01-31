<template>
  <Teleport to="body">
    <Transition name="dsf-dialog">
      <div v-if="visible" class="dsf-confirm-overlay" @click.self="$emit('cancel')">
        <div class="dsf-confirm-dialog">
          <!-- Icon -->
          <div class="dsf-confirm-icon" :class="`dsf-confirm-icon--${variant}`">
            <component :is="iconComponent" :size="24" />
          </div>
          
          <!-- Content -->
          <h3 class="dsf-confirm-title">{{ title }}</h3>
          <p class="dsf-confirm-message">{{ message }}</p>
          
          <!-- Actions -->
          <div class="dsf-confirm-actions">
            <button 
              class="dsf-confirm-btn dsf-confirm-btn--cancel" 
              @click="$emit('cancel')"
            >
              {{ cancelText }}
            </button>
            <button 
              class="dsf-confirm-btn dsf-confirm-btn--confirm"
              :class="`dsf-confirm-btn--${variant}`"
              @click="$emit('confirm')"
            >
              {{ confirmText }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'
import { AlertTriangle, Trash2, AlertCircle, Info } from 'lucide-vue-next'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: 'Are you sure?'
  },
  message: {
    type: String,
    default: 'This action cannot be undone.'
  },
  confirmText: {
    type: String,
    default: 'Confirm'
  },
  cancelText: {
    type: String,
    default: 'Cancel'
  },
  variant: {
    type: String,
    default: 'danger',
    validator: (v) => ['danger', 'warning', 'info'].includes(v)
  }
})

defineEmits(['confirm', 'cancel'])

const iconComponent = computed(() => {
  const icons = {
    danger: Trash2,
    warning: AlertTriangle,
    info: Info
  }
  return icons[props.variant] || AlertCircle
})
</script>

<style scoped>
.dsf-confirm-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10000;
}

.dsf-confirm-dialog {
  background: white;
  border-radius: 16px;
  padding: 24px;
  width: 100%;
  max-width: 400px;
  text-align: center;
  box-shadow: 
    0 25px 50px -12px rgba(0, 0, 0, 0.25),
    0 0 0 1px rgba(0, 0, 0, 0.05);
}

.dsf-confirm-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 16px;
}

.dsf-confirm-icon--danger {
  background: #fef2f2;
  color: #dc2626;
}

.dsf-confirm-icon--warning {
  background: #fffbeb;
  color: #d97706;
}

.dsf-confirm-icon--info {
  background: #eff6ff;
  color: #2563eb;
}

.dsf-confirm-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px;
}

.dsf-confirm-message {
  font-size: 0.9375rem;
  color: #6b7280;
  margin: 0 0 24px;
  line-height: 1.5;
}

.dsf-confirm-actions {
  display: flex;
  gap: 12px;
}

.dsf-confirm-btn {
  flex: 1;
  padding: 10px 16px;
  border-radius: 8px;
  font-size: 0.9375rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
  border: none;
}

.dsf-confirm-btn--cancel {
  background: #f3f4f6;
  color: #374151;
}

.dsf-confirm-btn--cancel:hover {
  background: #e5e7eb;
}

.dsf-confirm-btn--confirm {
  color: white;
}

.dsf-confirm-btn--danger {
  background: #dc2626;
}

.dsf-confirm-btn--danger:hover {
  background: #b91c1c;
}

.dsf-confirm-btn--warning {
  background: #d97706;
}

.dsf-confirm-btn--warning:hover {
  background: #b45309;
}

.dsf-confirm-btn--info {
  background: #2563eb;
}

.dsf-confirm-btn--info:hover {
  background: #1d4ed8;
}

/* Transitions */
.dsf-dialog-enter-active,
.dsf-dialog-leave-active {
  transition: opacity 0.2s ease;
}

.dsf-dialog-enter-active .dsf-confirm-dialog,
.dsf-dialog-leave-active .dsf-confirm-dialog {
  transition: transform 0.2s ease, opacity 0.2s ease;
}

.dsf-dialog-enter-from,
.dsf-dialog-leave-to {
  opacity: 0;
}

.dsf-dialog-enter-from .dsf-confirm-dialog,
.dsf-dialog-leave-to .dsf-confirm-dialog {
  transform: scale(0.95);
  opacity: 0;
}
</style>
