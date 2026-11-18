<script setup lang="ts">
import InventoryEditor from '~/components/inventory/InventoryEditor.vue'
const route = useRoute()
if (!/^\d+$/.test(String(route.params.id))) throw createError({ statusCode: 404, statusMessage: 'Record not found.' })
</script>
<template><InventoryEditor :key="String(route.params.id)" resource="stock-transfers" :id="String(route.params.id)" /></template>
