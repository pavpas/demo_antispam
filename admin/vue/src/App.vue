<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'
import AppHeader from './components/AppHeader.vue'

const router = useRouter()
const auth = useAuthStore()
</script>

<template>
  <div class="app">
    <AppHeader v-if="auth.isAuthenticated" />
    <main class="main-content">
      <router-view />
    </main>
  </div>
</template>

<style scoped>
.app {
  min-height: 100vh;
  background: #f5f7fa;
}
.main-content {
  margin: 0 auto;
  padding: 24px 32px;
}
</style>
