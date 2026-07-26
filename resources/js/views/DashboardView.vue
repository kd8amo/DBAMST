<template>
    <div>
        <h2 class="text-2xl font-bold text-gray-800 mb-6">{{ $t('nav.dashboard') }}</h2>

        <div v-if="loading" class="p-8 text-center text-gray-500">{{ $t('common.loading') }}</div>
        <div v-else>
            <!-- Summary cards -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 cursor-pointer hover:shadow-md transition-shadow"
                    @click="$router.push('/maintenance')">
                    <p class="text-red-700 font-bold text-3xl">{{ data.counts.overdue_maintenance }}</p>
                    <p class="text-red-600 text-sm mt-1">Overdue Maintenance</p>
                </div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 cursor-pointer hover:shadow-md transition-shadow"
                    @click="$router.push('/maintenance')">
                    <p class="text-yellow-700 font-bold text-3xl">{{ data.counts.due_soon }}</p>
                    <p class="text-yellow-600 text-sm mt-1">Due Soon</p>
                </div>
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 cursor-pointer hover:shadow-md transition-shadow"
                    @click="$router.push('/fault-reports')">
                    <p class="text-orange-700 font-bold text-3xl">{{ data.counts.open_faults }}</p>
                    <p class="text-orange-600 text-sm mt-1">Open Faults</p>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 cursor-pointer hover:shadow-md transition-shadow"
                    @click="$router.push('/test-systems')">
                    <p class="text-blue-700 font-bold text-3xl">{{ data.counts.total_systems }}</p>
                    <p class="text-blue-600 text-sm mt-1">Test Systems</p>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 cursor-pointer hover:shadow-md transition-shadow"
                    @click="$router.push('/devices')">
                    <p class="text-green-700 font-bold text-3xl">{{ data.counts.assigned_devices }}</p>
                    <p class="text-green-600 text-sm mt-1">Assigned Devices</p>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 cursor-pointer hover:shadow-md transition-shadow"
                    @click="$router.push('/devices')">
                    <p class="text-gray-700 font-bold text-3xl">{{ data.counts.total_devices }}</p>
                    <p class="text-gray-600 text-sm mt-1">Total Devices</p>
                </div>
            </div>

            <!-- Main content grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

                <!-- Overdue maintenance -->
                <div class="bg-white rounded-lg shadow-sm">
                    <div class="px-4 py-3 border-b flex items-center justify-between">
                        <h3 class="font-semibold text-gray-700">Overdue Maintenance</h3>
                        <RouterLink to="/maintenance" class="text-blue-600 hover:text-blue-800 text-sm">View all</RouterLink>
                    </div>
                    <div v-if="data.overdue_items.length === 0" class="p-4 text-sm text-gray-500">
                        ✓ No overdue items.
                    </div>
                    <ul v-else class="divide-y divide-gray-100">
                        <li v-for="item in data.overdue_items" :key="item.id" class="px-4 py-3 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-800 font-mono">{{ item.device?.asset_tag }}</p>
                                <p class="text-xs text-gray-500">{{ item.device?.manufacturer }} {{ item.device?.model }} — {{ item.event_type?.name }}</p>
                            </div>
                            <span class="text-xs text-red-600 font-medium">{{ formatDate(item.next_due_at) }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Open faults -->
                <div class="bg-white rounded-lg shadow-sm">
                    <div class="px-4 py-3 border-b flex items-center justify-between">
                        <h3 class="font-semibold text-gray-700">Open Fault Reports</h3>
                        <RouterLink to="/fault-reports" class="text-blue-600 hover:text-blue-800 text-sm">View all</RouterLink>
                    </div>
                    <div v-if="data.open_faults.length === 0" class="p-4 text-sm text-gray-500">
                        ✓ No open faults.
                    </div>
                    <ul v-else class="divide-y divide-gray-100">
                        <li v-for="fault in data.open_faults" :key="fault.id" class="px-4 py-3">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 mr-2">
                                    <p class="text-sm text-gray-800 truncate">{{ fault.description }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        <span v-if="fault.device" class="font-mono">{{ fault.device?.asset_tag }}</span>
                                        <span v-if="fault.test_system"> — {{ fault.test_system?.name }}</span>
                                    </p>
                                </div>
                                <span :class="fault.status?.name === 'open' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'"
                                    class="px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap">
                                    {{ fault.status?.name }}
                                </span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <!-- Upcoming bookings -->
                <div class="bg-white rounded-lg shadow-sm">
                    <div class="px-4 py-3 border-b flex items-center justify-between">
                        <h3 class="font-semibold text-gray-700">Upcoming Bookings (Next 7 Days)</h3>
                        <RouterLink to="/bookings" class="text-blue-600 hover:text-blue-800 text-sm">View all</RouterLink>
                    </div>
                    <div v-if="data.upcoming_bookings.length === 0" class="p-4 text-sm text-gray-500">
                        No bookings in the next 7 days.
                    </div>
                    <ul v-else class="divide-y divide-gray-100">
                        <li v-for="booking in data.upcoming_bookings" :key="booking.id" class="px-4 py-3">
                            <p class="text-sm font-medium text-gray-800">{{ booking.title }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ booking.test_system?.name }} — {{ formatDateTime(booking.starts_at) }}
                            </p>
                        </li>
                    </ul>
                </div>

                <!-- Recent activity -->
                <div class="bg-white rounded-lg shadow-sm">
                    <div class="px-4 py-3 border-b flex items-center justify-between">
                        <h3 class="font-semibold text-gray-700">Recent Activity</h3>
                        <RouterLink to="/audit-log" class="text-blue-600 hover:text-blue-800 text-sm">View all</RouterLink>
                    </div>
                    <div v-if="data.recent_activity.length === 0" class="p-4 text-sm text-gray-500">
                        No recent activity.
                    </div>
                    <ul v-else class="divide-y divide-gray-100">
                        <li v-for="entry in data.recent_activity" :key="entry.id" class="px-4 py-3">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 mr-2">
                                    <span class="text-xs font-mono bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded">{{ entry.action }}</span>
                                    <p class="text-xs text-gray-500 mt-1">{{ entry.description }}</p>
                                </div>
                                <span class="text-xs text-gray-400 whitespace-nowrap">{{ formatDateTime(entry.created_at) }}</span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../api.js'

const loading = ref(false)
const data    = ref({
    counts: {
        overdue_maintenance: 0,
        due_soon:            0,
        open_faults:         0,
        total_devices:       0,
        assigned_devices:    0,
        total_systems:       0,
    },
    upcoming_bookings: [],
    recent_activity:   [],
    overdue_items:     [],
    open_faults:       [],
})

async function fetchDashboard() {
    loading.value = true
    try {
        const response = await api.get('/dashboard')
        data.value = response.data
    } catch (e) {
        console.error('Failed to fetch dashboard', e)
    } finally {
        loading.value = false
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString()
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleString()
}

onMounted(fetchDashboard)
</script>
