<script setup lang="ts">
defineProps<{ value?: string | boolean }>()
</script>
<template>
    <span class="badge" :class="String(value)">{{
        typeof value === 'boolean' ? (value ? 'Active' : 'Inactive') : value?.replaceAll('_', ' ') || '—'
    }}</span>
</template>
