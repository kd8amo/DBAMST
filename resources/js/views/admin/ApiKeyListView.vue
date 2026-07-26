<template>
    <div>
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">{{ $t('nav.apiKeys') }}</h2>
            <button @click="showCreateModal = true"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                Create API Key
            </button>
        </div>

        <!-- New key display -->
        <div v-if="newKeyToken" class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
            <p class="text-green-700 font-medium mb-1">✓ API Key created — copy this token now, it will not be shown again:</p>
            <code class="block bg-white border border-green-300 rounded p-2 text-sm font-mono break-all select-all">{{ newKeyToken }}</code>
            <button @click="newKeyToken = null" class="mt-2 text-sm text-green-600 hover:text-green-800">Dismiss</button>
        </div>

        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div v-if="loading" class="p-8 text-center text-gray-500">{{ $t('common.loading') }}</div>
            <div v-else-if="keys.length === 0" class="p-8 text-center text-gray-500">{{ $t('common.noData') }}</div>
            <table v-else class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Scopes</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expires</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="key in keys" :key="key.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ key.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <span v-for="scope in key.scopes" :key="scope"
                                class="inline-block mr-1 px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-mono">
                                {{ scope }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(key.created_at) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ key.expires_at ? formatDate(key.expires_at) : 'Never' }}</td>
                        <td class="px-4 py-3">
                            <span :class="key.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                                class="px-2 py-1 rounded-full text-xs font-medium">
                                {{ key.is_active ? 'Active' : 'Revoked' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button v-if="key.is_active" @click="revokeKey(key)"
                                class="text-red-600 hover:text-red-800 text-sm">Revoke</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-xl">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Create API Key</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input v-model="keyForm.name" type="text" placeholder="e.g. test-bench-01"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Scopes</label>
                        <div class="space-y-1">
                            <label v-for="scope in availableScopes" :key="scope" class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="checkbox" :value="scope" v-model="keyForm.scopes" class="rounded" />
                                <span class="font-mono text-blue-700">{{ scope }}</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Expires (optional)</label>
                        <input v-model="keyForm.expires_at" type="date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <input v-model="keyForm.description" type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div v-if="formError" class="text-red-600 text-sm">{{ formError }}</div>
                </div>
                <div class="flex gap-3 justify-end mt-4">
                    <button @click="showCreateModal = false; formError = null"
                        class="px-4 py-2 border rounded-lg hover:bg-gray-50">{{ $t('common.cancel') }}</button>
                    <button @click="createKey"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Create</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api.js'

const keys          = ref([])
const loading       = ref(false)
const showCreateModal = ref(false)
const formError     = ref(null)
const newKeyToken   = ref(null)

const availableScopes = ['read', 'write:usage', 'write:bookings', 'write:faults', 'write:maintenance', 'admin']

const keyForm = ref({
    name:        '',
    scopes:      ['read'],
    expires_at:  '',
    description: '',
})

async function fetchKeys() {
    loading.value = true
    try {
        const response = await api.get('/api-keys')
        keys.value = response.data
    } catch (e) {
        console.error('Failed to fetch API keys', e)
    } finally {
        loading.value = false
    }
}

async function createKey() {
    formError.value = null
    if (keyForm.value.scopes.length === 0) {
        formError.value = 'Select at least one scope.'
        return
    }
    try {
        const response = await api.post('/api-keys', keyForm.value)
        newKeyToken.value = response.data.token
        showCreateModal.value = false
        keyForm.value = { name: '', scopes: ['read'], expires_at: '', description: '' }
        await fetchKeys()
    } catch (e) {
        formError.value = e.response?.data?.message ?? 'Failed to create API key.'
    }
}

async function revokeKey(key) {
    if (!confirm(`Revoke API key "${key.name}"? This cannot be undone.`)) return
    try {
        await api.post(`/api-keys/${key.id}/revoke`)
        await fetchKeys()
    } catch (e) {
        console.error('Failed to revoke API key', e)
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString()
}

onMounted(fetchKeys)
</script>
