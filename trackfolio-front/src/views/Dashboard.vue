<template>
  <div class="container mx-auto p-8">
    <h1 class="text-4xl font-bold mb-8">{{ $t('dashboard.title') }}</h1>
    
    <div v-if="isLoading" class="flex justify-center">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <div v-else-if="error" class="alert alert-error">
      <span>{{ error }}</span>
    </div>

    <div v-else class="space-y-8">
      <!-- Primary: Stats -->
      <div
        v-if="transactionCount !== null && transactionCount > 0"
        class="card bg-primary text-primary-content shadow-2xl"
      >
        <div class="card-body gap-4 py-10 px-8 md:px-10">
          <h2 class="text-3xl md:text-4xl font-bold leading-tight">
            {{ $t('dashboard.portfolioStats.title') }}
          </h2>
          <p class="text-lg md:text-xl opacity-90 max-w-2xl">
            {{ $t('dashboard.portfolioStats.description') }}
          </p>
          <div class="card-actions mt-2">
            <RouterLink
              :to="{ name: 'statistics' }"
              class="btn btn-lg bg-base-100 text-base-content border-0 hover:bg-base-200"
            >
              {{ $t('dashboard.portfolioStats.button') }}
            </RouterLink>
          </div>
        </div>
      </div>

      <div
        v-else
        class="card bg-base-200/70 shadow"
      >
        <div class="card-body py-6">
          <h2 class="text-xl font-semibold text-base-content/80">
            {{ $t('dashboard.portfolioStats.title') }}
          </h2>
          <p class="text-sm text-base-content/60">
            {{ $t('dashboard.uploadDegiroData.description') }}
          </p>
        </div>
      </div>

      <!-- Secondary actions -->
      <div class="grid gap-4 md:grid-cols-2">
        <div class="card bg-base-200/50 shadow-sm border border-base-300/60">
          <div class="card-body py-5 gap-2">
            <h2 class="text-base font-semibold text-base-content/80">
              {{ $t('dashboard.uploadDegiroData.title') }}
            </h2>
            <p class="text-sm text-base-content/55 mb-2">
              {{ $t('dashboard.uploadDegiroData.description') }}
            </p>
            <RouterLink
              :to="{ name: 'upload-degiro-transactions' }"
              class="btn btn-sm btn-outline btn-primary w-fit"
            >
              {{ $t('dashboard.uploadDegiroData.button') }}
            </RouterLink>
          </div>
        </div>

        <div
          v-if="transactionCount !== null && transactionCount > 0"
          class="card bg-base-200/50 shadow-sm border border-base-300/60"
        >
          <div class="card-body py-5 gap-2">
            <h2 class="text-base font-semibold text-base-content/80">
              {{ $t('dashboard.degiroTransactions.title') }}
            </h2>
            <p class="text-sm text-base-content/55 mb-2">
              {{ $t('dashboard.degiroTransactions.count', { count: formatInteger(transactionCount) }) }}
            </p>
            <RouterLink
              :to="{ name: 'degiro-transactions-list' }"
              class="btn btn-sm btn-ghost w-fit"
            >
              {{ $t('dashboard.degiroTransactions.viewList') }}
            </RouterLink>
          </div>
        </div>

        <div
          v-if="transactionCount !== null && transactionCount > 0"
          class="card bg-base-200/50 shadow-sm border border-base-300/60"
        >
          <div class="card-body py-5 gap-2">
            <h2 class="text-base font-semibold text-base-content/80">
              {{ $t('dashboard.taxReturn.title') }}
            </h2>
            <p class="text-sm text-base-content/55 mb-2">
              {{ $t('dashboard.taxReturn.description') }}
            </p>
            <RouterLink
              :to="{ name: 'tax-return-years' }"
              class="btn btn-sm btn-ghost w-fit"
            >
              {{ $t('dashboard.taxReturn.button') }}
            </RouterLink>
          </div>
        </div>

        <div class="card bg-base-200/50 shadow-sm border border-base-300/60">
          <div class="card-body py-5 gap-2">
            <h2 class="text-base font-semibold text-base-content/80">
              {{ $t('dashboard.configuration.title') }}
            </h2>
            <p class="text-sm text-base-content/55 mb-2">
              {{ $t('dashboard.configuration.description') }}
            </p>
            <RouterLink
              :to="{ name: 'configuration' }"
              class="btn btn-sm btn-ghost w-fit"
            >
              {{ $t('dashboard.configuration.button') }}
            </RouterLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { authService } from '../services/authService'
import { formatInteger } from '../utils/numberFormat'

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
