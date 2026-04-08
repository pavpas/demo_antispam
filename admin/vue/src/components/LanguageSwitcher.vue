<script setup>
import { useI18n } from 'vue-i18n'

const { locale } = useI18n()

function switchLang(lang) {
  locale.value = lang
  localStorage.setItem('locale', lang)
}
</script>

<template>
  <div class="lang-switcher">
    <button
      :class="['lang-btn', { active: locale === 'ru' }]"
      @click="switchLang('ru')"
    >RU</button>
    <button
      :class="['lang-btn', { active: locale === 'en' }]"
      @click="switchLang('en')"
    >EN</button>
  </div>
</template>

<style scoped>
.lang-switcher { display: flex; gap: 4px; }
.lang-btn {
  background: transparent; border: 1px solid #444; color: #888;
  padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px;
}
.lang-btn.active { background: #16213e; color: #fff; border-color: #4361ee; }
</style>
