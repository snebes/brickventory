<script setup lang="ts">
import InventoryDetail from '~/components/inventory/InventoryDetail.vue'
const route = useRoute()
if (!/^\d+$/.test(String(route.params.id))) throw createError({ statusCode: 404, statusMessage: 'Record not found.' })
</script>
<template><InventoryDetail :key="String(route.params.id)" resource="categories" :id="String(route.params.id)" /></template>
