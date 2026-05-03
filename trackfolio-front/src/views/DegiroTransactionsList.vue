<template>
  <div class="container mx-auto p-8">
    <h1 class="text-4xl font-bold mb-8">{{ $t('degiroTransactionsList.title') }}</h1>

    <div v-if="isLoading" class="flex justify-center">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <div v-else-if="error" class="alert alert-error">
      <span>{{ error }}</span>
    </div>

    <div v-else class="space-y-6">
      <div class="card bg-base-100 shadow-xl">
        <div class="card-body flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
          <div class="form-control flex-1 min-w-[200px]">
            <label class="label py-0">
              <span class="label-text font-semibold">{{ $t('degiroTransactionsList.filter.productLabel') }}</span>
            </label>
            <input
              v-model="productFilterDraft"
              type="search"
              class="input input-bordered input-sm w-full"
              :placeholder="$t('degiroTransactionsList.filter.placeholder')"
              @keydown.enter.prevent="applyProductFilter"
            />
          </div>
          <div class="flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary btn-sm" @click="applyProductFilter">
              {{ $t('degiroTransactionsList.filter.apply') }}
            </button>
            <button
              type="button"
              class="btn btn-ghost btn-sm"
              :disabled="!productFilterDraft.trim() && !appliedProductFilter"
              @click="clearProductFilter"
            >
              {{ $t('degiroTransactionsList.filter.clear') }}
            </button>
          </div>
        </div>
      </div>

      <div v-if="transactions.length === 0" class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <p class="text-base-content/70">
            {{ appliedProductFilter ? $t('degiroTransactionsList.noMatches') : $t('degiroTransactionsList.noTransactions') }}
          </p>
        </div>
      </div>

      <div v-else class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <div class="overflow-x-auto">
            <table class="table table-zebra min-w-full">
              <thead>
                <tr>
                  <th class="min-w-[110px]">
                    <button
                      type="button"
                      @click="toggleDateSort"
                      class="btn btn-ghost btn-sm gap-1 -ml-2 h-auto min-h-0 py-1 font-semibold normal-case"
                    >
                      {{ $t('degiroTransactionsList.table.date') }}
                      <span class="text-xs font-normal">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                    </button>
                  </th>
                  <th class="min-w-[170px]">{{ $t('degiroTransactionsList.table.product') }}</th>
                  <th class="min-w-[100px] text-right">{{ $t('degiroTransactionsList.table.quantity') }}</th>
                  <th class="min-w-[130px] text-right">
                    <div class="flex items-center justify-end gap-1">
                      <span>{{ $t('degiroTransactionsList.table.price') }}</span>
                      <button
                        type="button"
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
                        type="button"
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
                        type="button"
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
                  <th class="min-w-[72px] text-right">{{ $t('degiroTransactionsList.table.dbId') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="transaction in transactions" :key="transaction.id">
                  <td class="whitespace-nowrap">{{ formatDate(transaction.date) }}</td>
                  <td class="whitespace-nowrap">{{ transaction.product }}</td>
                  <td class="whitespace-nowrap text-right">{{ formatQuantity(transaction.quantity) }}</td>
                  <td class="whitespace-nowrap text-right">{{ formatPrice(transaction.price_ten_thousandths, transaction.price_currency) }}</td>
                  <td class="whitespace-nowrap text-right">{{ formatCurrency(transaction.local_value_min_unit, transaction.local_value_currency) }}</td>
                  <td class="whitespace-nowrap text-right">{{ formatCurrency(transaction.value_min_unit, transaction.value_currency) }}</td>
                  <td class="whitespace-nowrap">{{ transaction.order_id || '-' }}</td>
                  <td class="whitespace-nowrap text-right tabular-nums">{{ transaction.id }}</td>
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
                type="button"
                @click="loadPage(currentPage - 1)"
                :disabled="currentPage === 1"
                class="join-item btn"
              >
                «
              </button>
              <button type="button" class="join-item btn btn-active">
                {{ $t('degiroTransactionsList.pagination.page', { current: currentPage, total: lastPage }) }}
              </button>
              <button
                type="button"
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
          <button type="button" @click="closeTooltip" class="btn">{{ $t('degiroTransactionsList.modal.close') }}</button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop">
        <button type="button" @click="closeTooltip">close</button>
      </form>
    </dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { authService, DegiroTransaction } from '../services/authService'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const MAX_PRODUCT_QUERY_LEN = 200

const productFromRouteQuery = (): string => {
  const raw = route.query.product
  const s = Array.isArray(raw) ? raw[0] : raw
  return typeof s === 'string' ? s.trim().slice(0, MAX_PRODUCT_QUERY_LEN) : ''
}

const isLoading = ref(true)
const error = ref<string | null>(null)
const transactions = ref<DegiroTransaction[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const perPage = ref(10)
const sortOrder = ref<'asc' | 'desc'>('desc')
const productFilterDraft = ref('')
const appliedProductFilter = ref('')
const tooltipModal = ref<HTMLDialogElement | null>(null)
const tooltipTitle = ref('')
const tooltipDescription = ref('')

const formatDate = (dateString: string): string => {
  // Date is in DD-MM-YYYY format
  return dateString
}

const formatQuantity = (quantity: number): string => {
  return new Intl.NumberFormat('en-US', {
    maximumFractionDigits: 0,
    minimumFractionDigits: 0
  }).format(Math.round(Number(quantity)))
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
    const response = await authService.getDegiroTransactions(
      perPage.value,
      page,
      sortOrder.value,
      appliedProductFilter.value || undefined
    )
    transactions.value = response.data
    currentPage.value = response.current_page
    lastPage.value = response.last_page
  } catch (err: unknown) {
    console.error('Failed to load transactions:', err)
    const anyErr = err as { response?: { data?: { message?: string } } }
    error.value = anyErr.response?.data?.message || 'Failed to load transactions'
  } finally {
    isLoading.value = false
  }
}

const replaceProductQuery = (product: string) => {
  const next = { ...route.query } as Record<string, string | string[]>
  const trimmed = product.trim().slice(0, MAX_PRODUCT_QUERY_LEN)
  if (trimmed) {
    next.product = trimmed
  } else {
    delete next.product
  }
  return router.replace({ path: route.path, query: next })
}

const applyProductFilter = async () => {
  appliedProductFilter.value = productFilterDraft.value.trim().slice(0, MAX_PRODUCT_QUERY_LEN)
  await replaceProductQuery(appliedProductFilter.value)
  await loadPage(1)
}

const clearProductFilter = async () => {
  productFilterDraft.value = ''
  appliedProductFilter.value = ''
  await replaceProductQuery('')
  await loadPage(1)
}

const handlePerPageChange = () => {
  loadPage(1)
}

const toggleDateSort = () => {
  sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  loadPage(1)
}

onMounted(async () => {
  const fromQuery = productFromRouteQuery()
  if (fromQuery) {
    productFilterDraft.value = fromQuery
    appliedProductFilter.value = fromQuery
  }
  await loadPage(1)
})

watch(
  () => route.query.product,
  async () => {
    const fromQuery = productFromRouteQuery()
    if (fromQuery === appliedProductFilter.value) {
      return
    }
    productFilterDraft.value = fromQuery
    appliedProductFilter.value = fromQuery
    await loadPage(1)
  }
)
</script>
