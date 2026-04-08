import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: { 'Content-Type': 'application/json' }
})

// Add JWT token to requests
api.interceptors.request.use(config => {
  const token = localStorage.getItem('access_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Handle 401 — try refresh or redirect to login
api.interceptors.response.use(
  response => response,
  async error => {
    if (error.response?.status === 401 && !error.config._retry) {
      error.config._retry = true
      const refreshToken = localStorage.getItem('refresh_token')
      if (refreshToken) {
        try {
          const { data } = await axios.post('/api/auth/refresh', { refresh_token: refreshToken })
          localStorage.setItem('access_token', data.access_token)
          error.config.headers.Authorization = `Bearer ${data.access_token}`
          return api(error.config)
        } catch {
          localStorage.removeItem('access_token')
          localStorage.removeItem('refresh_token')
          window.location.href = '/vue/login'
        }
      } else {
        window.location.href = '/vue/login'
      }
    }
    return Promise.reject(error)
  }
)

export default {
  // Auth
  login(username, password) {
    return api.post('/auth/login', { username, password })
  },

  // Projects
  getProjects() {
    return api.get('/projects')
  },
  getProject(id) {
    return api.get(`/projects/${id}`)
  },
  createProject(name, domain) {
    return api.post('/projects', { name, domain })
  },
  deleteProject(id) {
    return api.delete(`/projects/${id}`)
  },

  // Stats
  getStats(projectId, from, to, extraParams) {
    const params = { ...(extraParams || {}) }
    if (from) params.from = from
    if (to) params.to = to
    return api.get(`/projects/${projectId}/stats`, { params })
  },

  // Filter Rules
  getRules(projectId) {
    return api.get(`/projects/${projectId}/rules`)
  },
  createRule(projectId, rule) {
    return api.post(`/projects/${projectId}/rules`, rule)
  },
  updateRule(projectId, ruleId, rule) {
    return api.put(`/projects/${projectId}/rules/${ruleId}`, rule)
  },
  deleteRule(projectId, ruleId) {
    return api.delete(`/projects/${projectId}/rules/${ruleId}`)
  },

  // Project Fields
  getFields(projectId) {
    return api.get(`/projects/${projectId}/fields`)
  },
  createField(projectId, name) {
    return api.post(`/projects/${projectId}/fields`, { name })
  },
  deleteField(projectId, fieldId) {
    return api.delete(`/projects/${projectId}/fields/${fieldId}`)
  },
  getFieldValues(projectId, fieldName) {
    return api.get(`/projects/${projectId}/fields/${fieldName}/values`)
  },

  // Logs
  getLogs(projectId, params) {
    return api.get(`/projects/${projectId}/logs`, { params })
  }
}
