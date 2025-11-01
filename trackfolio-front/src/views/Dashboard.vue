<template>
  <div class="container mx-auto p-8">
    <h1 class="text-4xl font-bold mb-8">{{ $t('dashboard.title') }}</h1>
    
    <div v-if="userStore.isLoading" class="flex justify-center">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <div v-else-if="userStore.error" class="alert alert-error">
      <span>{{ userStore.error }}</span>
    </div>

    <div v-else-if="userStore.account" class="card bg-base-100 shadow-xl">
      <div class="card-body">
        <h2 class="card-title">{{ $t('dashboard.accountInfo') }}</h2>
        <div class="space-y-4">
          <div>
            <label class="label">
              <span class="label-text font-semibold">{{ $t('dashboard.name') }}</span>
            </label>
            <p class="text-lg">{{ userStore.account.name }}</p>
          </div>
          <div>
            <label class="label">
              <span class="label-text font-semibold">{{ $t('dashboard.email') }}</span>
            </label>
            <p class="text-lg">{{ userStore.account.email }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useUserStore } from '../stores/userStore'

const userStore = useUserStore()

onMounted(async () => {
  try {
    await userStore.fetchAccount()
  } catch (error) {
    // Error is handled in the store and displayed in template
    console.error('Failed to load account:', error)
  }
})
</script>

