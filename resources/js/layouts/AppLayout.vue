<template>
    <div class="min-h-screen bg-gray-100 flex">
        <aside class="w-64 bg-gray-900 text-white flex flex-col">
            <div class="p-4 border-b border-gray-700">
                <h1 class="text-lg font-semibold">{{ $t('app.name') }}</h1>
            </div>
            <nav class="flex-1 p-4 space-y-1">
                <RouterLink
                    v-for="item in navItems"
                    :key="item.name"
                    :to="item.to"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors"
                    active-class="bg-gray-700 text-white"
                >
                    <span>{{ $t(item.label) }}</span>
                </RouterLink>
            </nav>
            <div class="p-4 border-t border-gray-700">
                <div class="text-sm text-gray-400 mb-2">
                    {{ auth.user?.display_name }}
                    <span class="text-xs text-gray-500 block">{{ auth.user?.role?.name }}</span>
                </div>
                <button
                    @click="handleLogout"
                    class="w-full text-left px-3 py-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors"
                >
                    {{ $t('nav.logout') }}
                </button>
            </div>
        </aside>

        <main class="flex-1 flex flex-col overflow-hidden">
            <!-- Top bar -->
            <header class="bg-white border-b px-6 py-3 flex items-center justify-between">
                <h2 class="text-gray-700 font-medium">{{ pageTitle }}</h2>

                <!-- Notification bell -->
                <div class="relative">
                    <button @click="toggleNotifications"
                        class="relative p-2 text-gray-500 hover:text-gray-700 transition-colors">
                        🔔
                        <span v-if="unreadCount > 0"
                            class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                            {{ unreadCount > 9 ? '9+' : unreadCount }}
                        </span>
                    </button>

                    <!-- Notification dropdown -->
                    <div v-if="showNotifications"
                        class="absolute right-0 top-10 w-96 bg-white rounded-lg shadow-xl border z-50 max-h-96 overflow-y-auto">
                        <div class="px-4 py-3 border-b flex items-center justify-between">
                            <span class="font-semibold text-gray-700">Notifications</span>
                            <button v-if="unreadCount > 0" @click="markAllRead"
                                class="text-xs text-blue-600 hover:text-blue-800">Mark all read</button>
                        </div>
                        <div v-if="notifications.length === 0" class="p-4 text-sm text-gray-500 text-center">
                            No notifications.
                        </div>
                        <ul v-else class="divide-y divide-gray-100">
                            <li v-for="n in notifications" :key="n.id"
                                :class="!n.is_read ? 'bg-blue-50' : ''"
                                class="px-4 py-3 hover:bg-gray-50 cursor-pointer"
                                @click="markRead(n)">
                                <p class="text-sm text-gray-800" :class="!n.is_read ? 'font-medium' : ''">
                                    {{ n.message }}
                                </p>
                                <p class="text-xs text-gray-400 mt-1">{{ formatDate(n.created_at) }}</p>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <div class="flex-1 overflow-auto p-6">
                <RouterView />
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import api from '../api.js'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()

const notifications    = ref([])
const showNotifications = ref(false)

const unreadCount = computed(() => notifications.value.filter(n => !n.is_read).length)
const pageTitle   = computed(() => route.name ?? '')

const navItems = [
    { name: 'dashboard',     to: '/',              label: 'nav.dashboard'    },
    { name: 'devices',       to: '/devices',       label: 'nav.devices'      },
    { name: 'test-systems',  to: '/test-systems',  label: 'nav.testSystems'  },
    { name: 'bookings',      to: '/bookings',      label: 'nav.bookings'     },
    { name: 'fault-reports', to: '/fault-reports', label: 'nav.faultReports' },
    { name: 'maintenance',   to: '/maintenance',   label: 'nav.maintenance'  },
    { name: 'audit-log',     to: '/audit-log',     label: 'nav.auditLog'     },
    { name: 'users',         to: '/users',         label: 'nav.users',       adminOnly: true },
    { name: 'api-keys',      to: '/api-keys',      label: 'nav.apiKeys',     adminOnly: true },
    { name: 'sites',         to: '/sites',         label: 'nav.sites',       adminOnly: true },
].filter(item => !item.adminOnly || auth.isAdmin)

async function fetchNotifications() {
    try {
        const response = await api.get('/notifications')
        notifications.value = response.data
    } catch (e) {
        console.error('Failed to fetch notifications', e)
    }
}

function toggleNotifications() {
    showNotifications.value = !showNotifications.value
    if (showNotifications.value) fetchNotifications()
}

async function markRead(notification) {
    if (notification.is_read) return
    try {
        await api.patch(`/notifications/${notification.id}/read`)
        notification.is_read = true
    } catch (e) {
        console.error('Failed to mark notification read', e)
    }
}

async function markAllRead() {
    try {
        await api.post('/notifications/mark-all-read')
        notifications.value.forEach(n => n.is_read = true)
    } catch (e) {
        console.error('Failed to mark all read', e)
    }
}

function formatDate(dateStr) {
    if (!dateStr) return ''
    return new Date(dateStr).toLocaleString()
}

// Close dropdown when clicking outside
function handleClickOutside(e) {
    if (!e.target.closest('.relative')) {
        showNotifications.value = false
    }
}

async function handleLogout() {
    await auth.logout()
    router.push({ name: 'login' })
}

onMounted(() => {
    fetchNotifications()
    document.addEventListener('click', handleClickOutside)
    // Poll for new notifications every 60 seconds
    const interval = setInterval(fetchNotifications, 60000)
    onUnmounted(() => {
        clearInterval(interval)
        document.removeEventListener('click', handleClickOutside)
    })
})
</script>
