<template>
    <div>
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">{{ $t('nav.users') }}</h2>
            <button @click="showCreateModal = true"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                {{ $t('common.create') }}
            </button>
        </div>

        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div v-if="loading" class="p-8 text-center text-gray-500">{{ $t('common.loading') }}</div>
            <div v-else-if="users.length === 0" class="p-8 text-center text-gray-500">{{ $t('common.noData') }}</div>
            <table v-else class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ user.display_name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ user.email }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ user.role?.name }}</td>
                        <td class="px-4 py-3">
                            <span :class="user.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                                class="px-2 py-1 rounded-full text-xs font-medium">
                                {{ user.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right flex gap-2 justify-end">
                            <button @click="openEditModal(user)" class="text-blue-600 hover:text-blue-800 text-sm">Edit</button>
                            <button v-if="user.is_active && user.id !== auth.user?.id"
                                @click="deactivateUser(user)" class="text-red-600 hover:text-red-800 text-sm">Deactivate</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showCreateModal || showEditModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-xl">
                <h3 class="text-lg font-bold text-gray-800 mb-4">{{ showEditModal ? 'Edit User' : 'Create User' }}</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Display Name</label>
                        <input v-model="userForm.display_name" type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input v-model="userForm.email" type="email"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div v-if="showCreateModal">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input v-model="userForm.password" type="password"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select v-model="userForm.role_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select a role</option>
                            <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                        </select>
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

const users           = ref([])
const roles           = ref([])
const loading         = ref(false)
const showCreateModal = ref(false)
const showEditModal   = ref(false)
const formError       = ref(null)
const selectedUser    = ref(null)

const userForm = ref({
    display_name: '',
    email:        '',
    password:     '',
    role_id:      '',
})

async function fetchUsers() {
    loading.value = true
    try {
        const response = await api.get('/users')
        users.value = response.data.data
    } catch (e) {
        console.error('Failed to fetch users', e)
    } finally {
        loading.value = false
    }
}

async function fetchRoles() {
    const response = await api.get('/roles')
    roles.value = response.data
}

function openEditModal(user) {
    selectedUser.value = user
    userForm.value = { display_name: user.display_name, email: user.email, password: '', role_id: user.role?.id }
    showEditModal.value = true
}

function closeModal() {
    showCreateModal.value = false
    showEditModal.value   = false
    formError.value       = null
    selectedUser.value    = null
    userForm.value        = { display_name: '', email: '', password: '', role_id: '' }
}

async function submitForm() {
    formError.value = null
    try {
        if (showEditModal.value) {
            await api.patch(`/users/${selectedUser.value.id}`, {
                display_name: userForm.value.display_name,
                email:        userForm.value.email,
                role_id:      userForm.value.role_id,
            })
        } else {
            await api.post('/users', userForm.value)
        }
        closeModal()
        await fetchUsers()
    } catch (e) {
        formError.value = e.response?.data?.message ?? 'Failed to save user.'
    }
}

async function deactivateUser(user) {
    if (!confirm(`Deactivate ${user.display_name}?`)) return
    try {
        await api.post(`/users/${user.id}/deactivate`)
        await fetchUsers()
    } catch (e) {
        console.error('Failed to deactivate user', e)
    }
}

onMounted(() => {
    fetchRoles()
    fetchUsers()
})
</script>
