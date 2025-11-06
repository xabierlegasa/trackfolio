<template>
  <div class="container mx-auto p-8">
    <h1 class="text-4xl font-bold mb-8">{{ $t('dashboard.title') }}</h1>
    
    <div v-if="isLoading" class="flex justify-center">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <div v-else-if="error" class="alert alert-error">
      <span>{{ error }}</span>
    </div>

    <div v-else class="space-y-6">
      <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <h2 class="card-title">{{ $t('dashboard.uploadDegiroData.title') }}</h2>
          <p class="text-base-content/70 mb-4">{{ $t('dashboard.uploadDegiroData.description') }}</p>
          <RouterLink :to="{ name: 'upload-degiro-transactions' }" class="btn btn-primary">
            {{ $t('dashboard.uploadDegiroData.button') }}
          </RouterLink>
        </div>
      </div>

      <div v-if="transactionCount !== null && transactionCount > 0" class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <h2 class="card-title">{{ $t('dashboard.degiroTransactions.title') }}</h2>
          <p class="text-lg mb-4">
            {{ $t('dashboard.degiroTransactions.count', { count: transactionCount }) }}
          </p>
          <RouterLink :to="{ name: 'degiro-transactions-list' }" class="btn btn-success">
            {{ $t('dashboard.degiroTransactions.viewList') }}
          </RouterLink>
        </div>
      </div>

      <div v-if="transactionCount !== null && transactionCount > 0" class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <h2 class="card-title">{{ $t('dashboard.portfolioStats.title') }}</h2>
          <p class="text-base-content/70 mb-4">{{ $t('dashboard.portfolioStats.description') }}</p>
          <RouterLink :to="{ name: 'statistics' }" class="btn btn-primary">
            {{ $t('dashboard.portfolioStats.button') }}
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { authService } from '../services/authService'

const isLoading = ref(true)
const error = ref<string | null>(null)
const transactionCount = ref<number | null>(null)

onMounted(async () => {
  try {
    isLoading.value = true
    error.value = null
    const response = await authService.getDegiroTransactionsCount()
    transactionCount.value = response.count
  } catch (err: any) {
    console.error('Failed to load transaction count:', err)
    error.value = err.response?.data?.message || 'Failed to load transaction count'
  } finally {
    isLoading.value = false
  }
})
</script>

