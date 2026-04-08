<script setup>
import { ref, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import api from '../api/client'
import StatsChart from '../components/StatsChart.vue'

const props = defineProps({ id: [String, Number] })
const { t } = useI18n()
const router = useRouter()

const stats = ref(null)
const project = ref(null)
const loading = ref(true)
const period = ref('30')
const customFrom = ref('')
const customTo = ref('')
const statsFieldFilters = ref({})
const statsFieldOptions = ref({})

function getDateRange(days) {
  const to = new Date()
  const from = new Date()
  from.setDate(from.getDate() - parseInt(days))
  return {
    from: from.toISOString().split('T')[0],
    to: to.toISOString().split('T')[0]
  }
}

async function fetchStats() {
  loading.value = true
  try {
    let from, to
    if (customFrom.value && customTo.value) {
      from = customFrom.value
      to = customTo.value
    } else {
      const range = getDateRange(period.value || 30)
      from = range.from
      to = range.to
    }
    const params = {}
    for (const [fieldName, value] of Object.entries(statsFieldFilters.value)) {
      if (value) params[`field_${fieldName}`] = value
    }
    const [statsRes, projectRes] = await Promise.all([
      api.getStats(props.id, from, to, params),
      api.getProject(props.id)
    ])
    stats.value = statsRes.data
    project.value = projectRes.data
  } catch (e) {
    console.error('Failed to fetch stats:', e)
  } finally {
    loading.value = false
  }
}

function fetchCustomRange() {
  if (customFrom.value && customTo.value) {
    period.value = ''
    fetchStats()
  }
}

async function loadStatsFieldOptions() {
  try {
    const { data: projectFields } = await api.getFields(props.id)
    for (const f of projectFields) {
      const { data: values } = await api.getFieldValues(props.id, f.field_name)
      statsFieldOptions.value[f.field_name] = values
      if (!(f.field_name in statsFieldFilters.value)) {
        statsFieldFilters.value[f.field_name] = ''
      }
    }
  } catch(e) {}
}

onMounted(() => {
  fetchStats()
  loadStatsFieldOptions()
})
watch(period, (newVal) => {
  if (newVal) {
    customFrom.value = ''
    customTo.value = ''
    fetchStats()
  }
})
</script>

<template>
  <div class="stats-page">
    <div class="stats-header">
      <div class="stats-header-left">
        <button class="btn-back" @click="router.push('/projects')">&#8592; {{ t('common.back') }}</button>
        <h1>{{ t('stats.title') }}<span v-if="project"> — {{ project.name }}</span></h1>
      </div>
      <div class="period-select">
        <label>{{ t('stats.period') }}:</label>
        <select v-model="period">
          <option value="7">{{ t('stats.last7') }}</option>
          <option value="30">{{ t('stats.last30') }}</option>
          <option value="90">{{ t('stats.last90') }}</option>
        </select>
        <div class="custom-range" style="display:flex;gap:8px;align-items:center;margin-left:16px">
          <input type="date" v-model="customFrom" class="form-input" />
          <span>—</span>
          <input type="date" v-model="customTo" class="form-input" />
          <button class="btn-sm" @click="fetchCustomRange">{{ t('stats.show') }}</button>
        </div>
      </div>
    </div>

    <div v-if="Object.keys(statsFieldOptions).length" class="field-filters" style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
      <select v-for="(options, fieldName) in statsFieldOptions" :key="fieldName"
        v-model="statsFieldFilters[fieldName]" @change="fetchStats()" class="field-filter">
        <option value="">{{ fieldName }}: All</option>
        <option v-for="val in options" :key="val" :value="val">{{ val }}</option>
      </select>
    </div>

    <div v-if="loading" class="loading">{{ t('common.loading') }}</div>

    <template v-else-if="stats">
      <!-- Summary Cards -->
      <div class="summary-cards">
        <div class="card card-total">
          <div class="card-value">{{ stats.total_requests }}</div>
          <div class="card-label">{{ t('stats.total') }}</div>
        </div>
        <div class="card card-success">
          <div class="card-value">{{ stats.successful }}</div>
          <div class="card-label">{{ t('stats.successful') }}</div>
        </div>
        <div class="card card-rejected">
          <div class="card-value">{{ stats.rejected }}</div>
          <div class="card-label">{{ t('stats.rejected') }}</div>
        </div>
      </div>

      <!-- Chart -->
      <div class="chart-section">
        <h2>{{ t('stats.chart') }}</h2>
        <div v-if="stats.daily_stats && stats.daily_stats.length > 0">
          <StatsChart :daily-stats="stats.daily_stats" />
        </div>
        <div v-else class="no-data">{{ t('stats.noData') }}</div>
      </div>

      <!-- Detailed Table -->
      <div v-if="stats.daily_stats && stats.daily_stats.length > 0" class="table-section">
        <table>
          <thead>
            <tr>
              <th>{{ t('stats.period') }}</th>
              <th>{{ t('stats.total') }}</th>
              <th>{{ t('stats.successful') }}</th>
              <th>{{ t('stats.rejected') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="day in stats.daily_stats" :key="day.date">
              <td>{{ day.date }}</td>
              <td>{{ day.total }}</td>
              <td class="text-green">{{ day.successful }}</td>
              <td class="text-red">{{ day.rejected }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<style scoped>
.stats-page { }
.stats-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 24px; flex-wrap: wrap; gap: 16px;
}
.stats-header-left { display: flex; align-items: center; gap: 16px; }
.stats-header h1 { margin: 0; font-size: 24px; color: #1a1a2e; }
.btn-back {
  background: #f3f4f6; border: none; padding: 8px 16px; border-radius: 6px;
  cursor: pointer; font-size: 14px; color: #555;
}
.btn-back:hover { background: #e5e7eb; }

.period-select { display: flex; align-items: center; gap: 8px; }
.period-select label { font-size: 14px; color: #666; }
.period-select select {
  padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;
}

.summary-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
.card {
  background: #fff; border-radius: 12px; padding: 24px; text-align: center;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.card-value { font-size: 36px; font-weight: 700; margin-bottom: 4px; }
.card-label { font-size: 14px; color: #888; }
.card-total .card-value { color: #4361ee; }
.card-success .card-value { color: #22c55e; }
.card-rejected .card-value { color: #ef4444; }

.chart-section {
  background: #fff; border-radius: 12px; padding: 24px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 24px;
}
.chart-section h2 { margin: 0 0 16px; font-size: 18px; color: #1a1a2e; }

.table-section {
  background: #fff; border-radius: 12px; overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
table { width: 100%; border-collapse: collapse; }
thead { background: #f8f9fa; }
th { text-align: left; padding: 12px 16px; font-size: 13px; color: #666; font-weight: 600; }
td { padding: 12px 16px; border-top: 1px solid #eee; font-size: 14px; }
.text-green { color: #22c55e; font-weight: 600; }
.text-red { color: #ef4444; font-weight: 600; }

.loading, .no-data { text-align: center; padding: 40px; color: #888; }

.form-input {
  padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;
}
.form-input:focus { border-color: #4361ee; outline: none; }
.btn-sm {
  border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 13px;
  background: #4361ee; color: #fff;
}
.btn-sm:hover { background: #3651d4; }
.field-filter {
  padding: 6px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;
}
.field-filter:focus { border-color: #4361ee; outline: none; }
</style>
