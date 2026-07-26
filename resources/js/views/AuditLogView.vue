<template>
    <div>
        <h2 class="text-2xl font-bold text-gray-800 mb-6">{{ $t('nav.auditLog') }}</h2>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-4 flex flex-wrap gap-3">
            <input v-model="filters.action" type="text" placeholder="Filter by action"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                @input="debouncedFetch" />
            <input v-model="filters.entity_type" type="text" placeholder="Entity type (e.g. device)"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                @input="debouncedFetch" />
            <input v-model="filters.from" type="date" @change="fetchLog"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
            <input v-model="filters.to" type="date" @change="fetchLog"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div v-if="loading" class="p-8 text-center text-gray-500">{{ $t('common.loading') }}</div>
            <div v-else-if="entries.length === 0" class="p-8 text-center text-gray-500">{{ $t('common.noData') }}</div>
            <table v-else class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">When</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entity</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="entry in entries" :key="entry.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ formatDateTime(entry.created_at) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ entry.user?.display_name ?? 'System' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 bg-gray-100 text-gray-700 rounded text-xs font-mono">{{ entry.action }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ entry.entity_type }} #{{ entry.entity_id }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ entry.description }}</td>
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
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../api.js'

const entries    = ref([])
const pagination = ref(null)
const loading    = ref(false)

const filters = ref({
    action:      '',
    entity_type: '',
    from:        '',
    to:          '',
    page:        1,
})

let debounceTimer = null
function debouncedFetch() {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        filters.value.page = 1
        fetchLog()
    }, 400)
}

async function fetchLog() {
    loading.value = true
    try {
        const params = Object.fromEntries(
            Object.entries(filters.value).filter(([, v]) => v !== '')
        )
        const response = await api.get('/audit-log', { params })
        entries.value    = response.data.data
        pagination.value = response.data
    } catch (e) {
        console.error('Failed to fetch audit log', e)
    } finally {
        loading.value = false
    }
}

function changePage(page) {
    filters.value.page = page
    fetchLog()
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleString()
}

onMounted(fetchLog)
</script>
