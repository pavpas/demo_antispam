import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../api/client'

export const useProjectsStore = defineStore('projects', () => {
  const projects = ref([])
  const loading = ref(false)
  const error = ref(null)

  async function fetchProjects() {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.getProjects()
      projects.value = data
    } catch (e) {
      error.value = e.message
    } finally {
      loading.value = false
    }
  }

  async function createProject(name, domain) {
    const { data } = await api.createProject(name, domain)
    projects.value.unshift(data)
    return data
  }

  async function deleteProject(id) {
    await api.deleteProject(id)
    projects.value = projects.value.filter(p => p.id !== id)
  }

  return { projects, loading, error, fetchProjects, createProject, deleteProject }
})
