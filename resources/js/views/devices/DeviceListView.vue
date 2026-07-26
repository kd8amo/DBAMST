<template>
    <div>
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">{{ $t('nav.devices') }}</h2>
            <div class="flex gap-2">
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
            <div v-if="loading" class="p-8 text-center text-gray-500">
                {{ $t('common.loading') }}
            </div>
            <div v-else-if="devices.length === 0" class="p-8 text-center text-gray-500">
                {{ $t('common.noData') }}
            </div>
            <table v-else class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
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
