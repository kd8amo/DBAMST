<template>
    <div v-if="loading" class="p-8 text-center text-gray-500">{{ $t('common.loading') }}</div>
    <div v-else-if="!system" class="p-8 text-center text-gray-500">Test system not found.</div>
    <div v-else>
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <RouterLink to="/test-systems" class="text-blue-600 hover:text-blue-800 text-sm">← Test Systems</RouterLink>
                <h2 class="text-2xl font-bold text-gray-800 mt-1">{{ system.name }}</h2>
                <p class="text-gray-500">{{ system.site?.name }}</p>
            </div>
            <div class="flex gap-2">
                <button
                    v-if="auth.canWrite"
                    @click="showFaultModal = true"
                    class="px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-colors"
                >
                    Report Fault
                </button>
                <button
                    v-if="auth.canWrite"
                    @click="showAssignModal = true"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Assign Device
                </button>
            </div>
        </div>

        <!-- Status -->
        <div class="mb-6">
            <span :class="statusClass(system.status?.name)" class="px-3 py-1 rounded-full text-sm font-medium">
                {{ system.status?.name }}
            </span>
        </div>

        <!-- Open faults warning -->
        <div v-if="openFaults.length > 0" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
            <p class="text-red-700 font-medium text-sm">⚠ {{ openFaults.length }} open fault report(s) on this system</p>
        </div>

        <!-- Current devices -->
        <div class="bg-white rounded-lg shadow-sm mb-4">
            <div class="px-4 py-3 border-b flex items-center justify-between">
                <h3 class="font-semibold text-gray-700">Current Devices ({{ system.current_assignments?.length ?? 0 }})</h3>
            </div>
            <div v-if="system.current_assignments?.length === 0" class="p-4 text-sm text-gray-500">
                No devices currently assigned.
            </div>
            <table v-else class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Asset Tag</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Device</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="a in system.current_assignments" :key="a.id" class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-mono text-sm text-blue-600">{{ a.device?.asset_tag }}</td>
                        <td class="px-4 py-2 text-sm">{{ a.device?.manufacturer }} {{ a.device?.model }}</td>
                        <td class="px-4 py-2 text-sm text-gray-600">{{ a.device?.category?.name }}</td>
                        <td class="px-4 py-2 text-sm text-gray-600">{{ a.assignment_type }}</td>
                        <td class="px-4 py-2">
                            <span :class="deviceStatusClass(a.device?.status?.name)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                                {{ a.device?.status?.name }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button
                                v-if="auth.canWrite"
                                @click="unassignDevice(a.device)"
                                class="text-red-600 hover:text-red-800 text-xs"
                            >
                                Unassign
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
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
                <!-- Fault Reports -->
                <div v-if="activeTab === 'faults'">
                    <div v-if="system.fault_reports?.length === 0" class="text-sm text-gray-500">No fault reports.</div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b">
                                <th class="pb-2">Description</th>
                                <th class="pb-2">Reported</th>
                                <th class="pb-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="f in system.fault_reports" :key="f.id">
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

                <!-- Bookings -->
                <div v-if="activeTab === 'bookings'">
                    <div v-if="system.bookings?.length === 0" class="text-sm text-gray-500">No bookings.</div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b">
                                <th class="pb-2">Title</th>
                                <th class="pb-2">Start</th>
                                <th class="pb-2">End</th>
                                <th class="pb-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="b in system.bookings" :key="b.id">
                                <td class="py-2">{{ b.title }}</td>
                                <td class="py-2">{{ formatDate(b.starts_at) }}</td>
                                <td class="py-2">{{ formatDate(b.ends_at) }}</td>
                                <td class="py-2">{{ b.status?.name }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Assign Device Modal -->
        <div v-if="showAssignModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-xl">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Assign Device</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Asset Tag</label>
                        <input
                            v-model="assignForm.asset_tag"
                            type="text"
                            placeholder="e.g. MEA-000001"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @blur="lookupDevice"
                        />
                        <p v-if="lookedUpDevice" class="text-sm text-green-600 mt-1">
                            ✓ {{ lookedUpDevice.manufacturer }} {{ lookedUpDevice.model }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assignment Type</label>
                        <select
                            v-model="assignForm.assignment_type"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="fixed">Fixed</option>
                            <option value="swappable">Swappable</option>
                        </select>
                    </div>
                    <div v-if="assignError" class="text-red-600 text-sm">{{ assignError }}</div>
                </div>
                <div class="flex gap-3 justify-end mt-4">
                    <button @click="showAssignModal = false; assignError = null" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
                        {{ $t('common.cancel') }}
                    </button>
                    <button @click="assignDevice" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Assign
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth.js'
import api from '../../api.js'

const route = useRoute()
const auth  = useAuthStore()

const system          = ref(null)
const loading         = ref(false)
const showAssignModal = ref(false)
const showFaultModal  = ref(false)
const assignError     = ref(null)
const lookedUpDevice  = ref(null)
const activeTab       = ref('faults')

const assignForm = ref({
    asset_tag:       '',
    assignment_type: 'swappable',
})

const tabs = [
    { id: 'faults',   label: 'Fault Reports' },
    { id: 'bookings', label: 'Bookings' },
]

const openFaults = computed(() =>
    system.value?.fault_reports?.filter(f => f.status?.name !== 'resolved') ?? []
)

async function fetchSystem() {
    loading.value = true
    try {
        const response = await api.get(`/test-systems/${route.params.id}`)
        system.value = response.data
    } catch (e) {
        console.error('Failed to fetch test system', e)
    } finally {
        loading.value = false
    }
}

async function lookupDevice() {
    if (!assignForm.value.asset_tag) return
    try {
        const response = await api.get('/devices/find-by-asset-tag', {
            params: { asset_tag: assignForm.value.asset_tag }
        })
        lookedUpDevice.value = response.data
    } catch (e) {
        lookedUpDevice.value = null
    }
}

async function assignDevice() {
    assignError.value = null
    if (!lookedUpDevice.value) {
        assignError.value = 'Please enter a valid asset tag.'
        return
    }
    try {
        await api.post(`/test-systems/${system.value.id}/assign-device`, {
            device_id:       lookedUpDevice.value.id,
            assignment_type: assignForm.value.assignment_type,
        })
        showAssignModal.value = false
        assignForm.value = { asset_tag: '', assignment_type: 'swappable' }
        lookedUpDevice.value = null
        await fetchSystem()
    } catch (e) {
        assignError.value = e.response?.data?.error ?? 'Failed to assign device.'
    }
}

async function unassignDevice(device) {
    if (!confirm(`Unassign ${device.asset_tag} from this system?`)) return
    try {
        await api.delete(`/test-systems/${system.value.id}/devices/${device.id}`)
        await fetchSystem()
    } catch (e) {
        console.error('Failed to unassign device', e)
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString()
}

function statusClass(name) {
    const map = {
        active:         'bg-green-100 text-green-800',
        in_maintenance: 'bg-yellow-100 text-yellow-800',
        retired:        'bg-red-100 text-red-700',
    }
    return map[name] ?? 'bg-gray-100 text-gray-700'
}

function deviceStatusClass(name) {
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

onMounted(fetchSystem)
</script>
