<template>
    <div>
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">{{ $t('nav.bookings') }}</h2>
            <button
                v-if="auth.canWrite"
                @click="showCreateModal = true"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
                New Booking
            </button>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-4 flex flex-wrap gap-3">
            <select v-model="filters.site_id" @change="fetchBookings"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Sites</option>
                <option v-for="site in sites" :key="site.id" :value="site.id">{{ site.name }}</option>
            </select>
            <input v-model="filters.from" type="date" @change="fetchBookings"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
            <input v-model="filters.to" type="date" @change="fetchBookings"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>

        <!-- Booking list -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div v-if="loading" class="p-8 text-center text-gray-500">{{ $t('common.loading') }}</div>
            <div v-else-if="bookings.length === 0" class="p-8 text-center text-gray-500">{{ $t('common.noData') }}</div>
            <table v-else class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Test System</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Start</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">End</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conflicts</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="booking in bookings" :key="booking.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ booking.title }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ booking.test_system?.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ formatDateTime(booking.starts_at) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ formatDateTime(booking.ends_at) }}</td>
                        <td class="px-4 py-3">
                            <span :class="statusClass(booking.status?.name)" class="px-2 py-1 rounded-full text-xs font-medium">
                                {{ booking.status?.name }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span v-if="booking.conflicts?.length > 0" class="px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                ⚠ {{ booking.conflicts.length }} conflict(s)
                            </span>
                            <span v-else class="text-gray-400 text-xs">None</span>
                        </td>
                        <td class="px-4 py-3 text-right flex gap-2 justify-end">
                            <button
                                v-if="auth.canWrite && booking.status?.name === 'requested'"
                                @click="confirmBooking(booking)"
                                class="text-green-600 hover:text-green-800 text-sm"
                            >
                                Confirm
                            </button>
                            <button
                                v-if="auth.canWrite && booking.status?.name !== 'cancelled'"
                                @click="cancelBooking(booking)"
                                class="text-red-600 hover:text-red-800 text-sm"
                            >
                                Cancel
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

        <!-- Create Booking Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-xl">
                <h3 class="text-lg font-bold text-gray-800 mb-4">New Booking</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                        <input v-model="newBooking.title" type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Test System</label>
                        <select v-model="newBooking.test_system_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select a test system</option>
                            <option v-for="system in testSystems" :key="system.id" :value="system.id">
                                {{ system.name }} ({{ system.site?.name }})
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start</label>
                        <input v-model="newBooking.starts_at" type="datetime-local"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End</label>
                        <input v-model="newBooking.ends_at" type="datetime-local"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea v-model="newBooking.notes" rows="2"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>

                    <!-- Conflict warnings -->
                    <div v-if="conflicts.length > 0" class="p-3 bg-orange-50 border border-orange-200 rounded-lg">
                        <p class="text-orange-700 font-medium text-sm mb-1">⚠ Conflicts detected:</p>
                        <ul class="text-orange-600 text-sm space-y-1">
                            <li v-for="c in conflicts" :key="c.id">• {{ c.description }}</li>
                        </ul>
                        <div class="mt-2">
                            <label class="block text-sm font-medium text-orange-700 mb-1">Override Reason (required to proceed)</label>
                            <input v-model="newBooking.override_reason" type="text"
                                class="w-full px-3 py-2 border border-orange-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500" />
                        </div>
                    </div>

                    <div v-if="createError" class="text-red-600 text-sm">{{ createError }}</div>
                </div>
                <div class="flex gap-3 justify-end mt-4">
                    <button @click="showCreateModal = false; conflicts = []; createError = null"
                        class="px-4 py-2 border rounded-lg hover:bg-gray-50">{{ $t('common.cancel') }}</button>
                    <button @click="createBooking"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Create</button>
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

const bookings    = ref([])
const sites       = ref([])
const testSystems = ref([])
const pagination  = ref(null)
const loading     = ref(false)
const conflicts   = ref([])

const showCreateModal = ref(false)
const createError     = ref(null)

const filters = ref({
    site_id: '',
    from:    '',
    to:      '',
    page:    1,
})

const newBooking = ref({
    title:          '',
    test_system_id: '',
    starts_at:      '',
    ends_at:        '',
    notes:          '',
    override_reason:'',
})

async function fetchBookings() {
    loading.value = true
    try {
        const params = Object.fromEntries(
            Object.entries(filters.value).filter(([, v]) => v !== '')
        )
        const response = await api.get('/bookings', { params })
        bookings.value   = response.data.data
        pagination.value = response.data
    } catch (e) {
        console.error('Failed to fetch bookings', e)
    } finally {
        loading.value = false
    }
}

async function fetchLookups() {
    const [sitesRes, systemsRes] = await Promise.all([
        api.get('/sites'),
        api.get('/test-systems', { params: { per_page: 300 } }),
    ])
    sites.value       = sitesRes.data
    testSystems.value = systemsRes.data.data
}

async function createBooking() {
    createError.value = null
    try {
        const response = await api.post('/bookings', newBooking.value)
        const booking = response.data

        // Check if conflicts were detected
        if (booking.conflicts && booking.conflicts.length > 0 && !newBooking.value.override_reason) {
            conflicts.value = booking.conflicts
            return
        }

        showCreateModal.value = false
        conflicts.value = []
        newBooking.value = { title: '', test_system_id: '', starts_at: '', ends_at: '', notes: '', override_reason: '' }
        await fetchBookings()
    } catch (e) {
        createError.value = e.response?.data?.message ?? 'Failed to create booking.'
    }
}

async function confirmBooking(booking) {
    try {
        await api.post(`/bookings/${booking.id}/confirm`, {
            override_reason: null,
        })
        await fetchBookings()
    } catch (e) {
        console.error('Failed to confirm booking', e)
    }
}

async function cancelBooking(booking) {
    if (!confirm(`Cancel booking "${booking.title}"?`)) return
    try {
        await api.post(`/bookings/${booking.id}/cancel`)
        await fetchBookings()
    } catch (e) {
        console.error('Failed to cancel booking', e)
    }
}

function changePage(page) {
    filters.value.page = page
    fetchBookings()
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleString()
}

function statusClass(name) {
    const map = {
        requested:  'bg-blue-100 text-blue-800',
        confirmed:  'bg-green-100 text-green-800',
        cancelled:  'bg-gray-100 text-gray-600',
    }
    return map[name] ?? 'bg-gray-100 text-gray-700'
}

onMounted(() => {
    fetchLookups()
    fetchBookings()
})
</script>
