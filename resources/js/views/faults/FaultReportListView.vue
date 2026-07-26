<template>
    <div>
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">{{ $t('nav.faultReports') }}</h2>
            <button
                v-if="auth.canWrite"
                @click="showCreateModal = true"
                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
            >
                Report Fault
            </button>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-4 flex flex-wrap gap-3">
            <select
                v-model="filters.status_id"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                @change="fetchReports"
            >
                <option value="">All Statuses</option>
                <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
            </select>
            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" v-model="filters.open_only" @change="fetchReports" class="rounded" />
                Open only
            </label>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div v-if="loading" class="p-8 text-center text-gray-500">{{ $t('common.loading') }}</div>
            <div v-else-if="reports.length === 0" class="p-8 text-center text-gray-500">{{ $t('common.noData') }}</div>
            <table v-else class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Device / System</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reported</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reporter</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="report in reports" :key="report.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-800 max-w-xs truncate">{{ report.description }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <span v-if="report.device" class="font-mono text-blue-600">{{ report.device?.asset_tag }}</span>
                            <span v-if="report.device && report.test_system"> / </span>
                            <span v-if="report.test_system">{{ report.test_system?.name }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(report.reported_at) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ report.reported_by?.display_name }}</td>
                        <td class="px-4 py-3">
                            <span :class="statusClass(report.status?.name)" class="px-2 py-1 rounded-full text-xs font-medium">
                                {{ report.status?.name }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                v-if="auth.canWrite && report.status?.name !== 'resolved'"
                                @click="openTriageModal(report)"
                                class="text-blue-600 hover:text-blue-800 text-sm"
                            >
                                Triage
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="pagination" class="px-4 py-3 border-t flex items-center justify-between text-sm text-gray-600">
                <span>Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}</span>
                <div class="flex gap-2">
                    <button :disabled="!pagination.prev_page_url" @click="changePage(pagination.current_page - 1)"
                        class="px-3 py-1 border rounded disabled:opacity-50 hover:bg-gray-50">Previous</button>
                    <button :disabled="!pagination.next_page_url" @click="changePage(pagination.current_page + 1)"
                        class="px-3 py-1 border rounded disabled:opacity-50 hover:bg-gray-50">Next</button>
                </div>
            </div>
        </div>

        <!-- Report Fault Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-xl">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Report a Fault</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Device Asset Tag (optional)</label>
                        <input v-model="newReport.asset_tag" type="text" placeholder="e.g. MEA-000001"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @blur="lookupDevice" />
                        <p v-if="lookedUpDevice" class="text-sm text-green-600 mt-1">
                            ✓ {{ lookedUpDevice.manufacturer }} {{ lookedUpDevice.model }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea v-model="newReport.description" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Describe the fault..." />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Severity</label>
                        <select v-model="newReport.severity"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Not specified</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div v-if="createError" class="text-red-600 text-sm">{{ createError }}</div>
                </div>
                <div class="flex gap-3 justify-end mt-4">
                    <button @click="showCreateModal = false; createError = null"
                        class="px-4 py-2 border rounded-lg hover:bg-gray-50">{{ $t('common.cancel') }}</button>
                    <button @click="createReport"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Submit</button>
                </div>
            </div>
        </div>

        <!-- Triage Modal -->
        <div v-if="showTriageModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-xl">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Triage Fault Report</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Update Status</label>
                        <select v-model="triageForm.status_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Resolution Notes</label>
                        <textarea v-model="triageForm.resolution_notes" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div v-if="triageError" class="text-red-600 text-sm">{{ triageError }}</div>
                </div>
                <div class="flex gap-3 justify-end mt-4">
                    <button @click="showTriageModal = false"
                        class="px-4 py-2 border rounded-lg hover:bg-gray-50">{{ $t('common.cancel') }}</button>
                    <button @click="submitTriage"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">{{ $t('common.save') }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth.js'
import api from '../../api.js'

const auth = useAuthStore()

const reports    = ref([])
const statuses   = ref([])
const pagination = ref(null)
const loading    = ref(false)

const showCreateModal = ref(false)
const showTriageModal = ref(false)
const createError     = ref(null)
const triageError     = ref(null)
const lookedUpDevice  = ref(null)
const selectedReport  = ref(null)

const filters = ref({
    status_id: '',
    open_only: false,
    page:      1,
})

const newReport = ref({
    asset_tag:   '',
    description: '',
    severity:    '',
})

const triageForm = ref({
    status_id:        null,
    resolution_notes: '',
})

async function fetchReports() {
    loading.value = true
    try {
        const params = Object.fromEntries(
            Object.entries(filters.value).filter(([, v]) => v !== '' && v !== false)
        )
        const response = await api.get('/fault-reports', { params })
        reports.value    = response.data.data
        pagination.value = response.data
    } catch (e) {
        console.error('Failed to fetch fault reports', e)
    } finally {
        loading.value = false
    }
}

async function fetchLookups() {
    const response = await api.get('/fault-report-statuses')
    statuses.value = response.data
}

async function lookupDevice() {
    if (!newReport.value.asset_tag) return
    try {
        const response = await api.get('/devices/find-by-asset-tag', {
            params: { asset_tag: newReport.value.asset_tag }
        })
        lookedUpDevice.value = response.data
    } catch (e) {
        lookedUpDevice.value = null
    }
}

async function createReport() {
    createError.value = null
    if (!newReport.value.description) {
        createError.value = 'Description is required.'
        return
    }
    if (!lookedUpDevice.value && !newReport.value.asset_tag) {
        createError.value = 'Please provide a device asset tag or test system.'
        return
    }
    try {
        await api.post('/fault-reports', {
            device_id:   lookedUpDevice.value?.id ?? null,
            description: newReport.value.description,
            severity:    newReport.value.severity || null,
        })
        showCreateModal.value = false
        newReport.value = { asset_tag: '', description: '', severity: '' }
        lookedUpDevice.value = null
        await fetchReports()
    } catch (e) {
        createError.value = e.response?.data?.error ?? 'Failed to submit fault report.'
    }
}

function openTriageModal(report) {
    selectedReport.value = report
    triageForm.value = {
        status_id:        report.status?.id,
        resolution_notes: report.resolution_notes ?? '',
    }
    showTriageModal.value = true
}

async function submitTriage() {
    triageError.value = null
    try {
        await api.patch(`/fault-reports/${selectedReport.value.id}`, triageForm.value)
        showTriageModal.value = false
        await fetchReports()
    } catch (e) {
        triageError.value = e.response?.data?.error ?? 'Failed to update fault report.'
    }
}

function changePage(page) {
    filters.value.page = page
    fetchReports()
}

function formatDate(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString()
}

function statusClass(name) {
    const map = {
        open:        'bg-red-100 text-red-800',
        in_progress: 'bg-yellow-100 text-yellow-800',
        resolved:    'bg-green-100 text-green-800',
    }
    return map[name] ?? 'bg-gray-100 text-gray-700'
}

onMounted(() => {
    fetchLookups()
    fetchReports()
})
</script>
