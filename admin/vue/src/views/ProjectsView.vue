<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useProjectsStore } from '../stores/projects'

const { t } = useI18n()
const router = useRouter()
const store = useProjectsStore()

const showModal = ref(false)
const newName = ref('')
const newDomain = ref('')
const creating = ref(false)

onMounted(() => { store.fetchProjects() })

async function createProject() {
  if (!newName.value || !newDomain.value) return
  creating.value = true
  try {
    await store.createProject(newName.value, newDomain.value)
    showModal.value = false
    newName.value = ''
    newDomain.value = ''
  } catch (e) {
    alert(t('common.error'))
  } finally {
    creating.value = false
  }
}

async function deleteProject(project) {
  if (!confirm(t('projects.deleteConfirm', { name: project.name }))) return
  try {
    await store.deleteProject(project.id)
  } catch (e) {
    alert(t('common.error'))
  }
}
</script>

<template>
  <div class="projects-page">
    <div class="page-header">
      <h1>{{ t('projects.title') }}</h1>
      <button class="btn-add" @click="showModal = true">+ {{ t('projects.add') }}</button>
    </div>

    <div v-if="store.loading" class="loading">{{ t('common.loading') }}</div>

    <div v-else-if="store.projects.length === 0" class="empty">
      <p>{{ t('projects.empty') }}</p>
    </div>

    <div v-else class="projects-table">
      <table>
        <thead>
          <tr>
            <th>{{ t('projects.name') }}</th>
            <th>{{ t('projects.domain') }}</th>
            <th>{{ t('projects.stats') }}</th>
            <th>{{ t('projects.details') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in store.projects" :key="p.id">
            <td class="cell-name">{{ p.name }}</td>
            <td class="cell-domain">{{ p.domain }}</td>
            <td>
              <button class="btn-sm btn-stats" @click="router.push(`/projects/${p.id}/stats`)">
                {{ t('projects.stats') }}
              </button>
            </td>
            <td>
              <button class="btn-sm btn-details" @click="router.push(`/projects/${p.id}`)">
                {{ t('projects.details') }}
              </button>
            </td>
            <td>
              <button class="btn-sm btn-delete" @click="deleteProject(p)">
                {{ t('projects.delete') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create Project Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal">
        <h2>{{ t('newProject.title') }}</h2>
        <form @submit.prevent="createProject">
          <div class="form-group">
            <label>{{ t('projects.name') }}</label>
            <input v-model="newName" :placeholder="t('newProject.namePlaceholder')" required />
          </div>
          <div class="form-group">
            <label>{{ t('projects.domain') }}</label>
            <input v-model="newDomain" :placeholder="t('newProject.domainPlaceholder')" required />
          </div>
          <div class="modal-actions">
            <button type="button" class="btn-cancel" @click="showModal = false">{{ t('newProject.cancel') }}</button>
            <button type="submit" class="btn-create" :disabled="creating">{{ t('newProject.create') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.page-header h1 { margin: 0; font-size: 28px; color: #1a1a2e; }
.btn-add {
  background: #4361ee; color: #fff; border: none; padding: 10px 20px;
  border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600;
}
.btn-add:hover { background: #3a56d4; }

.projects-table { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
table { width: 100%; border-collapse: collapse; }
thead { background: #f8f9fa; }
th { text-align: left; padding: 14px 16px; font-size: 13px; color: #666; font-weight: 600; text-transform: uppercase; }
td { padding: 14px 16px; border-top: 1px solid #eee; font-size: 14px; }
.cell-name { font-weight: 600; color: #1a1a2e; }
.cell-domain { color: #666; }

.btn-sm { padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; border: none; }
.btn-stats { background: #e0e7ff; color: #4361ee; }
.btn-stats:hover { background: #c7d2fe; }
.btn-details { background: #f0fdf4; color: #16a34a; }
.btn-details:hover { background: #dcfce7; }
.btn-delete { background: #fef2f2; color: #ef4444; }
.btn-delete:hover { background: #fee2e2; }

.loading, .empty { text-align: center; padding: 60px; color: #888; font-size: 16px; }

/* Modal */
.modal-overlay {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 100;
}
.modal {
  background: #fff; border-radius: 12px; padding: 32px; width: 100%; max-width: 440px;
}
.modal h2 { margin: 0 0 24px; color: #1a1a2e; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-size: 14px; color: #555; margin-bottom: 6px; font-weight: 500; }
.form-group input {
  width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px;
  font-size: 15px; box-sizing: border-box;
}
.form-group input:focus { border-color: #4361ee; outline: none; }
.modal-actions { display: flex; gap: 12px; justify-content: flex-end; }
.btn-cancel { background: #f3f4f6; color: #555; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; }
.btn-create { background: #4361ee; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; }
.btn-create:hover { background: #3a56d4; }
.btn-create:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
