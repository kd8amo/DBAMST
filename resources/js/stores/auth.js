import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../api.js'

export const useAuthStore = defineStore('auth', () => {
    const token = ref(localStorage.getItem('tsm_token') || null)
    const user  = ref(JSON.parse(localStorage.getItem('tsm_user') || 'null'))

    const isAuthenticated = computed(() => !!token.value)
    const isAdmin         = computed(() => user.value?.role?.name === 'admin')
    const isAuditor       = computed(() => user.value?.role?.name === 'auditor')
    const canWrite        = computed(() => !isAuditor.value)

    async function login(email, password) {
        const response = await api.post('/login', { email, password })
        token.value = response.data.token
        user.value  = response.data.user
        localStorage.setItem('tsm_token', token.value)
        localStorage.setItem('tsm_user', JSON.stringify(user.value))
        api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
    }

    async function logout() {
        try {
            await api.post('/logout')
        } finally {
            token.value = null
            user.value  = null
            localStorage.removeItem('tsm_token')
            localStorage.removeItem('tsm_user')
            delete api.defaults.headers.common['Authorization']
        }
    }

    // Restore token on page reload
    if (token.value) {
        api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
    }

    return { token, user, isAuthenticated, isAdmin, isAuditor, canWrite, login, logout }
})
