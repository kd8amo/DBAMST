<template>
    <div>
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">{{ $t('nav.devices') }}</h2>
            <div class="flex gap-2">
                <button
                    v-if="selectedIds.length > 0"
                    @click="printLabels('avery')"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                >
                    Print Labels (Avery)
                </button>
                <button
                    v-if="selectedIds.length > 0"
                    @click="printLabels('thermal')"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                >
                    Print Labels (Thermal)
                </button>
                <button
                    v-if="auth.canWrite"
                    @click="showImportModal = true"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                >
                    {{ $t('common.import') }}
                </button>
                <button
                    v-if="auth.canWrite"
                    @click="showCreateModal = true"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    {{ $t('common.create') }}
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-4 flex flex-wrap gap-3">
            <input
                v-model="filters.search"
                type="text"
                :placeholder="$t('common.search')"
                class="px-3 py-2 border border-gray-300 rounded-lg flex-1 min-w-48 focus:outline-none focus:ring-2 focus:ring-blue-500"
                @input="debouncedFetch"
            />
            <select
                v-model="filters.site_id"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                @change="fetchDevices"
            >
                <option value="">All Sites</option>
                <option v-for="site in sites" :key="site.id" :value="site.id">{{ site.name }}</option>
            </select>
            <select
                v-model="filters.category_id"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                @change="fetchDevices"
            >
                <option value="">All Categories</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
            <select
                v-model="filters.status_id"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                @change="fetchDevices"
            >
                <option value="">All Statuses</option>
                <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
            </select>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div v-if="loading" class="p-8 text-center text-gray-500">{{ $t('common.loading') }}</div>
            <div v-else-if="devices.length === 0" class="p-8 text-center text-gray-500">{{ $t('common.noData') }}</div>
            <table v-else class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3">
                            <input type="checkbox" v-model="allSelected" @change="toggleAll" class="rounded" />
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset Tag</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Manufacturer / Model</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Site</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">System</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr
                        v-for="device in devices"
                        :key="device.id"
                        class="hover:bg-gray-50 transition-colors"
                    >
                        <td class="px-4 py-3">
                            <input type="checkbox" :value="device.id" v-model="selectedIds" class="rounded" />
                        </td>
                        <td class="px-4 py-3 font-mono text-sm font-medium text-blue-600">
                            {{ device.asset_tag }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            {{ device.manufacturer }} {{ device.model }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ device.category?.name }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ device.site?.name }}
                        </td>
                        <td class="px-4 py-3">
                            <span :class="statusClass(device.status?.name)" class="px-2 py-1 rounded-full text-xs font-medium">
                                {{ device.status?.name }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ device.current_assignment?.test_system?.name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <RouterLink
                                :to="{ name: 'device-detail', params: { id: device.id }}"
                                class="text-blue-600 hover:text-blue-800 text-sm"
                            >
                                View
                            </RouterLink>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="pagination" class="px-4 py-3 border-t flex items-center justify-between text-sm text-gray-600">
                <span>
                    Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}
                    <span v-if="selectedIds.length > 0" class="ml-2 text-blue-600">({{ selectedIds.length }} selected)</span>
                </span>
                <div class="flex gap-2">
                    <button
                        :disabled="!pagination.prev_page_url"
                        @click="changePage(pagination.current_page - 1)"
                        class="px-3 py-1 border rounded disabled:opacity-50 hover:bg-gray-50"
                    >
                        Previous
                    </button>
                    <button
                        :disabled="!pagination.next_page_url"
                        @click="changePage(pagination.current_page + 1)"
                        class="px-3 py-1 border rounded disabled:opacity-50 hover:bg-gray-50"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <!-- Import Modal -->
        <div v-if="showImportModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl p-6 w-full max-w-lg shadow-xl">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Bulk Import Devices</h3>
                <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700">
                    <p class="font-medium mb-1">Instructions:</p>
                    <ol class="list-decimal list-inside space-y-1">
                        <li>Download the CSV template below</li>
                        <li>Fill in your devices (one per row)</li>
                        <li>Upload the completed file</li>
                    </ol>
                    <a href="/api/devices/import-template"
                        class="inline-block mt-2 text-blue-600 hover:text-blue-800 font-medium">
                        ↓ Download CSV Template
                    </a>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select CSV File</label>
                    <input type="file" accept=".csv" @change="handleFileSelect"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none" />
                </div>
                <div v-if="importResults" class="mb-4">
                    <div class="flex gap-4 mb-3 text-sm font-medium">
                        <span class="text-green-600">✓ {{ importResults.succeeded }} succeeded</span>
                        <span class="text-red-600">✗ {{ importResults.failed }} failed</span>
                        <span class="text-gray-600">{{ importResults.total }} total</span>
                    </div>
                    <div v-if="importResults.failed > 0" class="max-h-48 overflow-y-auto">
                        <div v-for="row in importResults.rows.filter(r => r.status === 'failed')" :key="row.row"
                            class="mb-2 p-2 bg-red-50 border border-red-200 rounded text-xs">
                            <p class="font-medium text-red-700">Row {{ row.row }}: {{ row.data.manufacturer }} {{ row.data.model }}</p>
                            <p v-for="err in row.errors" :key="err" class="text-red-600">{{ err }}</p>
                        </div>
                    </div>
                </div>
                <div v-if="importError" class="mb-3 text-red-600 text-sm">{{ importError }}</div>
                <div class="flex gap-3 justify-end">
                    <button @click="showImportModal = false; importResults = null; importError = null"
                        class="px-4 py-2 border rounded-lg hover:bg-gray-50">Close</button>
                    <button v-if="importFile" @click="submitImport" :disabled="importing"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-blue-400">
                        {{ importing ? 'Importing...' : 'Import' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '../../stores/auth.js'
import api from '../../api.js'

const auth = useAuthStore()

const devices    = ref([])
const sites      = ref([])
const categories = ref([])
const statuses   = ref([])
const pagination = ref(null)
const loading    = ref(false)

const showCreateModal = ref(false)
const showImportModal = ref(false)
const importFile      = ref(null)
const importResults   = ref(null)
const importError     = ref(null)
const importing       = ref(false)

const selectedIds = ref([])
const allSelected = ref(false)

const filters = ref({
    search:      '',
    site_id:     '',
    category_id: '',
    status_id:   '',
    page:        1,
})

let debounceTimer = null
function debouncedFetch() {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        filters.value.page = 1
        fetchDevices()
    }, 400)
}

async function fetchDevices() {
    loading.value = true
    try {
        const params = Object.fromEntries(
            Object.entries(filters.value).filter(([, v]) => v !== '')
        )
        const response = await api.get('/devices', { params })
        devices.value    = response.data.data
        pagination.value = response.data
        selectedIds.value = []
        allSelected.value = false
    } catch (e) {
        console.error('Failed to fetch devices', e)
    } finally {
        loading.value = false
    }
}

async function fetchLookups() {
    const [sitesRes, catsRes, statusesRes] = await Promise.all([
        api.get('/sites'),
        api.get('/device-categories'),
        api.get('/device-statuses'),
    ])
    sites.value      = sitesRes.data
    categories.value = catsRes.data
    statuses.value   = statusesRes.data
}

function changePage(page) {
    filters.value.page = page
    fetchDevices()
}

function toggleAll() {
    if (allSelected.value) {
        selectedIds.value = devices.value.map(d => d.id)
    } else {
        selectedIds.value = []
    }
}

async function printLabels(template = 'avery') {
    if (selectedIds.value.length === 0) {
        alert('Please select at least one device.')
        return
    }
    try {
        const ids      = selectedIds.value.join(',')
        const response = await api.get('/labels', {
            params:       { ids, template },
            responseType: 'blob',
        })
        const url  = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
        const link = document.createElement('a')
        link.href   = url
        link.target = '_blank'
        link.click()
        URL.revokeObjectURL(url)
    } catch (e) {
        console.error('Failed to generate labels', e)
        alert('Failed to generate labels. Please try again.')
    }
}

function handleFileSelect(event) {
    importFile.value    = event.target.files[0]
    importResults.value = null
    importError.value   = null
}

async function submitImport() {
    if (!importFile.value) return
    importing.value   = true
    importError.value = null
    try {
        const formData = new FormData()
        formData.append('file', importFile.value)
        const response = await api.post('/devices/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
        importResults.value = response.data
        await fetchDevices()
    } catch (e) {
        importError.value = e.response?.data?.message ?? 'Import failed.'
    } finally {
        importing.value = false
    }
}

function statusClass(name) {
    const map = {
        assigned:           'bg-green-100 text-green-800',
        unassigned:         'bg-gray-100 text-gray-700',
        out_for_calibration:'bg-yellow-100 text-yellow-800',
        retired:            'bg-red-100 text-red-700',
    }
    return map[name] ?? 'bg-gray-100 text-gray-700'
}

onMounted(() => {
    fetchLookups()
    fetchDevices()
})
</script>
