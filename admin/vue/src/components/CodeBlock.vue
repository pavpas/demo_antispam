<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const props = defineProps({ code: String, lang: String })
const copied = ref(false)

function copy() {
  navigator.clipboard.writeText(props.code)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}
</script>

<template>
  <div class="code-wrapper">
    <div class="code-header">
      <span v-if="lang" class="code-lang">{{ lang }}</span>
      <button class="btn-copy" :class="{ copied }" @click="copy">
        <span v-if="copied">&#10003; {{ t('detail.keys.copied') }}</span>
        <span v-else>&#128203; {{ t('detail.keys.copy') }}</span>
      </button>
    </div>
    <pre class="code-block"><code>{{ code }}</code></pre>
  </div>
</template>

<style scoped>
.code-wrapper { position: relative; margin: 12px 0; }
.code-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #12122a; padding: 6px 16px; border-radius: 8px 8px 0 0;
}
.code-lang { color: #888; font-size: 12px; font-weight: 600; text-transform: uppercase; }
.btn-copy {
  background: #2a2a4a; color: #aaa; border: 1px solid #3a3a5a; padding: 4px 12px;
  border-radius: 4px; cursor: pointer; font-size: 12px; transition: all 0.2s;
}
.btn-copy:hover { background: #3a3a5a; color: #fff; }
.btn-copy.copied { background: #166534; border-color: #22c55e; color: #4ade80; }
.code-block {
  background: #1a1a2e; color: #e0e0e0; padding: 16px;
  border-radius: 0 0 8px 8px; margin: 0;
  overflow-x: auto; font-size: 13px; line-height: 1.5;
}
.code-block code { font-family: 'Consolas', 'Monaco', monospace; }
</style>
