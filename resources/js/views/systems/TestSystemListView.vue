<template>
    <div>
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">{{ $t('nav.testSystems') }}</h2>
            <button
                v-if="auth.canWrite"
                @click="showCreateModal = true"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
                {{ $t('common.create') }}
            </button>
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
                @change="fetchSystems"
            >
                <option value="">All Sites</option>
                <option v-for="site in sites" :key="site.id" :value="site.id">{{ site.name }}</option>
            </select>
            <select
                v-model="filters.status_id"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                @change="fetchSystems"
            >
                <option value="">All Statuses</option>
                <option v-for="status in statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
            </select>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div v-if="loading" class="p-8 text-center text-gray-500">{{ $t('common.loading') }}</div>
            <div v-else-if="systems.length === 0" class="p-8 text-center text-gray-500">{{ $t('common.noData') }}</div>
            <table v-else class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Site</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Devices</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr
                        v-for="system in systems"
                        :key="system.id"
                        class="hover:bg-gray-50 transition-colors"
                    >
                        <td class="px-4 py-3 font-medium text-gray-800">{{ system.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ system.site?.name }}</td>
                        <td class="px-4 py-3">
                            <span :class="statusClass(system.status?.name)" class="px-2 py-1 rounded-full text-xs font-medium">
                                {{ system.status?.name }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ system.current_assignments_count ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <RouterLink
                                :to="{ name: 'test-system-detail', params: { id: system.id }}"
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
                <span>Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}</span>
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

        <!-- Create Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-xl">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Create Test System</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input
                            v-model="newSystem.name"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Site</label>
                        <select
                            v-model="newSystem.site_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">Select a site</option>
                            <option v-for="site in sites" :key="site.id" :value="site.id">{{ site.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea
                            v-model="newSystem.notes"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div v-if="createError" class="text-red-600 text-sm">{{ createError }}</div>
                </div>
                <div class="flex gap-3 justify-end mt-4">
                    <button @click="showCreateModal = false; createError = null" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
                        {{ $t('common.cancel') }}
                    </button>
                    <button @click="createSystem" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        {{ $t('common.create') }}
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

const systems    = ref([])
const sites      = ref([])
const statuses   = ref([])
const pagination = ref(null)
const loading    = ref(false)
const showCreateModal = ref(false)
const createError     = ref(null)

const filters = ref({
    search:   '',
    site_id:  '',
    status_id:'',
    page:     1,
})

const newSystem = ref({
    name:    '',
    site_id: '',
    notes:   '',
})

let debounceTimer = null
function debouncedFetch() {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        filters.value.page = 1
        fetchSystems()
    }, 400)
}

async function fetchSystems() {
    loading.value = true
    try {
        const params = Object.fromEntries(
            Object.entries(filters.value).filter(([, v]) => v !== '')
        )
        const response = await api.get('/test-systems', { params })
        systems.value    = response.data.data
        pagination.value = response.data
    } catch (e) {
        console.error('Failed to fetch test systems', e)
    } finally {
        loading.value = false
    }
}

async function fetchLookups() {
    const [sitesRes, statusesRes] = await Promise.all([
        api.get('/sites'),
        api.get('/test-system-statuses'),
    ])
    sites.value   = sitesRes.data
    statuses.value = statusesRes.data
}

async function createSystem() {
    createError.value = null
    try {
        await api.post('/test-systems', newSystem.value)
        showCreateModal.value = false
        newSystem.value = { name: '', site_id: '', notes: '' }
        await fetchSystems()
    } catch (e) {
        createError.value = e.response?.data?.message ?? 'Failed to create test system.'
    }
}

function changePage(page) {
    filters.value.page = page
    fetchSystems()
}

function statusClass(name) {
    const map = {
        active:         'bg-green-100 text-green-800',
        in_maintenance: 'bg-yellow-100 text-yellow-800',
        retired:        'bg-red-100 text-red-700',
    }
    return map[name] ?? 'bg-gray-100 text-gray-700'
}

onMounted(() => {
    fetchLookups()
    fetchSystems()
})
</script>
