<template>
    <div>
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">{{ $t('nav.maintenance') }}</h2>
            <button
                v-if="auth.canWrite"
                @click="showLogModal = true"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
                Log Event
            </button>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-red-700 font-bold text-2xl">{{ overdueItems.length }}</p>
                <p class="text-red-600 text-sm">Overdue</p>
            </div>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-yellow-700 font-bold text-2xl">{{ dueSoonItems.length }}</p>
                <p class="text-yellow-600 text-sm">Due Soon</p>
            </div>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <p class="text-green-700 font-bold text-2xl">{{ schedules.length }}</p>
                <p class="text-green-600 text-sm">Total Scheduled</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-4 flex gap-3">
            <select v-model="filters.site_id" @change="fetchDue"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Sites</option>
                <option v-for="site in sites" :key="site.id" :value="site.id">{{ site.name }}</option>
            </select>
        </div>

        <!-- Due/Overdue table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div v-if="loading" class="p-8 text-center text-gray-500">{{ $t('common.loading') }}</div>
            <div v-else-if="schedules.length === 0" class="p-8 text-center text-gray-500">
                No items due for maintenance.
            </div>
            <table v-else class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset Tag</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Device</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Site</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="item in schedules" :key="item.id"
                        :class="item.is_overdue ? 'bg-red-50' : ''"
                        class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-sm text-blue-600">{{ item.device?.asset_tag }}</td>
                        <td class="px-4 py-3 text-sm text-gray-800">
                            {{ item.device?.manufacturer }} {{ item.device?.model }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ item.device?.site?.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ item.event_type?.name }}</td>
                        <td class="px-4 py-3 text-sm" :class="item.is_overdue ? 'text-red-600 font-medium' : 'text-gray-700'">
                            {{ formatDate(item.next_due_at) }}
                        </td>
                        <td class="px-4 py-3">
                            <span :class="item.is_overdue ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'"
                                class="px-2 py-1 rounded-full text-xs font-medium">
                                {{ item.is_overdue ? 'Overdue' : 'Due Soon' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                v-if="auth.canWrite"
                                @click="openLogModal(item)"
                                class="text-blue-600 hover:text-blue-800 text-sm"
                            >
                                Log
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Log Maintenance Event Modal -->
        <div v-if="showLogModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-xl">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Log Maintenance Event</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Device Asset Tag</label>
                        <input v-model="logForm.asset_tag" type="text" placeholder="e.g. MEA-000001"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @blur="lookupDevice" />
                        <p v-if="lookedUpDevice" class="text-sm text-green-600 mt-1">
                            ✓ {{ lookedUpDevice.manufacturer }} {{ lookedUpDevice.model }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Event Type</label>
                        <select v-model="logForm.event_type_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select type</option>
                            <option v-for="type in eventTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date Performed</label>
                        <input v-model="logForm.performed_at" type="date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Performed By / Vendor</label>
                        <input v-model="logForm.performed_by" type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Result</label>
                        <select v-model="logForm.result"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="pass">Pass</option>
                            <option value="fail">Fail</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Next Due Date (optional)</label>
                        <input v-model="logForm.next_due_at" type="date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div v-if="logError" class="text-red-600 text-sm">{{ logError }}</div>
                </div>
                <div class="flex gap-3 justify-end mt-4">
                    <button @click="showLogModal = false; logError = null"
                        class="px-4 py-2 border rounded-lg hover:bg-gray-50">{{ $t('common.cancel') }}</button>
                    <button @click="submitLog"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">{{ $t('common.save') }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth.js'
import api from '../../api.js'

const auth = useAuthStore()

const schedules      = ref([])
const sites          = ref([])
const eventTypes     = ref([])
const loading        = ref(false)
const showLogModal   = ref(false)
const logError       = ref(null)
const lookedUpDevice = ref(null)

const filters = ref({ site_id: '' })

const logForm = ref({
    asset_tag:    '',
    event_type_id:'',
    performed_at: '',
    performed_by: '',
    result:       'pass',
    next_due_at:  '',
})

const overdueItems  = computed(() => schedules.value.filter(s => s.is_overdue))
const dueSoonItems  = computed(() => schedules.value.filter(s => !s.is_overdue))

async function fetchDue() {
    loading.value = true
    try {
        const params = Object.fromEntries(
            Object.entries(filters.value).filter(([, v]) => v !== '')
        )
        const response = await api.get('/maintenance/due', { params })
        schedules.value = response.data
    } catch (e) {
        console.error('Failed to fetch maintenance due list', e)
    } finally {
        loading.value = false
    }
}

async function fetchLookups() {
    const [sitesRes, typesRes] = await Promise.all([
        api.get('/sites'),
        api.get('/maintenance-event-types'),
    ])
    sites.value      = sitesRes.data
    eventTypes.value = typesRes.data
}

async function lookupDevice() {
    if (!logForm.value.asset_tag) return
    try {
        const response = await api.get('/devices/find-by-asset-tag', {
            params: { asset_tag: logForm.value.asset_tag }
        })
        lookedUpDevice.value = response.data
    } catch (e) {
        lookedUpDevice.value = null
    }
}

function openLogModal(item) {
    logForm.value.asset_tag = item.device?.asset_tag ?? ''
    lookedUpDevice.value    = item.device ?? null
    logForm.value.event_type_id = item.event_type_id ?? ''
    showLogModal.value = true
}

async function submitLog() {
    logError.value = null
    if (!lookedUpDevice.value) {
        logError.value = 'Please enter a valid asset tag.'
        return
    }
    try {
        await api.post('/maintenance/events', {
            device_id:     lookedUpDevice.value.id,
            event_type_id: logForm.value.event_type_id,
            performed_at:  logForm.value.performed_at,
            performed_by:  logForm.value.performed_by,
            result:        logForm.value.result,
            next_due_at:   logForm.value.next_due_at || null,
        })
        showLogModal.value = false
        logForm.value = { asset_tag: '', event_type_id: '', performed_at: '', performed_by: '', result: 'pass', next_due_at: '' }
        lookedUpDevice.value = null
        await fetchDue()
    } catch (e) {
        logError.value = e.response?.data?.error ?? 'Failed to log maintenance event.'
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString()
}

onMounted(() => {
    fetchLookups()
    fetchDue()
})
</script>
