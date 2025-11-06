<template>
  <div class="container mx-auto p-8">
    <h1 class="text-4xl font-bold mb-8">{{ $t('portfolioStats.title') }}</h1>
    
    <div v-if="isLoading" class="flex justify-center">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <div v-else-if="error" class="alert alert-error">
      <span>{{ error }}</span>
    </div>

    <div v-else-if="holdings.length === 0" class="card bg-base-100 shadow-xl">
      <div class="card-body">
        <p class="text-base-content/70">{{ $t('portfolioStats.noHoldings') }}</p>
      </div>
    </div>

    <div v-else class="space-y-6">
      <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <div class="overflow-x-auto">
            <table class="table table-zebra min-w-full">
              <thead>
                <tr>
                  <th class="min-w-[170px]">{{ $t('portfolioStats.table.product') }}</th>
                  <th class="min-w-[100px] text-right">{{ $t('portfolioStats.table.quantity') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="holding in holdings" :key="holding.isin">
                  <td class="whitespace-nowrap">{{ holding.product }}</td>
                  <td class="whitespace-nowrap text-right">{{ formatQuantity(holding.quantity) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="flex flex-col items-center gap-4 mt-6">
            <div class="flex items-center gap-2">
              <label class="label">
                <span class="label-text">{{ $t('portfolioStats.pagination.itemsPerPage') }}:</span>
              </label>
              <select v-model="perPage" @change="handlePerPageChange" class="select select-bordered select-sm">
                <option :value="20">20</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
              </select>
            </div>
            <div v-if="lastPage > 1" class="join">
              <button
                @click="loadPage(currentPage - 1)"
                :disabled="currentPage === 1"
                class="join-item btn"
              >
                «
              </button>
              <button class="join-item btn btn-active">
                {{ $t('portfolioStats.pagination.page', { current: currentPage, total: lastPage }) }}
              </button>
              <button
                @click="loadPage(currentPage + 1)"
                :disabled="currentPage === lastPage"
                class="join-item btn"
              >
                »
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { authService, PortfolioHolding } from '../services/authService'

const { t } = useI18n()

const isLoading = ref(true)
const error = ref<string | null>(null)
const holdings = ref<PortfolioHolding[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const perPage = ref(20)

const formatQuantity = (quantity: number): string => {
  // Format quantity with appropriate decimal places
  // For very small quantities (like Bitcoin), show more decimals
  if (Math.abs(quantity) < 1) {
    return new Intl.NumberFormat('en-US', {
      minimumFractionDigits: 6,
      maximumFractionDigits: 10
    }).format(quantity)
  } else {
    return new Intl.NumberFormat('en-US', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 4
    }).format(quantity)
  }
}

const loadPage = async (page: number) => {
  if (page < 1) return
  
  isLoading.value = true
  error.value = null
  
  try {
    const response = await authService.getPortfolioStats(perPage.value, page)
    holdings.value = response.data
    currentPage.value = response.current_page
    lastPage.value = response.last_page
  } catch (err: any) {
    console.error('Failed to load portfolio stats:', err)
    error.value = err.response?.data?.message || 'Failed to load portfolio stats'
  } finally {
    isLoading.value = false
  }
}

const handlePerPageChange = () => {
  // Reset to first page when changing items per page
  loadPage(1)
}

onMounted(async () => {
  await loadPage(1)
})
</script>


