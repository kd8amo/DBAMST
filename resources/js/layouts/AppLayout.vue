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
                    <i :class="item.icon" />
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
                    class="w-full text-left px-3 py-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors flex items-center gap-3"
                >
                    <i class="pi pi-sign-out" />
                    <span>{{ $t('nav.logout') }}</span>
                </button>
            </div>
        </aside>
        <main class="flex-1 flex flex-col overflow-hidden">
            <header class="bg-white border-b px-6 py-3 flex items-center justify-between">
                <h2 class="text-gray-700 font-medium">{{ pageTitle }}</h2>
            </header>
            <div class="flex-1 overflow-auto p-6">
                <RouterView />
            </div>
        </main>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()

const navItems = [
    { name: 'dashboard',     to: '/',              label: 'nav.dashboard',    icon: 'pi pi-home' },
    { name: 'devices',       to: '/devices',       label: 'nav.devices',      icon: 'pi pi-microchip' },
    { name: 'test-systems',  to: '/test-systems',  label: 'nav.testSystems',  icon: 'pi pi-server' },
    { name: 'bookings',      to: '/bookings',      label: 'nav.bookings',     icon: 'pi pi-calendar' },
    { name: 'fault-reports', to: '/fault-reports', label: 'nav.faultReports', icon: 'pi pi-exclamation-triangle' },
    { name: 'maintenance',   to: '/maintenance',   label: 'nav.maintenance',  icon: 'pi pi-wrench' },
    { name: 'audit-log',     to: '/audit-log',     label: 'nav.auditLog',     icon: 'pi pi-list' },
    { name: 'users',         to: '/users',         label: 'nav.users',        icon: 'pi pi-users',      adminOnly: true },
    { name: 'api-keys',      to: '/api-keys',      label: 'nav.apiKeys',      icon: 'pi pi-key',        adminOnly: true },
    { name: 'sites',         to: '/sites',         label: 'nav.sites',        icon: 'pi pi-map-marker', adminOnly: true },
].filter(item => !item.adminOnly || auth.isAdmin)

const pageTitle = computed(() => route.name ?? '')

async function handleLogout() {
    await auth.logout()
    router.push({ name: 'login' })
}
</script>
