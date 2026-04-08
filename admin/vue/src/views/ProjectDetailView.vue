<script setup>
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import api from '../api/client'
import KeyDisplay from '../components/KeyDisplay.vue'
import CodeBlock from '../components/CodeBlock.vue'

const props = defineProps({ id: [String, Number] })
const { t } = useI18n()
const router = useRouter()

const project = ref(null)
const loading = ref(true)
const activeTab = ref('keys')

onMounted(async () => {
  try {
    const { data } = await api.getProject(props.id)
    project.value = data
  } catch (e) {
    router.push('/projects')
  } finally {
    loading.value = false
  }
})

const baseUrl = computed(() => window.location.origin)

const scriptTagAuto = computed(() => {
  if (!project.value) return ''
  return `<script src="${baseUrl.value}/sdk.js?key=${project.value.public_key}"><\/script>`
})

const scriptTagManual = computed(() => {
  if (!project.value) return ''
  return `<script src="${baseUrl.value}/sdk.js?key=${project.value.public_key}&auto=0"><\/script>`
})

const protectById = computed(() => `<script>
  // Protect a specific form by ID
  AntiSpam.protect('#contactForm');
<\/script>`)

const protectByClass = computed(() => `<script>
  // Protect all forms with a class
  AntiSpam.protect('.protected-form');
<\/script>`)

const protectBySelector = computed(() => `<script>
  // Protect by any CSS selector
  AntiSpam.protect('form[data-antispam]');

  // Protect by DOM element
  AntiSpam.protect(document.getElementById('myForm'));
<\/script>`)

const verifyEndpoint = computed(() => `${baseUrl.value}/api/v1/verify`)

const requestExample = computed(() => {
  if (!project.value) return ''
  return `{
  "secret": "${project.value.private_key}",
  "token": "TOKEN_FROM_FORM_FIELD_antispam_token",
  "data": "optional custom data string",
  "filters": {"form": "contact", "method": "POST"}
}`
})

const responseExample = computed(() => {
  if (!project.value) return ''
  return `{
  "success": true,
  "score": 0.87,
  "action": "submit",
  "timestamp": "2026-01-15T12:00:00Z",
  "hostname": "${project.value.domain}"
}`
})

const endpointLine = computed(() => `POST ${verifyEndpoint.value}`)

const phpCode = computed(() => {
  if (!project.value) return ''
  return `<?php
$token = $_POST['_antispam_token'] ?? '';
$secret = '${project.value.private_key}';

$response = file_get_contents('${verifyEndpoint.value}', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode(['secret' => $secret, 'token' => $token])
    ]
]));

$result = json_decode($response, true);
if ($result['success'] && $result['score'] >= 0.5) {
    // Human - process the form
} else {
    // Bot - reject the request
}
?>`
})

const pythonCode = computed(() => {
  if (!project.value) return ''
  return `import requests

token = request.form.get('_antispam_token', '')
response = requests.post('${verifyEndpoint.value}', json={
    'secret': '${project.value.private_key}',
    'token': token
})
result = response.json()

if result.get('success') and result.get('score', 0) >= 0.5:
    # Human - process the form
    pass
else:
    # Bot - reject the request
    pass`
})

const nodeCode = computed(() => {
  if (!project.value) return ''
  return `const token = req.body._antispam_token;
const response = await fetch('${verifyEndpoint.value}', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    secret: '${project.value.private_key}',
    token: token
  })
});
const result = await response.json();

if (result.success && result.score >= 0.5) {
  // Human - process the form
} else {
  // Bot - reject the request
}`
})

const goCode = computed(() => {
  if (!project.value) return ''
  return `token := r.FormValue("_antispam_token")
body, _ := json.Marshal(map[string]string{
    "secret": "${project.value.private_key}",
    "token":  token,
})
resp, _ := http.Post("${verifyEndpoint.value}", "application/json", bytes.NewReader(body))
var result struct {
    Success bool    \`json:"success"\`
    Score   float64 \`json:"score"\`
}
json.NewDecoder(resp.Body).Decode(&result)

if result.Success && result.Score >= 0.5 {
    // Human
} else {
    // Bot
}`
})

const csharpCode = computed(() => {
  if (!project.value) return ''
  return `var token = Request.Form["_antispam_token"];
var client = new HttpClient();
var response = await client.PostAsJsonAsync("${verifyEndpoint.value}", new {
    secret = "${project.value.private_key}",
    token = token
});
var result = await response.Content.ReadFromJsonAsync<VerifyResult>();

if (result.Success && result.Score >= 0.5) {
    // Human
} else {
    // Bot
}`
})

const rubyCode = computed(() => {
  if (!project.value) return ''
  return `require 'net/http'
require 'json'

token = params[:_antispam_token]
uri = URI('${verifyEndpoint.value}')
res = Net::HTTP.post(uri, { secret: '${project.value.private_key}', token: token }.to_json,
                     'Content-Type' => 'application/json')
result = JSON.parse(res.body)

if result['success'] && result['score'] >= 0.5
  # Human
else
  # Bot
end`
})

// Rules tab
const rules = ref([])
const rulesLoading = ref(false)
const showRuleForm = ref(false)
const editingRule = ref(null)
const ruleForm = ref({ name: '', action: 'block', priority: 0, is_active: true })
const ruleConditions = ref([{ condition_type: 'ip', condition_value: '', header_name: '', header_value: '' }])

async function loadRules() {
  rulesLoading.value = true
  try {
    const { data } = await api.getRules(props.id)
    rules.value = data
  } catch (e) {
    rules.value = []
  } finally {
    rulesLoading.value = false
  }
}

function openRuleForm(rule = null) {
  if (rule) {
    editingRule.value = rule
    ruleForm.value = { name: rule.name, action: rule.action, priority: rule.priority, is_active: rule.is_active }
    ruleConditions.value = (rule.conditions || []).map(c => {
      const row = { condition_type: c.condition_type, condition_value: c.condition_value, header_name: '', header_value: '' }
      if (c.condition_type === 'header') {
        try {
          const h = JSON.parse(c.condition_value)
          row.header_name = h.name || ''
          row.header_value = h.value || ''
        } catch {}
      }
      return row
    })
    if (ruleConditions.value.length === 0) {
      ruleConditions.value = [{ condition_type: 'ip', condition_value: '', header_name: '', header_value: '' }]
    }
  } else {
    editingRule.value = null
    ruleForm.value = { name: '', action: 'block', priority: 0, is_active: true }
    ruleConditions.value = [{ condition_type: 'ip', condition_value: '', header_name: '', header_value: '' }]
  }
  showRuleForm.value = true
}

function addCondition() {
  ruleConditions.value.push({ condition_type: 'ip', condition_value: '', header_name: '', header_value: '' })
}

function removeCondition(index) {
  if (ruleConditions.value.length > 1) {
    ruleConditions.value.splice(index, 1)
  }
}

async function saveRule() {
  const conditions = ruleConditions.value.map(c => {
    let value = c.condition_value
    if (c.condition_type === 'header') {
      value = JSON.stringify({ name: c.header_name, value: c.header_value })
    }
    return { condition_type: c.condition_type, condition_value: value }
  })
  const data = { name: ruleForm.value.name, action: ruleForm.value.action, priority: ruleForm.value.priority, is_active: ruleForm.value.is_active, conditions }
  try {
    if (editingRule.value) {
      await api.updateRule(props.id, editingRule.value.id, data)
    } else {
      await api.createRule(props.id, data)
    }
    showRuleForm.value = false
    await loadRules()
  } catch (e) {
    alert(e.response?.data?.error || 'Error saving rule')
  }
}

async function deleteRule(rule) {
  if (!confirm(t('detail.rules.deleteConfirm', { name: rule.name }))) return
  try {
    await api.deleteRule(props.id, rule.id)
    await loadRules()
  } catch (e) {
    alert('Error deleting rule')
  }
}

async function toggleRule(rule) {
  try {
    await api.updateRule(props.id, rule.id, { name: rule.name, action: rule.action, priority: rule.priority, is_active: !rule.is_active, conditions: (rule.conditions || []).map(c => ({ condition_type: c.condition_type, condition_value: c.condition_value })) })
    await loadRules()
  } catch (e) {
    alert('Error updating rule')
  }
}

function ruleTypeLabel(type) {
  const labels = { ip: t('detail.rules.typeIp'), ip_range: t('detail.rules.typeIpRange'), user_agent: t('detail.rules.typeUserAgent'), header: t('detail.rules.typeHeader'), score: t('detail.rules.typeScore') }
  return labels[type] || type
}

function conditionSummary(rule) {
  return (rule.conditions || []).map(c => {
    const label = ruleTypeLabel(c.condition_type)
    let val = c.condition_value
    if (c.condition_type === 'header') {
      try { const h = JSON.parse(val); val = h.name + ': ' + h.value } catch {}
    }
    if (val && val.length > 25) val = val.substring(0, 25) + '...'
    return label + ': ' + val
  })
}

// Logs tab
const logs = ref([])
const logsLoading = ref(false)
const logsStatus = ref('all')
const logsPage = ref(1)
const logsTotalPages = ref(1)
const logsTotal = ref(0)
const logsIpFilter = ref('')
const logFieldFilters = ref({})
const logFieldOptions = ref({})

async function loadLogFieldOptions() {
  try {
    const { data: projectFields } = await api.getFields(props.id)
    for (const f of projectFields) {
      const { data: values } = await api.getFieldValues(props.id, f.field_name)
      logFieldOptions.value[f.field_name] = values
      if (!(f.field_name in logFieldFilters.value)) {
        logFieldFilters.value[f.field_name] = ''
      }
    }
  } catch(e) {}
}

async function loadLogs() {
  logsLoading.value = true
  try {
    const params = { status: logsStatus.value, page: logsPage.value, per_page: 30, ip: logsIpFilter.value || undefined }
    for (const [fieldName, value] of Object.entries(logFieldFilters.value)) {
      if (value) params[`field_${fieldName}`] = value
    }
    const { data } = await api.getLogs(props.id, params)
    logs.value = data.logs
    logsTotalPages.value = data.total_pages
    logsTotal.value = data.total
  } catch (e) {
    logs.value = []
  } finally {
    logsLoading.value = false
  }
}

function changeLogsStatus(status) {
  logsStatus.value = status
  logsPage.value = 1
  loadLogs()
}

function logsPageChange(delta) {
  const next = logsPage.value + delta
  if (next >= 1 && next <= logsTotalPages.value) {
    logsPage.value = next
    loadLogs()
  }
}

function formatDate(d) {
  if (!d) return '-'
  const dt = new Date(d)
  return dt.toLocaleString()
}

function truncate(s, max) {
  if (!s) return '-'
  return s.length > max ? s.substring(0, max) + '...' : s
}

// Logs accordion
const expandedLogId = ref(null)

function hasLogDetails(log) {
  return !!(log.custom_data || (log.field_values && Object.keys(log.field_values).length))
}

function toggleLogExpand(id) {
  expandedLogId.value = expandedLogId.value === id ? null : id
}

// Fields tab
const fields = ref([])
const fieldsLoading = ref(false)
const newFieldName = ref('')

async function loadFields() {
  fieldsLoading.value = true
  try {
    const { data } = await api.getFields(props.id)
    fields.value = data
  } catch (e) {
    fields.value = []
  } finally {
    fieldsLoading.value = false
  }
}

async function addField() {
  if (!newFieldName.value.trim()) return
  try {
    await api.createField(props.id, newFieldName.value.trim())
    newFieldName.value = ''
    await loadFields()
  } catch (e) {
    alert(e.response?.data?.error || 'Error')
  }
}

async function deleteField(field) {
  if (!confirm(t('detail.fields.deleteConfirm', { name: field.field_name }))) return
  await api.deleteField(props.id, field.id)
  await loadFields()
}

// Testing tab
const testForm = ref({ name: 'Test User', email: 'test@example.com', message: 'Hello, this is a test message.' })
const testResult = ref(null)
const testLoading = ref(false)
const sdkLoaded = ref(false)

function loadSDK() {
  if (sdkLoaded.value || !project.value) return
  const existing = document.getElementById('antispam-test-sdk')
  if (existing) existing.remove()
  const script = document.createElement('script')
  script.id = 'antispam-test-sdk'
  script.src = `${baseUrl.value}/sdk.js?key=${project.value.public_key}&auto=0`
  document.body.appendChild(script)
  sdkLoaded.value = true
}

async function submitTestForm() {
  testLoading.value = true
  testResult.value = null

  if (typeof window.AntiSpam === 'undefined') {
    testResult.value = { type: 'error', message: t('detail.testing.noToken') }
    testLoading.value = false
    return
  }

  window.AntiSpam.getToken(async (err, token) => {
    if (err || !token) {
      testResult.value = { type: 'error', message: t('detail.testing.noToken') }
      testLoading.value = false
      return
    }

    try {
      const resp = await fetch(`${baseUrl.value}/api/v1/verify`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ secret: project.value.private_key, token })
      })
      const data = await resp.json()

      if (data.success && data.score >= 0.5) {
        testResult.value = { type: 'success', data }
      } else {
        testResult.value = { type: 'bot', data }
      }
    } catch (e) {
      testResult.value = { type: 'error', message: e.message }
    } finally {
      testLoading.value = false
    }
  })
}
</script>

<template>
  <div class="detail-page" v-if="!loading && project">
    <div class="detail-header">
      <button class="btn-back" @click="router.push('/projects')">&#8592; {{ t('common.back') }}</button>
      <h1>{{ project.name }}</h1>
      <span class="domain-badge">{{ project.domain }}</span>
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <button :class="['tab', { active: activeTab === 'keys' }]" @click="activeTab = 'keys'">
        {{ t('detail.tabs.keys') }}
      </button>
      <button :class="['tab', { active: activeTab === 'frontend' }]" @click="activeTab = 'frontend'">
        {{ t('detail.tabs.frontend') }}
      </button>
      <button :class="['tab', { active: activeTab === 'backend' }]" @click="activeTab = 'backend'">
        {{ t('detail.tabs.backend') }}
      </button>
      <button :class="['tab', { active: activeTab === 'rules' }]" @click="activeTab = 'rules'; loadRules()">
        {{ t('detail.tabs.rules') }}
      </button>
      <button :class="['tab', { active: activeTab === 'logs' }]" @click="activeTab = 'logs'; loadLogs(); loadLogFieldOptions()">
        {{ t('detail.tabs.logs') }}
      </button>
      <button :class="['tab', { active: activeTab === 'fields' }]" @click="activeTab = 'fields'; loadFields()">
        {{ t('detail.tabs.fields') }}
      </button>
      <button :class="['tab', { active: activeTab === 'testing' }]" @click="activeTab = 'testing'; loadSDK()">
        {{ t('detail.tabs.testing') }}
      </button>
    </div>

    <!-- Keys Tab -->
    <div v-if="activeTab === 'keys'" class="tab-content">
      <KeyDisplay :label="t('detail.keys.public')" :value="project.public_key" />
      <KeyDisplay :label="t('detail.keys.private')" :value="project.private_key" :secret="true" />
      <div class="warning-box">
        &#x26a0;&#xfe0f; {{ t('detail.keys.warning') }}
      </div>
    </div>

    <!-- Frontend Tab -->
    <div v-if="activeTab === 'frontend'" class="tab-content">
      <h3>{{ t('detail.frontend.title') }}</h3>

      <div class="info-box">
        {{ t('detail.frontend.paramNote') }}
      </div>

      <!-- Auto mode -->
      <h4>{{ t('detail.frontend.autoTitle') }}</h4>
      <p>{{ t('detail.frontend.autoDesc') }}</p>
      <p>{{ t('detail.frontend.autoStep') }}</p>
      <CodeBlock :code="scriptTagAuto" lang="HTML" />

      <div class="divider"></div>

      <!-- Manual mode -->
      <h4>{{ t('detail.frontend.manualTitle') }}</h4>
      <p>{{ t('detail.frontend.manualDesc') }}</p>
      <p>{{ t('detail.frontend.manualStep') }}</p>
      <CodeBlock :code="scriptTagManual" lang="HTML" />

      <p>{{ t('detail.frontend.manualExamples') }}</p>

      <p class="example-label">{{ t('detail.frontend.byId') }}</p>
      <CodeBlock :code="protectById" lang="JavaScript" />

      <p class="example-label">{{ t('detail.frontend.byClass') }}</p>
      <CodeBlock :code="protectByClass" lang="JavaScript" />

      <p class="example-label">{{ t('detail.frontend.bySelector') }}</p>
      <CodeBlock :code="protectBySelector" lang="JavaScript" />

      <div class="info-box">
        {{ t('detail.frontend.note') }}
      </div>
    </div>

    <!-- Backend Tab -->
    <div v-if="activeTab === 'backend'" class="tab-content">
      <h3>{{ t('detail.backend.title') }}</h3>
      <p>{{ t('detail.backend.description') }}</p>

      <h4>{{ t('detail.backend.endpoint') }}</h4>
      <CodeBlock :code="endpointLine" />

      <h4>{{ t('detail.backend.request') }}</h4>
      <CodeBlock :code="requestExample" lang="JSON" />

      <h4>{{ t('detail.backend.response') }}</h4>
      <CodeBlock :code="responseExample" lang="JSON" />

      <div class="info-box">{{ t('detail.backend.scoreNote') }}</div>

      <div class="info-box">
        <p><strong>data</strong> — arbitrary string, stored as-is, visible in Logs tab.</p>
        <p><strong>filters</strong> — key-value pairs for categorization, only keys defined in project Fields tab are stored.</p>
      </div>

      <h3>{{ t('detail.backend.examples') }}</h3>

      <CodeBlock :code="phpCode" lang="PHP" />
      <CodeBlock :code="pythonCode" lang="Python" />
      <CodeBlock :code="nodeCode" lang="Node.js" />
      <CodeBlock :code="goCode" lang="Go" />
      <CodeBlock :code="csharpCode" lang="C#" />
      <CodeBlock :code="rubyCode" lang="Ruby" />
    </div>

    <!-- Rules Tab -->
    <div v-if="activeTab === 'rules'" class="tab-content">
      <div class="tab-header">
        <h3>{{ t('detail.rules.title') }}</h3>
        <button class="btn-primary" @click="openRuleForm()">+ {{ t('detail.rules.add') }}</button>
      </div>

      <!-- Rule Form -->
      <div v-if="showRuleForm" class="rule-form-overlay">
        <div class="rule-form">
          <h4>{{ editingRule ? t('detail.rules.edit') : t('detail.rules.add') }}</h4>
          <div class="form-group">
            <label>{{ t('detail.rules.name') }}</label>
            <input v-model="ruleForm.name" type="text" required />
          </div>

          <div class="conditions-section">
            <label>{{ t('detail.rules.conditions') }} <span class="hint">({{ t('detail.rules.conditionsHint') }})</span></label>
            <div v-for="(cond, idx) in ruleConditions" :key="idx" class="condition-row">
              <select v-model="cond.condition_type" class="condition-type">
                <option value="ip">{{ t('detail.rules.typeIp') }}</option>
                <option value="ip_range">{{ t('detail.rules.typeIpRange') }}</option>
                <option value="user_agent">{{ t('detail.rules.typeUserAgent') }}</option>
                <option value="header">{{ t('detail.rules.typeHeader') }}</option>
                <option value="score">{{ t('detail.rules.typeScore') }}</option>
              </select>
              <template v-if="cond.condition_type === 'header'">
                <input v-model="cond.header_name" type="text" :placeholder="t('detail.rules.placeholderHeaderName')" class="condition-value" />
                <input v-model="cond.header_value" type="text" :placeholder="t('detail.rules.placeholderHeaderValue')" class="condition-value" />
              </template>
              <template v-else>
                <input v-model="cond.condition_value" type="text" class="condition-value"
                  :placeholder="cond.condition_type === 'ip' ? t('detail.rules.placeholderIp') : cond.condition_type === 'ip_range' ? t('detail.rules.placeholderIpRange') : cond.condition_type === 'user_agent' ? t('detail.rules.placeholderUa') : t('detail.rules.placeholderScore')" />
              </template>
              <button class="btn-icon danger" @click="removeCondition(idx)" :disabled="ruleConditions.length <= 1" title="Remove">&#10005;</button>
            </div>
            <button class="btn-add-condition" @click="addCondition">+ {{ t('detail.rules.addCondition') }}</button>
          </div>

          <div class="form-group">
            <label>{{ t('detail.rules.action') }}</label>
            <select v-model="ruleForm.action">
              <option value="block">{{ t('detail.rules.block') }}</option>
              <option value="allow">{{ t('detail.rules.allow') }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>{{ t('detail.rules.priority') }}</label>
            <input v-model.number="ruleForm.priority" type="number" min="0" />
          </div>
          <div class="form-group">
            <label class="toggle-switch-label">
              <span class="toggle-switch">
                <input type="checkbox" v-model="ruleForm.is_active" />
                <span class="toggle-slider"></span>
              </span>
              <span>{{ t('detail.rules.active') }}</span>
            </label>
          </div>
          <div class="form-actions">
            <button class="btn-primary" @click="saveRule">{{ t('detail.rules.save') }}</button>
            <button class="btn-secondary" @click="showRuleForm = false">{{ t('detail.rules.cancel') }}</button>
          </div>
        </div>
      </div>

      <!-- Rules List -->
      <div v-if="rulesLoading" class="loading">{{ t('common.loading') }}</div>
      <div v-else-if="rules.length === 0" class="empty-state">{{ t('detail.rules.empty') }}</div>
      <div v-else class="rules-table">
        <table>
          <thead>
            <tr>
              <th>{{ t('detail.rules.name') }}</th>
              <th>{{ t('detail.rules.conditions') }}</th>
              <th>{{ t('detail.rules.action') }}</th>
              <th>{{ t('detail.rules.priority') }}</th>
              <th>{{ t('detail.logs.status') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rule in rules" :key="rule.id" :class="{ inactive: !rule.is_active }">
              <td>{{ rule.name }}</td>
              <td class="conditions-cell">
                <span v-for="(label, i) in conditionSummary(rule)" :key="i" class="condition-tag">{{ label }}</span>
              </td>
              <td><span :class="['action-badge', rule.action]">{{ rule.action === 'block' ? t('detail.rules.block') : t('detail.rules.allow') }}</span></td>
              <td>{{ rule.priority }}</td>
              <td>
                <label class="toggle-switch" @click.prevent="toggleRule(rule)">
                  <input type="checkbox" :checked="rule.is_active" />
                  <span class="toggle-slider"></span>
                </label>
              </td>
              <td class="actions-cell">
                <button class="btn-icon" @click="openRuleForm(rule)" title="Edit">&#9998;</button>
                <button class="btn-icon danger" @click="deleteRule(rule)" title="Delete">&#10005;</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Logs Tab -->
    <div v-if="activeTab === 'logs'" class="tab-content">
      <h3>{{ t('detail.logs.title') }}</h3>

      <div class="logs-filters">
        <div class="sub-tabs">
          <button v-for="s in ['all', 'success', 'blocked', 'filtered']" :key="s"
            :class="['sub-tab', { active: logsStatus === s }]"
            @click="changeLogsStatus(s)">
            {{ t('detail.logs.' + (s === 'success' ? 'successful' : s === 'blocked' ? 'blocked' : s === 'filtered' ? 'filtered' : 'all')) }}
          </button>
        </div>
        <input class="ip-filter" v-model="logsIpFilter" :placeholder="t('detail.logs.filterIp')"
          @keyup.enter="logsPage = 1; loadLogs()" />
        <select v-for="(options, fieldName) in logFieldOptions" :key="fieldName"
          v-model="logFieldFilters[fieldName]" @change="logsPage = 1; loadLogs()" class="field-filter">
          <option value="">{{ fieldName }}: {{ t('detail.logs.allValues') }}</option>
          <option v-for="val in options" :key="val" :value="val">{{ val }}</option>
        </select>
      </div>

      <div v-if="logsLoading" class="loading">{{ t('common.loading') }}</div>
      <div v-else-if="logs.length === 0" class="empty-state">{{ t('detail.logs.noLogs') }}</div>
      <div v-else class="logs-table">
        <table>
          <thead>
            <tr>
              <th>{{ t('detail.logs.date') }}</th>
              <th>{{ t('detail.logs.ip') }}</th>
              <th>{{ t('detail.logs.userAgent') }}</th>
              <th>{{ t('detail.logs.score') }}</th>
              <th>{{ t('detail.logs.status') }}</th>
              <th>{{ t('detail.logs.rule') }}</th>
              <th style="width:30px"></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="log in logs" :key="log.id">
              <tr :class="{ 'log-row-expandable': hasLogDetails(log) }"
                  @click="hasLogDetails(log) && toggleLogExpand(log.id)">
                <td class="date-cell">{{ formatDate(log.created_at) }}</td>
                <td>{{ log.ip_address }}</td>
                <td class="ua-cell" :title="log.user_agent">{{ truncate(log.user_agent, 30) }}</td>
                <td><span class="score-value" :class="log.score >= 0.5 ? 'good' : 'bad'">{{ log.score }}</span></td>
                <td>
                  <span v-if="log.filter_action === 'blocked'" class="status-badge filtered">{{ t('detail.logs.filterBlocked') }}</span>
                  <span v-else-if="log.is_human" class="status-badge human">{{ t('detail.logs.human') }}</span>
                  <span v-else class="status-badge bot">{{ t('detail.logs.bot') }}</span>
                </td>
                <td>{{ log.matched_rule_name || '-' }}</td>
                <td class="expand-cell">
                  <span v-if="hasLogDetails(log)" class="log-expand-btn" :class="{ open: expandedLogId === log.id }">&#9654;</span>
                </td>
              </tr>
              <tr v-if="expandedLogId === log.id" class="log-detail-row">
                <td colspan="7" class="log-detail-content">
                  <div v-if="log.field_values && Object.keys(log.field_values).length" class="log-detail-fields">
                    <span v-for="(val, key) in log.field_values" :key="key" class="log-field-tag">
                      <strong>{{ key }}:</strong> {{ val }}
                    </span>
                  </div>
                  <div v-if="log.custom_data" class="log-detail-data">
                    <strong>Data:</strong> {{ log.custom_data }}
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>

        <div class="pagination" v-if="logsTotalPages > 1">
          <button :disabled="logsPage <= 1" @click="logsPageChange(-1)">{{ t('detail.logs.prev') }}</button>
          <span>{{ t('detail.logs.page') }} {{ logsPage }} / {{ logsTotalPages }} ({{ logsTotal }})</span>
          <button :disabled="logsPage >= logsTotalPages" @click="logsPageChange(1)">{{ t('detail.logs.next') }}</button>
        </div>
      </div>
    </div>

    <!-- Fields Tab -->
    <div v-if="activeTab === 'fields'" class="tab-content">
      <h3>{{ t('detail.fields.title') }}</h3>
      <div class="rule-form-box">
        <form @submit.prevent="addField" class="form-row">
          <input v-model="newFieldName" :placeholder="t('detail.fields.placeholder')" class="form-input" style="flex:1" />
          <button type="submit" class="btn-primary">{{ t('detail.fields.add') }}</button>
        </form>
      </div>
      <div v-if="fieldsLoading" class="loading">{{ t('common.loading') }}</div>
      <div v-else-if="fields.length === 0" class="empty-state">{{ t('detail.fields.empty') }}</div>
      <table v-else class="data-table">
        <thead><tr><th>{{ t('detail.fields.name') }}</th><th style="width:100px">{{ t('detail.rules.action') }}</th></tr></thead>
        <tbody>
          <tr v-for="f in fields" :key="f.id">
            <td><code>{{ f.field_name }}</code></td>
            <td><button class="btn-sm danger" @click="deleteField(f)">{{ t('detail.fields.delete') }}</button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Testing Tab -->
    <div v-if="activeTab === 'testing'" class="tab-content">
      <h3>{{ t('detail.testing.title') }}</h3>
      <p>{{ t('detail.testing.description') }}</p>

      <form id="antispam-test-form" class="test-form" @submit.prevent="submitTestForm">
        <div class="form-group">
          <label>{{ t('detail.testing.formName') }}</label>
          <input type="text" v-model="testForm.name" required />
        </div>
        <div class="form-group">
          <label>{{ t('detail.testing.formEmail') }}</label>
          <input type="email" v-model="testForm.email" required />
        </div>
        <div class="form-group">
          <label>{{ t('detail.testing.formMessage') }}</label>
          <textarea v-model="testForm.message" rows="3"></textarea>
        </div>
        <button type="submit" class="btn-submit" :disabled="testLoading">
          {{ testLoading ? t('detail.testing.verifying') : t('detail.testing.submit') }}
        </button>
      </form>

      <div v-if="testResult" :class="['test-result', testResult.type]">
        <template v-if="testResult.type === 'success'">
          <strong>{{ t('detail.testing.resultHuman') }}</strong>
          <pre>{{ JSON.stringify(testResult.data, null, 2) }}</pre>
        </template>
        <template v-else-if="testResult.type === 'bot'">
          <strong>{{ t('detail.testing.resultBot') }}</strong>
          <pre>{{ JSON.stringify(testResult.data, null, 2) }}</pre>
        </template>
        <template v-else>
          <strong>{{ t('detail.testing.resultError') }}:</strong> {{ testResult.message }}
        </template>
      </div>
    </div>
  </div>

  <div v-else-if="loading" class="loading">{{ t('common.loading') }}</div>
</template>

<style scoped>
.detail-page { background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.detail-header { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
.detail-header h1 { margin: 0; font-size: 24px; color: #1a1a2e; }
.domain-badge { background: #e0e7ff; color: #4361ee; padding: 4px 12px; border-radius: 20px; font-size: 13px; }
.btn-back {
  background: #f3f4f6; border: none; padding: 8px 16px; border-radius: 6px;
  cursor: pointer; font-size: 14px; color: #555;
}
.btn-back:hover { background: #e5e7eb; }

.tabs { display: flex; gap: 4px; margin-bottom: 24px; border-bottom: 2px solid #eee; padding-bottom: 0; }
.tab {
  background: none; border: none; padding: 10px 20px; cursor: pointer;
  font-size: 14px; color: #888; border-bottom: 2px solid transparent; margin-bottom: -2px;
}
.tab.active { color: #4361ee; border-bottom-color: #4361ee; font-weight: 600; }
.tab:hover { color: #333; }

.tab-content { animation: fadeIn 0.2s ease-in; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.warning-box {
  background: #fffbeb; border: 1px solid #fcd34d; padding: 12px 16px;
  border-radius: 8px; color: #92400e; font-size: 14px; margin-top: 16px;
}
.info-box {
  background: #eff6ff; border: 1px solid #93c5fd; padding: 12px 16px;
  border-radius: 8px; color: #1e40af; font-size: 14px; margin: 12px 0;
}

h3 { color: #1a1a2e; margin: 24px 0 12px; }
h4 { color: #4361ee; margin: 20px 0 8px; font-size: 15px; }

.divider { border-top: 1px solid #eee; margin: 28px 0; }
.example-label { font-weight: 600; color: #555; font-size: 14px; margin: 16px 0 4px; }

.loading { text-align: center; padding: 60px; color: #888; }

.test-form { max-width: 500px; margin: 20px 0; }
.test-form .form-group { margin-bottom: 16px; }
.test-form label { display: block; margin-bottom: 4px; font-weight: 600; color: #555; font-size: 14px; }
.test-form input, .test-form textarea {
  width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px;
  font-size: 14px; box-sizing: border-box;
}
.test-form input:focus, .test-form textarea:focus { border-color: #4361ee; outline: none; }
.btn-submit {
  background: #4361ee; color: #fff; border: none; padding: 10px 24px;
  border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600;
}
.btn-submit:hover { background: #3651d4; }
.btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }

.test-result {
  margin-top: 20px; padding: 16px; border-radius: 8px; font-size: 14px;
}
.test-result.success { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }
.test-result.bot { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
.test-result.error { background: #fef3c7; border: 1px solid #fcd34d; color: #92400e; }
.test-result pre { margin: 8px 0 0; white-space: pre-wrap; font-size: 13px; }

/* Rules Tab */
.tab-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.tab-header h3 { margin: 0; }
.btn-primary {
  background: #4361ee; color: #fff; border: none; padding: 8px 16px;
  border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600;
}
.btn-primary:hover { background: #3651d4; }
.btn-secondary {
  background: #f3f4f6; color: #555; border: none; padding: 8px 16px;
  border-radius: 6px; cursor: pointer; font-size: 14px;
}
.btn-secondary:hover { background: #e5e7eb; }

.rule-form-overlay {
  background: rgba(0,0,0,0.1); border-radius: 8px; padding: 20px; margin-bottom: 20px;
}
.rule-form h4 { margin: 0 0 16px; color: #1a1a2e; }
.rule-form .form-group { margin-bottom: 12px; }
.rule-form label { display: block; margin-bottom: 4px; font-weight: 600; color: #555; font-size: 13px; }
.rule-form input, .rule-form select {
  width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px;
  font-size: 14px; box-sizing: border-box;
}
.rule-form .toggle-switch input { width: 0; height: 0; padding: 0; border: none; }
.rule-form .toggle-switch-label { display: flex; align-items: center; gap: 10px; font-weight: normal; margin-bottom: 0; }
.rule-form input:focus, .rule-form select:focus { border-color: #4361ee; outline: none; }
.form-row { display: flex; gap: 12px; }
.form-row .form-group { flex: 1; }
.form-actions { display: flex; gap: 8px; margin-top: 16px; }

.rules-table { overflow-x: auto; }
.rules-table table { width: 100%; border-collapse: collapse; font-size: 14px; }
.rules-table th { text-align: left; padding: 10px 12px; border-bottom: 2px solid #eee; color: #888; font-size: 12px; text-transform: uppercase; }
.rules-table td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; }
.rules-table tr.inactive { opacity: 0.5; }
.type-badge { background: #e0e7ff; color: #4361ee; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
.action-badge { padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
.action-badge.block { background: #fee2e2; color: #991b1b; }
.action-badge.allow { background: #d1fae5; color: #065f46; }
/* Conditions */
.conditions-section { margin-bottom: 16px; }
.conditions-section > label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 13px; }
.conditions-section .hint { font-weight: 400; color: #999; font-size: 12px; }
.condition-row { display: flex; gap: 8px; align-items: center; margin-bottom: 6px; }
.condition-type { width: 160px; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
.condition-value { flex: 1; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
.condition-type:focus, .condition-value:focus { border-color: #4361ee; outline: none; }
.btn-add-condition { background: none; border: 1px dashed #ccc; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; color: #888; margin-top: 4px; }
.btn-add-condition:hover { border-color: #4361ee; color: #4361ee; }
.conditions-cell { max-width: 400px; }
.condition-tag { display: inline-block; background: #e0e7ff; color: #4361ee; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin: 1px 3px 1px 0; white-space: nowrap; }
/* Toggle switch */
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
  flex-shrink: 0;
  cursor: pointer;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
.toggle-slider {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  background: #ccc;
  border-radius: 24px;
  transition: background 0.2s;
}
.toggle-slider::before {
  content: '';
  position: absolute;
  width: 18px;
  height: 18px;
  left: 3px;
  bottom: 3px;
  background: #fff;
  border-radius: 50%;
  transition: transform 0.2s;
}
.toggle-switch input:checked + .toggle-slider { background: #4361ee; }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }
.toggle-switch-label {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  font-size: 13px;
  color: #555;
}
.btn-icon { background: none; border: none; cursor: pointer; font-size: 16px; padding: 4px 6px; color: #888; }
.btn-icon:hover { color: #333; }
.btn-icon.danger:hover { color: #dc2626; }
.actions-cell { white-space: nowrap; }
.empty-state { text-align: center; padding: 40px; color: #888; font-size: 14px; }

/* Logs Tab */
.logs-filters { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
.sub-tabs { display: flex; gap: 4px; }
.sub-tab {
  background: #f3f4f6; border: none; padding: 6px 14px; border-radius: 6px;
  cursor: pointer; font-size: 13px; color: #555;
}
.sub-tab.active { background: #4361ee; color: #fff; }
.sub-tab:hover { background: #e5e7eb; }
.sub-tab.active:hover { background: #3651d4; }
.ip-filter {
  padding: 6px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; width: 200px;
}
.ip-filter:focus { border-color: #4361ee; outline: none; }

.logs-table { overflow-x: auto; }
.logs-table table { width: 100%; border-collapse: collapse; font-size: 13px; }
.logs-table th { text-align: left; padding: 8px 10px; border-bottom: 2px solid #eee; color: #888; font-size: 11px; text-transform: uppercase; }
.logs-table td { padding: 8px 10px; border-bottom: 1px solid #f3f4f6; }
.date-cell { white-space: nowrap; font-size: 12px; color: #555; }
.ua-cell { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; }
.score-value { font-weight: 600; font-family: monospace; }
.score-value.good { color: #065f46; }
.score-value.bad { color: #991b1b; }
.status-badge { padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
.status-badge.human { background: #d1fae5; color: #065f46; }
.status-badge.bot { background: #fee2e2; color: #991b1b; }
.status-badge.filtered { background: #fef3c7; color: #92400e; }

.pagination {
  display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 16px; padding: 12px 0;
}
.pagination button {
  background: #f3f4f6; border: none; padding: 6px 14px; border-radius: 6px;
  cursor: pointer; font-size: 13px; color: #555;
}
.pagination button:hover:not(:disabled) { background: #e5e7eb; }
.pagination button:disabled { opacity: 0.4; cursor: not-allowed; }
.pagination span { font-size: 13px; color: #888; }

/* Fields Tab */
.rule-form-box { margin-bottom: 16px; }
.form-input {
  padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;
}
.form-input:focus { border-color: #4361ee; outline: none; }
.data-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.data-table th { text-align: left; padding: 10px 12px; border-bottom: 2px solid #eee; color: #888; font-size: 12px; text-transform: uppercase; }
.data-table td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; }
.data-table code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
.btn-sm {
  border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 12px;
  background: #f3f4f6; color: #555;
}
.btn-sm.danger { background: #fee2e2; color: #991b1b; }
.btn-sm.danger:hover { background: #fca5a5; }
.field-filter {
  padding: 6px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;
}
.field-filter:focus { border-color: #4361ee; outline: none; }

/* Logs accordion */
.log-row-expandable { cursor: pointer; }
.log-row-expandable:hover { background: #f8f9fa; }
.expand-cell { width: 30px; text-align: center; }
.log-expand-btn { color: #aaa; font-size: 10px; transition: transform 0.2s; display: inline-block; }
.log-expand-btn.open { transform: rotate(90deg); }
.log-detail-row td { padding: 8px 12px !important; background: #f9fafb; border-bottom: 1px solid #eee; }
.log-detail-content { font-size: 13px; line-height: 1.8; }
.log-detail-fields { margin-bottom: 4px; }
.log-field-tag { display: inline-block; background: #e0e7ff; color: #4361ee; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-right: 6px; margin-bottom: 4px; }
.log-field-tag strong { color: #333; }
.log-detail-data { color: #555; word-break: break-all; font-family: monospace; font-size: 12px; }
.log-detail-data strong { color: #333; font-family: -apple-system, sans-serif; }
</style>
