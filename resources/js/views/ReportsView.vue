<template>
    <div>
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Reports</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-for="report in reports" :key="report.id"
                class="bg-white rounded-lg shadow-sm p-5 hover:shadow-md transition-shadow">
                <h3 class="font-semibold text-gray-800 mb-1">{{ report.title }}</h3>
                <p class="text-sm text-gray-500 mb-4">{{ report.description }}</p>

                <!-- Filters -->
                <div class="space-y-2 mb-4">
                    <div v-if="report.filters.includes('site_id')">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Site</label>
                        <select v-model="reportFilters[report.id].site_id"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                            <option value="">All Sites</option>
                            <option v-for="site in sites" :key="site.id" :value="site.id">{{ site.name }}</option>
                        </select>
                    </div>
                    <div v-if="report.filters.includes('from')" class="flex gap-2">
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-600 mb-1">From</label>
                            <input v-model="reportFilters[report.id].from" type="date"
                                class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500" />
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
                            <input v-model="reportFilters[report.id].to" type="date"
                                class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500" />
                        </div>
                    </div>
                    <div v-if="report.filters.includes('days')">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Warning Window (days)</label>
                        <input v-model="reportFilters[report.id].days" type="number" min="1" max="365"
                            class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500" />
                    </div>
                </div>

                <!-- Export buttons -->
                <div class="flex gap-2">
                    <button @click="exportReport(report.id, 'pdf')"
                        :disabled="exporting[report.id]"
                        class="flex-1 px-3 py-2 bg-red-600 text-white rounded text-sm hover:bg-red-700 disabled:opacity-50 transition-colors">
                        Export PDF
                    </button>
                    <button @click="exportReport(report.id, 'excel')"
                        :disabled="exporting[report.id]"
                        class="flex-1 px-3 py-2 bg-green-600 text-white rounded text-sm hover:bg-green-700 disabled:opacity-50 transition-colors">
                        Export Excel
                    </button>
                    <button @click="previewReport(report.id)"
                        :disabled="exporting[report.id]"
                        class="flex-1 px-3 py-2 border border-gray-300 text-gray-700 rounded text-sm hover:bg-gray-50 disabled:opacity-50 transition-colors">
                        Preview
                    </button>
                </div>
            </div>
        </div>

        <!-- Preview Modal -->
        <div v-if="showPreview" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-5xl max-h-screen overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h3 class="font-bold text-gray-800">{{ previewTitle }}</h3>
                    <div class="flex gap-2">
                        <button @click="exportReport(previewReportId, 'pdf')"
                            class="px-3 py-1.5 bg-red-600 text-white rounded text-sm hover:bg-red-700">Export PDF</button>
                        <button @click="exportReport(previewReportId, 'excel')"
                            class="px-3 py-1.5 bg-green-600 text-white rounded text-sm hover:bg-green-700">Export Excel</button>
                        <button @click="showPreview = false"
                            class="px-3 py-1.5 border rounded text-sm hover:bg-gray-50">Close</button>
                    </div>
                </div>
                <div class="overflow-auto flex-1 p-4">
                    <p class="text-sm text-gray-500 mb-3">{{ previewData.rows?.length ?? 0 }} records</p>
                    <div v-if="previewData.rows?.length === 0" class="text-center text-gray-500 py-8">
                        No records found for the selected filters.
                    </div>
                    <table v-else class="w-full text-sm border-collapse">
                        <thead>
                            <tr>
                                <th v-for="header in previewData.headers" :key="header"
                                    class="bg-gray-50 px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase border border-gray-200">
                                    {{ header }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in previewData.rows" :key="i"
                                :class="i % 2 === 0 ? 'bg-white' : 'bg-gray-50'">
                                <td v-for="(cell, j) in row" :key="j"
                                    class="px-3 py-2 border border-gray-200 text-gray-700">
                                    {{ cell }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '../api.js'

const reports         = ref([])
const sites           = ref([])
const showPreview     = ref(false)
const previewData     = ref({ headers: [], rows: [] })
const previewTitle    = ref('')
const previewReportId = ref('')
const reportFilters   = reactive({})
const exporting       = reactive({})

async function fetchReports() {
    const response = await api.get('/reports')
    reports.value  = response.data
    response.data.forEach(r => {
        reportFilters[r.id] = { site_id: '', from: '', to: '', days: 30 }
        exporting[r.id]     = false
    })
}

async function fetchSites() {
    const response = await api.get('/sites')
    sites.value = response.data
}

async function exportReport(reportId, format) {
    exporting[reportId] = true
    try {
        const params   = { format, ...cleanFilters(reportFilters[reportId]) }
        const response = await api.get(`/reports/${reportId}`, {
            params,
            responseType: 'blob',
        })
        const mimeType = format === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        const ext  = format === 'pdf' ? 'pdf' : 'xlsx'
        const url  = URL.createObjectURL(new Blob([response.data], { type: mimeType }))
        const link = document.createElement('a')
        link.href     = url
        link.download = `${reportId}_${new Date().toISOString().slice(0,10)}.${ext}`
        link.click()
        URL.revokeObjectURL(url)
    } catch (e) {
        console.error('Export failed', e)
        alert('Export failed. Please try again.')
    } finally {
        exporting[reportId] = false
    }
}

async function previewReport(reportId) {
    try {
        const params   = { format: 'json', ...cleanFilters(reportFilters[reportId]) }
        const response = await api.get(`/reports/${reportId}`, { params })
        previewData.value     = response.data
        previewTitle.value    = reports.value.find(r => r.id === reportId)?.title ?? reportId
        previewReportId.value = reportId
        showPreview.value     = true
    } catch (e) {
        console.error('Preview failed', e)
        alert('Failed to load preview.')
    }
}

function cleanFilters(filters) {
    return Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== '' && v !== null)
    )
}

onMounted(() => {
    fetchReports()
    fetchSites()
})
</script>
