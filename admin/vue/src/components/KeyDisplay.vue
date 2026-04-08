<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const props = defineProps({ label: String, value: String, secret: Boolean })
const copied = ref(false)

function copy() {
  navigator.clipboard.writeText(props.value)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}
</script>

<template>
  <div class="key-display">
    <label class="key-label">{{ label }}</label>
    <div class="key-value-row">
      <code class="key-value" :class="{ secret }">{{ secret ? '••••••••-••••-••••-••••-••••••••' + value.slice(-4) : value }}</code>
      <button class="btn-copy" @click="copy">
        {{ copied ? t('detail.keys.copied') : t('detail.keys.copy') }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.key-display { margin-bottom: 16px; }
.key-label { display: block; font-size: 13px; color: #666; margin-bottom: 6px; font-weight: 600; }
.key-value-row { display: flex; gap: 8px; align-items: center; }
.key-value {
  flex: 1; background: #f0f0f0; padding: 10px 14px; border-radius: 6px;
  font-family: monospace; font-size: 14px; word-break: break-all; border: 1px solid #ddd;
}
.key-value.secret { color: #999; }
.btn-copy {
  background: #4361ee; color: #fff; border: none; padding: 8px 16px;
  border-radius: 6px; cursor: pointer; font-size: 13px; white-space: nowrap;
}
.btn-copy:hover { background: #3a56d4; }
</style>
