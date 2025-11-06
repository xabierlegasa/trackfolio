<template>
  <div class="container mx-auto p-8">
    <h1 class="text-4xl font-bold mb-8">{{ $t('trades.title') }}</h1>
    
    <div v-if="isLoading" class="flex justify-center">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <div v-else-if="error" class="alert alert-error">
      <span>{{ error }}</span>
    </div>

    <div v-else-if="trades.length === 0" class="card bg-base-100 shadow-xl">
      <div class="card-body">
        <p class="text-base-content/70">{{ $t('trades.noTrades') }}</p>
      </div>
    </div>

    <div v-else class="space-y-6">
      <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <div class="overflow-x-auto">
            <table class="table table-zebra min-w-full">
              <thead>
                <tr>
                  <th class="min-w-[150px] text-right">
                    <button 
                      @click="toggleSort('profit_loss')" 
                      class="flex items-center gap-1 hover:opacity-70 ml-auto"
                    >
                      {{ $t('trades.table.profitLoss') }}
                      <span v-if="sortBy === 'profit_loss'" class="text-xs">
                        {{ sortOrder === 'asc' ? '↑' : '↓' }}
                      </span>
                    </button>
                  </th>
                  <th class="min-w-[34px]">{{ $t('trades.table.product') }}</th>
                  <th class="min-w-[110px]">{{ $t('trades.table.firstPurchaseDate') }}</th>
                  <th class="min-w-[110px]">{{ $t('trades.table.lastSaleDate') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="trade in trades" :key="trade.isin">
                  <td class="whitespace-nowrap text-right" :class="getProfitLossClass(trade.profit_loss)">
                    {{ formatCurrency(trade.profit_loss, trade.currency) }}
                  </td>
                  <td class="whitespace-nowrap">{{ trade.product }}</td>
                  <td class="whitespace-nowrap">{{ formatDate(trade.first_purchase_date) }}</td>
                  <td class="whitespace-nowrap">{{ formatDate(trade.last_sale_date) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="flex flex-col items-center gap-4 mt-6">
            <div class="flex items-center gap-2">
              <label class="label">
                <span class="label-text">{{ $t('trades.pagination.itemsPerPage') }}:</span>
              </label>
              <select v-model="perPage" @change="handlePerPageChange" class="select select-bordered select-sm">
                <option :value="10">10</option>
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
                {{ $t('trades.pagination.page', { current: currentPage, total: lastPage }) }}
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
import { authService, Trade } from '../services/authService'

const { t } = useI18n()

const isLoading = ref<boolean>(true)
const error = ref<string | null>(null)
const trades = ref<Trade[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const perPage = ref(10)
const sortBy = ref('last_sale_date')
const sortOrder = ref<'asc' | 'desc'>('desc')

const formatCurrency = (valueMinUnit: number, currency: string): string => {
  const value = valueMinUnit / 100
  const sign = value >= 0 ? '+' : ''
  return `${sign}${value.toFixed(2)} ${currency}`
}

const getProfitLossClass = (profitLoss: number): string => {
  if (profitLoss > 0) {
    return 'text-success'
  } else if (profitLoss < 0) {
    return 'text-error'
  }
  return ''
}

const formatDate = (dateStr: string): string => {
  // Date format is DD-MM-YYYY, convert to a more readable format
  if (!dateStr) return '-'
  const [day, month, year] = dateStr.split('-')
  return `${year}-${month}-${day}`
}

const loadPage = async (page: number) => {
  if (page < 1) return
  
  isLoading.value = true
  error.value = null
  
  try {
    const response = await authService.getTrades(perPage.value, page, sortBy.value, sortOrder.value)
    trades.value = response.data
    currentPage.value = response.current_page
    lastPage.value = response.last_page
  } catch (err: any) {
    console.error('Failed to load trades:', err)
    error.value = err.response?.data?.message || 'Failed to load trades'
  } finally {
    isLoading.value = false
  }
}

const toggleSort = (column: string) => {
  if (sortBy.value === column) {
    // Toggle order if same column
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    // Set new column and default to desc
    sortBy.value = column
    sortOrder.value = 'desc'
  }
  // Reset to first page and reload
  loadPage(1)
}

const handlePerPageChange = () => {
  // Reset to first page when changing items per page
  loadPage(1)
}

onMounted(async () => {
  await loadPage(1)
})
</script>

