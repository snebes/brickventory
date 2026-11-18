<script setup lang="ts">
import { inventoryResources, navigation } from '~/shared/inventory'
import { label } from '~/utils/inventory'

const navIcons: Record<string, string> = {
    dashboard: 'M3 10l9-7 9 7v10H3z M9 20v-6h6v6',
    products: 'M3 7l9-4 9 4v10l-9 4-9-4z M3 7l9 4 9-4 M12 11v10 M8 5l9 4',
    variants: 'M12 3l9 5-9 5-9-5z M3 12l9 5 9-5 M3 16l9 5 9-5',
    categories: 'M3 6h7l2 2h9v11H3z',
    orders: 'M5 4h14v16l-3-2-4 2-4-2-3 2z M8 8h8 M8 12h8',
    shipments: 'M2 5h12v12H2z M14 10h4l4 4v3h-8 M6 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4z M18 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
    'order-returns': 'M8 7l-5 5 5 5 M3 12h12a5 5 0 0 0 0-10',
    customers: 'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M4 21v-2a5 5 0 0 1 5-5h6a5 5 0 0 1 5 5v2',
    'purchase-orders': 'M3 4h2l3 10h10l3-7H6 M9 20h.01 M17 20h.01',
    replenishment: 'M4 13a8 8 0 0 1 14-6l2 2 M20 4v5h-5 M20 11a8 8 0 0 1-14 6l-2-2 M4 20v-5h5',
    suppliers: 'M3 21h18 M5 21V7h7v14 M12 21V3h7v18 M8 10h1 M8 14h1 M15 7h1 M15 11h1 M15 15h1',
    'stock-balances': 'M3 3v18h18 M7 16v-4 M12 16V6 M17 16v-7',
    'stock-movements': 'M3 12h4l3-7 4 14 3-7h4',
    'stock-transfers': 'M3 8h17l-4-4 M21 16H4l4 4',
    'stock-adjustments': 'M4 6h16 M4 12h16 M4 18h16 M8 4v4 M15 10v4 M10 16v4',
    'stock-audits': 'M9 4H5v17h14V4h-4 M9 3h6v4H9z M8 13l3 3 5-5',
    'cycle-counts': 'M3 6h18v15H3z M3 10h18 M8 3v5 M16 3v5 M8 15h3 M14 15h2',
    'trace-balances': 'M3 5v14 M7 5v14 M11 5v14 M16 5v14 M21 5v14',
    warehouses: 'M3 21V8l9-5 9 5v13H3z M7 21V11h10v10 M7 15h10 M7 18h10',
    locations: 'M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0z M12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
}
const route = useRoute()
const currentResource = computed(() => route.path.split('/')[1] || '')
const currentTitle = computed(() => {
    const resource = currentResource.value
    if (!resource) return 'Dashboard'
    if (resource === 'inventory-import') return 'Inventory tools'
    return inventoryResources[resource]?.title || label(resource.replaceAll('-', ' '))
})
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
const menuToggle = ref<HTMLButtonElement>()
const menuClose = ref<HTMLButtonElement>()
const mainContent = ref<HTMLElement>()
const logoutError = ref('')

async function openMenu() {
    menuOpen.value = true
    await nextTick()
    menuClose.value?.focus()
}
async function closeMenu() {
    menuOpen.value = false
    await nextTick()
    menuToggle.value?.focus()
}
function closeMenuOnFocusLeave(event: FocusEvent) {
    const sidebar = event.currentTarget as HTMLElement
    if (event.relatedTarget instanceof Node && !sidebar.contains(event.relatedTarget)) {
        menuOpen.value = false
    }
}
watch(
    () => route.path,
    async () => {
        if (!menuOpen.value) return
        menuOpen.value = false
        await nextTick()
        mainContent.value?.focus()
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
    <NuxtPage v-if="route.path === '/login'" />
    <div v-else class="workspace" @keydown.esc="menuOpen && closeMenu()">
        <a class="skip-link" href="#main-content">Skip to content</a>
        <div v-if="menuOpen" class="menu-backdrop" aria-hidden="true" @click="closeMenu"></div>
        <aside
            id="workspace-sidebar"
            class="sidebar"
            :class="{ open: menuOpen }"
            @focusout="closeMenuOnFocusLeave"
        >
            <div class="sidebar-header">
                <NuxtLink class="brand" to="/">
                    <img class="brand-logo" src="/branding/brickventory-logo.png" width="46" height="40" alt="" />
                    <span>Brickventory</span>
                </NuxtLink>
                <button ref="menuClose" type="button" class="menu-close icon-button" aria-label="Close navigation" @click="closeMenu">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6" /></svg>
                </button>
            </div>
            <nav aria-label="Workspace" class="ds-scroll">
                <NuxtLink to="/" :aria-current="route.path === '/' ? 'page' : undefined">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path :d="navIcons.dashboard" /></svg>
                    Dashboard
                </NuxtLink>
                <div v-for="group in navigation" :key="group.label" class="nav-group">
                    <p class="nav-label">{{ group.label }}</p>
                    <NuxtLink
                        v-for="resource in group.resources"
                        :key="resource"
                        :to="`/${resource}`"
                        :aria-current="currentResource === resource
                            ? (route.path === `/${resource}` ? 'page' : 'location')
                            : undefined"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path :d="navIcons[resource]" /></svg>
                        {{ inventoryResources[resource]?.title }}
                    </NuxtLink>
                </div>
            </nav>
            <div class="sidebar-footer"><small>Adapted from Inventoros</small></div>
        </aside>

        <div class="workspace-content">
            <header class="topbar">
                <button
                    ref="menuToggle"
                    type="button"
                    class="menu-toggle icon-button"
                    :aria-expanded="menuOpen"
                    aria-controls="workspace-sidebar"
                    aria-label="Toggle navigation"
                    @click="menuOpen ? closeMenu() : openMenu()"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <div class="topbar-location">
                    <NuxtLink to="/" aria-label="Workspace home">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path :d="navIcons.dashboard" /></svg>
                    </NuxtLink>
                    <svg class="location-divider" viewBox="0 0 24 24" aria-hidden="true"><path d="m10 8 4 4-4 4" /></svg>
                    <span>{{ currentTitle }}</span>
                </div>
                <div class="topbar-tools">
                    <label class="workspace-indicator">
                        <span>Warehouse</span>
                        <select v-model="warehouse" aria-label="Active warehouse">
                            <option value="all">All warehouses</option>
                            <option v-for="w in warehouseRows" :key="w.id" :value="String(w.id)">{{ w.name }}</option>
                        </select>
                    </label>
                    <button type="button" class="sign-out icon-button" aria-label="Sign out" title="Sign out" @click="logout">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H4v16h6 M10 12h11m-4-4 4 4-4 4" /></svg>
                        <span>Sign out</span>
                    </button>
                </div>
            </header>
            <main id="main-content" ref="mainContent" tabindex="-1">
                <p v-if="logoutError" class="error" role="alert">{{ logoutError }}</p>
                <NuxtPage />
            </main>
        </div>
    </div>
</template>
