<template>
  <div class="container mx-auto p-8">
    <h1 class="text-4xl font-bold mb-8">{{ $t('degiroTransactionsList.title') }}</h1>
    
    <div v-if="isLoading" class="flex justify-center">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <div v-else-if="error" class="alert alert-error">
      <span>{{ error }}</span>
    </div>

    <div v-else-if="transactions.length === 0" class="card bg-base-100 shadow-xl">
      <div class="card-body">
        <p class="text-base-content/70">{{ $t('degiroTransactionsList.noTransactions') }}</p>
      </div>
    </div>

    <div v-else class="space-y-6">
      <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <div class="overflow-x-auto">
            <table class="table table-zebra min-w-full">
              <thead>
                <tr>
                  <th class="min-w-[110px]">{{ $t('degiroTransactionsList.table.date') }}</th>
                  <th class="min-w-[170px]">{{ $t('degiroTransactionsList.table.product') }}</th>
                  <th class="min-w-[100px] text-right">{{ $t('degiroTransactionsList.table.quantity') }}</th>
                  <th class="min-w-[130px] text-right">
                    <div class="flex items-center justify-end gap-1">
                      <span>{{ $t('degiroTransactionsList.table.price') }}</span>
                      <button 
                        @click="showTooltip('price')"
                        class="btn btn-ghost btn-xs btn-circle p-0 min-h-0 h-4 w-4"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="h-3 w-3">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                      </button>
                    </div>
                  </th>
                  <th class="min-w-[130px] text-right">
                    <div class="flex items-center justify-end gap-1">
                      <span>{{ $t('degiroTransactionsList.table.localValue') }}</span>
                      <button 
                        @click="showTooltip('localValue')"
                        class="btn btn-ghost btn-xs btn-circle p-0 min-h-0 h-4 w-4"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="h-3 w-3">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                      </button>
                    </div>
                  </th>
                  <th class="min-w-[130px] text-right">
                    <div class="flex items-center justify-end gap-1">
                      <span>{{ $t('degiroTransactionsList.table.value') }}</span>
                      <button 
                        @click="showTooltip('value')"
                        class="btn btn-ghost btn-xs btn-circle p-0 min-h-0 h-4 w-4"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="h-3 w-3">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                      </button>
                    </div>
                  </th>
                  <th class="min-w-[250px]">{{ $t('degiroTransactionsList.table.orderId') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="transaction in transactions" :key="transaction.id">
                  <td class="whitespace-nowrap">{{ formatDate(transaction.date) }}</td>
                  <td class="whitespace-nowrap">{{ transaction.product }}</td>
                  <td class="whitespace-nowrap text-right">{{ transaction.quantity }}</td>
                  <td class="whitespace-nowrap text-right">{{ formatPrice(transaction.price_ten_thousandths, transaction.price_currency) }}</td>
                  <td class="whitespace-nowrap text-right">{{ formatCurrency(transaction.local_value_min_unit, transaction.local_value_currency) }}</td>
                  <td class="whitespace-nowrap text-right">{{ formatCurrency(transaction.value_min_unit, transaction.value_currency) }}</td>
                  <td class="whitespace-nowrap">{{ transaction.order_id || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="flex flex-col items-center gap-4 mt-6">
            <div class="flex items-center gap-2">
              <label class="label">
                <span class="label-text">{{ $t('degiroTransactionsList.pagination.itemsPerPage') }}:</span>
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
                {{ $t('degiroTransactionsList.pagination.page', { current: currentPage, total: lastPage }) }}
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

    <!-- Tooltip Modal -->
    <dialog ref="tooltipModal" class="modal">
      <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">{{ tooltipTitle }}</h3>
        <p>{{ tooltipDescription }}</p>
        <div class="modal-action">
          <button @click="closeTooltip" class="btn">{{ $t('degiroTransactionsList.modal.close') }}</button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop">
        <button @click="closeTooltip">close</button>
      </form>
    </dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { authService, DegiroTransaction } from '../services/authService'

const { t } = useI18n()

const isLoading = ref(true)
const error = ref<string | null>(null)
const transactions = ref<DegiroTransaction[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const perPage = ref(10)
const tooltipModal = ref<HTMLDialogElement | null>(null)
const tooltipTitle = ref('')
const tooltipDescription = ref('')

const formatDate = (dateString: string): string => {
  // Date is in DD-MM-YYYY format
  return dateString
}

const formatCurrency = (amountInCents: number, currency: string): string => {
  const amount = amountInCents / 100
  const formatted = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount)
  return `${formatted} ${currency}`
}

const formatPrice = (amountInTenThousandths: number, currency: string): string => {
  const amount = amountInTenThousandths / 10000
  const formatted = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
    minimumFractionDigits: 4,
    maximumFractionDigits: 4
  }).format(amount)
  return `${formatted} ${currency}`
}

const showTooltip = (type: 'price' | 'localValue' | 'value') => {
  tooltipTitle.value = t(`degiroTransactionsList.tooltips.${type}.title`)
  tooltipDescription.value = t(`degiroTransactionsList.tooltips.${type}.description`)
  tooltipModal.value?.showModal()
}

const closeTooltip = () => {
  tooltipModal.value?.close()
}

const loadPage = async (page: number) => {
  if (page < 1) return
  
  isLoading.value = true
  error.value = null
  
  try {
    const response = await authService.getDegiroTransactions(perPage.value, page)
    transactions.value = response.data
    currentPage.value = response.current_page
    lastPage.value = response.last_page
  } catch (err: any) {
    console.error('Failed to load transactions:', err)
    error.value = err.response?.data?.message || 'Failed to load transactions'
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

