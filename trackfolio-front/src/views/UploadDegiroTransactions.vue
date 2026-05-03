<template>
  <div class="container mx-auto p-8">
    <h1 class="text-4xl font-bold mb-8">{{ $t('uploadDegiroTransactions.title') }}</h1>

    <div class="card bg-base-100 shadow-xl mb-6">
      <div class="card-body">
        <h2 class="card-title text-lg">{{ $t('uploadDegiroTransactions.recent.title') }}</h2>
        <div v-if="recentLoading" class="flex justify-center py-6">
          <span class="loading loading-spinner loading-md"></span>
        </div>
        <div v-else-if="recentError" class="alert alert-warning">
          <span>{{ recentError }}</span>
        </div>
        <p v-else-if="recentTransactions.length === 0" class="text-base-content/70">
          {{ $t('uploadDegiroTransactions.recent.empty') }}
        </p>
        <div v-else class="overflow-x-auto -mx-2">
          <table class="table table-zebra table-sm min-w-full">
            <thead>
              <tr>
                <th class="whitespace-nowrap">{{ $t('degiroTransactionsList.table.date') }}</th>
                <th class="whitespace-nowrap">{{ $t('degiroTransactionsList.table.time') }}</th>
                <th class="min-w-[140px]">{{ $t('degiroTransactionsList.table.product') }}</th>
                <th class="text-right whitespace-nowrap">{{ $t('degiroTransactionsList.table.quantity') }}</th>
                <th class="text-right whitespace-nowrap">{{ $t('degiroTransactionsList.table.price') }}</th>
                <th class="text-right whitespace-nowrap">{{ $t('degiroTransactionsList.table.localValue') }}</th>
                <th class="text-right whitespace-nowrap">{{ $t('degiroTransactionsList.table.value') }}</th>
                <th class="whitespace-nowrap">{{ $t('degiroTransactionsList.table.orderId') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="tx in recentTransactions" :key="tx.id">
                <td class="whitespace-nowrap">{{ tx.date }}</td>
                <td class="whitespace-nowrap">{{ tx.time }}</td>
                <td class="whitespace-nowrap">{{ tx.product }}</td>
                <td class="whitespace-nowrap text-right">{{ formatQuantity(tx.quantity) }}</td>
                <td class="whitespace-nowrap text-right">{{ formatPrice(tx.price_ten_thousandths, tx.price_currency) }}</td>
                <td class="whitespace-nowrap text-right">{{ formatCurrency(tx.local_value_min_unit, tx.local_value_currency) }}</td>
                <td class="whitespace-nowrap text-right">{{ formatCurrency(tx.value_min_unit, tx.value_currency) }}</td>
                <td class="whitespace-nowrap">{{ tx.order_id || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card bg-base-100 shadow-xl">
      <div class="card-body">
        <!-- Success State -->
        <div v-if="success" class="text-center space-y-6">
          <div class="alert alert-success">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="text-left">
              <h3 class="font-bold text-lg mb-2">{{ $t('uploadDegiroTransactions.success.title') }}</h3>
              <div class="text-sm space-y-2">
                <div class="flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-success shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span>{{ $t('uploadDegiroTransactions.success.newCount', { count: newCount }) }}</span>
                </div>
                <div v-if="ignoredCount > 0" class="flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-success shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span>{{ $t('uploadDegiroTransactions.success.ignoredCount', { count: ignoredCount }) }}</span>
                </div>
              </div>
            </div>
          </div>
          <button
            @click="goToDashboard"
            class="btn btn-primary"
          >
            {{ $t('uploadDegiroTransactions.success.goToDashboard') }}
          </button>
        </div>

        <!-- Upload Form -->
        <div v-else>
          <p class="text-base-content/70 mb-6">{{ $t('uploadDegiroTransactions.description') }}</p>
          
          <form @submit.prevent="handleUpload" class="space-y-4">
            <div class="form-control">
              <label class="label">
                <span class="label-text font-semibold">{{ $t('uploadDegiroTransactions.selectFile') }}</span>
              </label>
              <input
                ref="fileInput"
                type="file"
                accept=".csv,.txt"
                @change="handleFileSelect"
                class="file-input file-input-bordered w-full"
                :disabled="isUploading"
              />
              <label v-if="selectedFile" class="label mt-2">
                <span class="label-text-alt text-success">{{ $t('uploadDegiroTransactions.fileSelected') }}: {{ selectedFile.name }}</span>
              </label>
            </div>

            <div v-if="error" class="alert alert-error">
              <span>{{ error }}</span>
            </div>

            <div v-if="validationErrors.length > 0" class="alert alert-warning">
              <div>
                <h3 class="font-bold">{{ $t('uploadDegiroTransactions.errors.validationErrors') }}</h3>
                <ul class="list-disc list-inside mt-2">
                  <li v-for="(validationError, index) in validationErrors" :key="index">
                    {{ validationError }}
                  </li>
                </ul>
              </div>
            </div>

            <div class="form-control mt-6">
              <button
                type="submit"
                class="btn btn-primary"
                :disabled="!selectedFile || isUploading"
              >
                <span v-if="isUploading" class="loading loading-spinner loading-sm"></span>
                {{ isUploading ? $t('uploadDegiroTransactions.uploading') : $t('uploadDegiroTransactions.upload') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { authService, DegiroTransaction, UploadDegiroTransactionsErrorResponse } from '../services/authService'

const router = useRouter()
const { t } = useI18n()

const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const isUploading = ref(false)
const error = ref<string | null>(null)
const validationErrors = ref<string[]>([])
const success = ref(false)
const transactionCount = ref(0)
const newCount = ref(0)
const ignoredCount = ref(0)
const recentTransactions = ref<DegiroTransaction[]>([])
const recentLoading = ref(true)
const recentError = ref<string | null>(null)

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

const loadRecentTransactions = async () => {
  recentLoading.value = true
  recentError.value = null
  try {
    const response = await authService.getDegiroTransactions(5, 1, 'desc')
    recentTransactions.value = response.data
  } catch (err: unknown) {
    console.error('Failed to load recent transactions:', err)
    recentError.value = t('uploadDegiroTransactions.recent.loadFailed')
    recentTransactions.value = []
  } finally {
    recentLoading.value = false
  }
}

const handleFileSelect = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  
  if (file) {
    selectedFile.value = file
    error.value = null
    validationErrors.value = []
    success.value = false
    newCount.value = 0
    ignoredCount.value = 0
    transactionCount.value = 0
  }
}

const goToDashboard = () => {
  router.push('/dashboard')
}

const handleUpload = async () => {
  if (!selectedFile.value) {
    error.value = t('uploadDegiroTransactions.errors.noFile')
    return
  }

  // Validate file type
  const validExtensions = ['.csv', '.txt']
  const fileExtension = selectedFile.value.name.toLowerCase().substring(
    selectedFile.value.name.lastIndexOf('.')
  )
  
  if (!validExtensions.includes(fileExtension)) {
    error.value = t('uploadDegiroTransactions.errors.invalidFile')
    return
  }

  isUploading.value = true
  error.value = null
  validationErrors.value = []
  success.value = false

  try {
    const response = await authService.uploadDegiroTransactions(selectedFile.value)

    success.value = true
    transactionCount.value = response.count || 0
    newCount.value = response.new_count || 0
    ignoredCount.value = response.ignored_count || 0

    await loadRecentTransactions()

    // Reset form after successful upload
    selectedFile.value = null
    if (fileInput.value) {
      fileInput.value.value = ''
    }
  } catch (err: any) {
    console.error('Upload error:', err)
    
    if (err.response?.status === 422) {
      // Validation errors from the API
      const errorData = err.response.data as UploadDegiroTransactionsErrorResponse
      error.value = errorData.message || t('uploadDegiroTransactions.errors.uploadFailed')
      
      // Display validation errors if available
      if (errorData.errors && errorData.errors.length > 0) {
        validationErrors.value = errorData.errors
      }
    } else {
      // Other errors
      error.value = err.response?.data?.message || t('uploadDegiroTransactions.errors.uploadFailed')
      validationErrors.value = []
    }
    
    success.value = false
  } finally {
    isUploading.value = false
  }
}

onMounted(async () => {
  await loadRecentTransactions()
})
</script>

