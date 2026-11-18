<script setup lang="ts">
import { inventoryResources, navigation } from '~/shared/inventory'
const route = useRoute()
const warehouse = useCookie<string>('inventory-warehouse', { sameSite: 'lax' })
const warehouseRows = ref<any[]>([])
const api = useInventoryApi()
async function loadWarehouses() {
    if (route.path === '/login') return
    try {
        warehouseRows.value = (await api.list('warehouses', { per_page: 100 })).data
        if (!warehouse.value)
            warehouse.value = String(warehouseRows.value.find((w) => w.details?.is_default)?.id || 'all')
    } catch {
        /* Individual inventory views retain their request errors. */
    }
}
onMounted(loadWarehouses)
watch(() => route.path, loadWarehouses)
const menuOpen = ref(false)
const logoutError = ref('')
watch(
    () => route.path,
    () => {
        menuOpen.value = false
    },
)
async function logout() {
    try {
        await $fetch('/api/session', { method: 'DELETE' })
        clearNuxtData()
        clearNuxtState('inventory-labels')
        await navigateTo('/login')
    } catch {
        logoutError.value = 'Could not sign out. Please try again.'
    }
}
</script>
<template>
    <NuxtRouteAnnouncer />
    <div v-if="route.path === '/login'"><NuxtPage /></div>
    <div v-else class="workspace">
        <a class="skip-link" href="#main-content">Skip to content</a>
        <aside class="sidebar" :class="{ open: menuOpen }">
            <NuxtLink class="brand" to="/"
                >◈ <span>Brickventory<small>Inventory workspace</small></span></NuxtLink
            >
            <nav aria-label="Workspace">
                <NuxtLink to="/" :aria-current="route.path === '/' ? 'page' : undefined">Dashboard</NuxtLink>
                <NuxtLink
                    v-for="resource in navigation"
                    :key="resource"
                    :to="`/${resource}`"
                    :aria-current="route.path.startsWith(`/${resource}`) ? 'page' : undefined"
                    >{{ inventoryResources[resource]?.title }}</NuxtLink
                >
            </nav>
            <div class="sidebar-footer">
                <small>Adapted from Inventoros</small><button @click="logout">Sign out</button>
                <p v-if="logoutError" role="alert">{{ logoutError }}</p>
            </div>
        </aside>
        <div class="workspace-content">
            <header class="topbar">
                <button
                    class="menu-toggle"
                    :aria-expanded="menuOpen"
                    aria-label="Toggle navigation"
                    @click="menuOpen = !menuOpen"
                >
                    ☰</button
                ><span
                    >Workspace <span aria-hidden="true">/</span>
                    <strong>{{
                        inventoryResources[String(route.path.split('/')[1])]?.title || 'Dashboard'
                    }}</strong></span
                ><label class="workspace-indicator"
                    >Warehouse<select v-model="warehouse" aria-label="Active warehouse">
                        <option value="all">All warehouses</option>
                        <option v-for="w in warehouseRows" :key="w.id" :value="String(w.id)">{{ w.name }}</option>
                    </select></label
                >
            </header>
            <main id="main-content" tabindex="-1"><NuxtPage /></main>
        </div>
    </div>
</template>
