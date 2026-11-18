<script setup lang="ts">
import { errorMessage } from '~/utils/inventory'
useHead({ title: 'Sign in · Brickventory' })
const token = ref('')
const busy = ref(false)
const error = ref('')
async function login() {
  error.value = ''; busy.value = true
  try { await $fetch('/api/session', { method: 'POST', body: { token: token.value } }); token.value = ''; await navigateTo('/') }
  catch (cause) { error.value = errorMessage(cause) }
  finally { busy.value = false }
}
</script>
<template><main class="login-page"><section class="login-card"><div class="brand">◈ Brickventory</div><h1>Your inventory, in order.</h1><p>Sign in to manage stock, orders and purchasing.</p><form @submit.prevent="login"><label for="token">Workspace access token</label><input id="token" v-model="token" type="password" autocomplete="current-password" required autofocus :disabled="busy"><p v-if="error" class="error" role="alert">{{ error }}</p><button class="primary" :disabled="busy">{{ busy ? 'Signing in…' : 'Sign in' }}</button></form></section></main></template>
