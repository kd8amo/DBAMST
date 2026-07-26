<template>
    <div v-if="loading" class="p-8 text-center text-gray-500">{{ $t('common.loading') }}</div>
    <div v-else-if="!device" class="p-8 text-center text-gray-500">Device not found.</div>
    <div v-else>
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <RouterLink to="/devices" class="text-blue-600 hover:text-blue-800 text-sm">← Devices</RouterLink>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 font-mono">{{ device.asset_tag }}</h2>
                <p class="text-gray-500">{{ device.manufacturer }} {{ device.model }}</p>
            </div>
            <div class="flex gap-2">
                <button
                    v-if="auth.canWrite && !device.is_active"
                    disabled
                    class="px-4 py-2 bg-gray-200 text-gray-500 rounded-lg cursor-not-allowed"
                >
                    Retired
                </button>
                <button
                    v-if="auth.canWrite && device.is_active"
                    @click="showRetireConfirm = true"
                    class="px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-colors"
                >
                    Retire Device
                </button>
                <button
                    v-if="auth.canWrite && device.is_active"
                    @click="showEditModal = true"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    {{ $t('common.edit') }}
                </button>
            </div>
        </div>

        <!-- Status badge -->
        <div class="flex gap-3 mb-6">
            <span :class="statusClass(device.status?.name)" class="px-3 py-1 rounded-full text-sm font-medium">
                {{ device.status?.name }}
            </span>
            <span class="px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                {{ device.category?.name }}
            </span>
        </div>

        <!-- Info grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow-sm p-4">
                <h3 class="font-semibold text-gray-700 mb-3">Device Information</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Asset Tag</dt>
                        <dd class="font-mono font-medium">{{ device.asset_tag }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Serial Number</dt>
                        <dd>{{ device.serial_number ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Manufacturer</dt>
                        <dd>{{ device.manufacturer }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Model</dt>
                        <dd>{{ device.model }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Category</dt>
                        <dd>{{ device.category?.name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Site</dt>
                        <dd>{{ device.site?.name }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-4">
                <h3 class="font-semibold text-gray-700 mb-3">Current Assignment</h3>
                <div v-if="device.current_assignment">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Test System</dt>
                            <dd class="font-medium">{{ device.current_assignment.test_system?.name }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Type</dt>
                            <dd>{{ device.current_assignment.assignment_type }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Since</dt>
                            <dd>{{ formatDate(device.current_assignment.started_at) }}</dd>
                        </div>
                    </dl>
                </div>
                <p v-else class="text-sm text-gray-500">Not currently assigned to any test system.</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="border-b flex">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    :class="activeTab === tab.id
                        ? 'border-b-2 border-blue-600 text-blue-600'
                        : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-3 text-sm font-medium transition-colors"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div class="p-4">
                <!-- Assignment History -->
                <div v-if="activeTab === 'assignments'">
                    <div v-if="device.assignments?.length === 0" class="text-gray-500 text-sm">No assignment history.</div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b">
                                <th class="pb-2">Test System</th>
                                <th class="pb-2">Type</th>
                                <th class="pb-2">From</th>
                                <th class="pb-2">To</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="a in device.assignments" :key="a.id">
                                <td class="py-2">{{ a.test_system?.name }}</td>
                                <td class="py-2">{{ a.assignment_type }}</td>
                                <td class="py-2">{{ formatDate(a.started_at) }}</td>
                                <td class="py-2">{{ a.ended_at ? formatDate(a.ended_at) : 'Current' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Maintenance History -->
                <div v-if="activeTab === 'maintenance'">
                    <div class="flex justify-between mb-3">
                        <span class="text-sm text-gray-500">Calibration and maintenance records</span>
                        <button
                            v-if="auth.canWrite"
                            @click="showMaintenanceModal = true"
                            class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700"
                        >
                            Log Event
                        </button>
                    </div>
                    <div v-if="device.maintenance_events?.length === 0" class="text-gray-500 text-sm">No maintenance records.</div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b">
                                <th class="pb-2">Type</th>
                                <th class="pb-2">Date</th>
                                <th class="pb-2">Performed By</th>
                                <th class="pb-2">Result</th>
                                <th class="pb-2">Next Due</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="e in device.maintenance_events" :key="e.id">
                                <td class="py-2">{{ e.event_type?.name }}</td>
                                <td class="py-2">{{ formatDate(e.performed_at) }}</td>
                                <td class="py-2">{{ e.performed_by }}</td>
                                <td class="py-2">
                                    <span :class="e.result === 'pass' ? 'text-green-600' : 'text-red-600'" class="font-medium">
                                        {{ e.result }}
                                    </span>
                                </td>
                                <td class="py-2">{{ e.next_due_at ? formatDate(e.next_due_at) : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Fault Reports -->
                <div v-if="activeTab === 'faults'">
                    <div class="flex justify-between mb-3">
                        <span class="text-sm text-gray-500">Open and resolved fault reports</span>
                        <button
                            v-if="auth.canWrite"
                            @click="showFaultModal = true"
                            class="px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700"
                        >
                            Report Fault
                        </button>
                    </div>
                    <div v-if="device.fault_reports?.length === 0" class="text-gray-500 text-sm">No fault reports.</div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b">
                                <th class="pb-2">Description</th>
                                <th class="pb-2">Reported</th>
                                <th class="pb-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="f in device.fault_reports" :key="f.id">
                                <td class="py-2">{{ f.description }}</td>
                                <td class="py-2">{{ formatDate(f.reported_at) }}</td>
                                <td class="py-2">
                                    <span :class="faultStatusClass(f.status?.name)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                                        {{ f.status?.name }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Transfer History -->
                <div v-if="activeTab === 'transfers'">
                    <div v-if="device.transfers?.length === 0" class="text-gray-500 text-sm">No transfer history.</div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b">
                                <th class="pb-2">From</th>
                                <th class="pb-2">To</th>
                                <th class="pb-2">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="t in device.transfers" :key="t.id">
                                <td class="py-2">{{ t.from_site?.name }}</td>
                                <td class="py-2">{{ t.to_site?.name }}</td>
                                <td class="py-2">{{ formatDate(t.transferred_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Retire confirmation -->
        <div v-if="showRetireConfirm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-xl">
                <h3 class="text-lg font-bold text-gray-800 mb-2">Retire Device</h3>
                <p class="text-gray-600 mb-4">Are you sure you want to retire <strong>{{ device.asset_tag }}</strong>? This cannot be undone.</p>
                <div class="flex gap-3 justify-end">
                    <button @click="showRetireConfirm = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</button>
                    <button @click="retireDevice" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Retire</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth.js'
import api from '../../api.js'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()

const device             = ref(null)
const loading            = ref(false)
const showRetireConfirm  = ref(false)
const showEditModal      = ref(false)
const showMaintenanceModal = ref(false)
const showFaultModal     = ref(false)
const activeTab          = ref('assignments')

const tabs = [
    { id: 'assignments', label: 'Assignment History' },
    { id: 'maintenance', label: 'Maintenance' },
    { id: 'faults',      label: 'Fault Reports' },
    { id: 'transfers',   label: 'Transfer History' },
]

async function fetchDevice() {
    loading.value = true
    try {
        const response = await api.get(`/devices/${route.params.id}`)
        device.value = response.data
    } catch (e) {
        console.error('Failed to fetch device', e)
    } finally {
        loading.value = false
    }
}

async function retireDevice() {
    try {
        await api.post(`/devices/${device.value.id}/retire`)
        showRetireConfirm.value = false
        await fetchDevice()
    } catch (e) {
        console.error('Failed to retire device', e)
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString()
}

function statusClass(name) {
    const map = {
        assigned:            'bg-green-100 text-green-800',
        unassigned:          'bg-gray-100 text-gray-700',
        out_for_calibration: 'bg-yellow-100 text-yellow-800',
        retired:             'bg-red-100 text-red-700',
    }
    return map[name] ?? 'bg-gray-100 text-gray-700'
}

function faultStatusClass(name) {
    const map = {
        open:        'bg-red-100 text-red-800',
        in_progress: 'bg-yellow-100 text-yellow-800',
        resolved:    'bg-green-100 text-green-800',
    }
    return map[name] ?? 'bg-gray-100 text-gray-700'
}

onMounted(fetchDevice)
</script>
