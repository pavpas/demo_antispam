<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'
import LanguageSwitcher from '../components/LanguageSwitcher.vue'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const username = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function handleLogin() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(username.value, password.value)
    router.push('/projects')
  } catch (e) {
    error.value = t('auth.error')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-page">
    <div class="login-card">
      <div class="login-header">
        <span class="login-icon">&#x1f6e1;</span>
        <h1>{{ t('app.title') }}</h1>
        <p>{{ t('app.subtitle') }}</p>
      </div>
      <form @submit.prevent="handleLogin" class="login-form">
        <div class="form-group">
          <label>{{ t('auth.username') }}</label>
          <input v-model="username" type="text" required autofocus />
        </div>
        <div class="form-group">
          <label>{{ t('auth.password') }}</label>
          <input v-model="password" type="password" required />
        </div>
        <p v-if="error" class="error">{{ error }}</p>
        <button type="submit" class="btn-login" :disabled="loading">
          {{ loading ? t('common.loading') : t('auth.login') }}
        </button>
      </form>
      <div class="login-footer">
        <LanguageSwitcher />
      </div>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  display: flex; align-items: center; justify-content: center;
  min-height: 100vh; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
}
.login-card {
  background: #fff; border-radius: 12px; padding: 40px;
  width: 100%; max-width: 400px; box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}
.login-header { text-align: center; margin-bottom: 32px; }
.login-icon { font-size: 48px; display: block; margin-bottom: 12px; }
.login-header h1 { margin: 0; font-size: 24px; color: #1a1a2e; }
.login-header p { margin: 4px 0 0; color: #888; font-size: 14px; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-size: 14px; color: #555; margin-bottom: 6px; font-weight: 500; }
.form-group input {
  width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px;
  font-size: 15px; box-sizing: border-box; transition: border-color 0.2s;
}
.form-group input:focus { border-color: #4361ee; outline: none; }
.error { color: #ef4444; font-size: 14px; margin: 0 0 16px; }
.btn-login {
  width: 100%; padding: 12px; background: #4361ee; color: #fff;
  border: none; border-radius: 8px; font-size: 16px; cursor: pointer; font-weight: 600;
}
.btn-login:hover { background: #3a56d4; }
.btn-login:disabled { opacity: 0.6; cursor: not-allowed; }
.login-footer { display: flex; justify-content: center; margin-top: 24px; }
</style>
