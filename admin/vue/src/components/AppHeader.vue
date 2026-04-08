<script setup>
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import LanguageSwitcher from './LanguageSwitcher.vue'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

function logout() {
  auth.logout()
  router.push('/login')
}
</script>

<template>
  <header class="header">
    <div class="header-inner">
      <div class="header-left">
        <a href="/" class="logo-link">
          <span class="logo-icon">&#x1f6e1;</span>
          <span class="logo-text">{{ t('app.title') }}</span>
        </a>
      </div>
      <div class="header-right">
        <LanguageSwitcher />
        <span class="username">{{ auth.username }}</span>
        <button class="btn-logout" @click="logout">{{ t('nav.logout') }}</button>
      </div>
    </div>
  </header>
</template>

<style scoped>
.header {
  background: #1a1a2e;
  color: #fff;
  padding: 0 24px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.header-inner {
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 56px;
}
.header-left { display: flex; align-items: center; gap: 16px; }
.header-right { display: flex; align-items: center; gap: 16px; }
.logo-link {
  display: flex; align-items: center; gap: 8px;
  text-decoration: none; color: #fff; font-size: 18px; font-weight: 700;
}
.logo-icon { font-size: 24px; }
.username { color: #aaa; font-size: 14px; }
.btn-logout {
  background: transparent; border: 1px solid #555; color: #ccc;
  padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 13px;
}
.btn-logout:hover { background: #333; color: #fff; }
</style>
