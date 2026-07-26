<template>
    <div class="min-h-screen bg-gray-100 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-lg p-8 w-full max-w-md">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-800">{{ $t('app.name') }}</h1>
                <p class="text-gray-500 mt-1">{{ $t('auth.login') }}</p>
            </div>
            <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                {{ error }}
            </div>
            <form @submit.prevent="handleLogin" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('auth.email') }}</label>
                    <input
                        v-model="email"
                        type="email"
                        required
                        autocomplete="email"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        :placeholder="$t('auth.email')"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('auth.password') }}</label>
                    <input
                        v-model="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        :placeholder="$t('auth.password')"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full py-2 px-4 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-medium rounded-lg transition-colors"
                >
                    {{ loading ? $t('auth.loggingIn') : $t('auth.loginButton') }}
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth.js'

const router = useRouter()
const auth   = useAuthStore()

const email    = ref('')
const password = ref('')
const loading  = ref(false)
const error    = ref(null)

async function handleLogin() {
    loading.value = true
    error.value   = null
    try {
        await auth.login(email.value, password.value)
        router.push({ name: 'dashboard' })
    } catch (e) {
        error.value = e.response?.data?.error ?? 'Login failed. Please try again.'
    } finally {
        loading.value = false
    }
}
</script>
