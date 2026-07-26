<template>
    <div>
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">{{ $t('nav.sites') }}</h2>
            <button
                v-if="auth.isAdmin"
                @click="showCreateModal = true"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
                {{ $t('common.create') }}
            </button>
        </div>

        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div v-if="loading" class="p-8 text-center text-gray-500">{{ $t('common.loading') }}</div>
            <div v-else-if="sites.length === 0" class="p-8 text-center text-gray-500">{{ $t('common.noData') }}</div>
            <table v-else class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Address</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="site in sites" :key="site.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ site.name }}</td>
                        <td class="px-4 py-3 font-mono text-sm text-gray-600">{{ site.code }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ site.address ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span :class="site.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                                class="px-2 py-1 rounded-full text-xs font-medium">
                                {{ site.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button v-if="auth.isAdmin" @click="openEditModal(site)"
                                class="text-blue-600 hover:text-blue-800 text-sm">Edit</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showCreateModal || showEditModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-xl">
                <h3 class="text-lg font-bold text-gray-800 mb-4">{{ showEditModal ? 'Edit Site' : 'Create Site' }}</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input v-model="siteForm.name" type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Code</label>
                        <input v-model="siteForm.code" type="text" maxlength="10"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <input v-model="siteForm.address" type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div v-if="showEditModal">
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" v-model="siteForm.is_active" class="rounded" />
                            Active
                        </label>
                    </div>
                    <div v-if="formError" class="text-red-600 text-sm">{{ formError }}</div>
                </div>
                <div class="flex gap-3 justify-end mt-4">
                    <button @click="closeModal" class="px-4 py-2 border rounded-lg hover:bg-gray-50">{{ $t('common.cancel') }}</button>
                    <button @click="submitForm" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">{{ $t('common.save') }}</button>
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

const sites          = ref([])
const loading        = ref(false)
const showCreateModal = ref(false)
const showEditModal   = ref(false)
const formError       = ref(null)
const selectedSite    = ref(null)

const siteForm = ref({ name: '', code: '', address: '', is_active: true })

async function fetchSites() {
    loading.value = true
    try {
        const response = await api.get('/sites')
        sites.value = response.data
    } catch (e) {
        console.error('Failed to fetch sites', e)
    } finally {
        loading.value = false
    }
}

function openEditModal(site) {
    selectedSite.value = site
    siteForm.value = { name: site.name, code: site.code, address: site.address ?? '', is_active: site.is_active }
    showEditModal.value = true
}

function closeModal() {
    showCreateModal.value = false
    showEditModal.value   = false
    formError.value       = null
    selectedSite.value    = null
    siteForm.value        = { name: '', code: '', address: '', is_active: true }
}

async function submitForm() {
    formError.value = null
    try {
        if (showEditModal.value) {
            await api.patch(`/sites/${selectedSite.value.id}`, siteForm.value)
        } else {
            await api.post('/sites', siteForm.value)
        }
        closeModal()
        await fetchSites()
    } catch (e) {
        formError.value = e.response?.data?.message ?? 'Failed to save site.'
    }
}

onMounted(fetchSites)
</script>
