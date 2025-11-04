<template>
  <div class="container mx-auto p-8">
    <h1 class="text-4xl font-bold mb-8">{{ $t('uploadDegiroTransactions.title') }}</h1>
    
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
import { authService, UploadDegiroTransactionsErrorResponse } from '../services/authService'

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
  // Ensure user is authenticated
  try {
    // The route should be protected, but we can verify here if needed
  } catch (err) {
    router.push('/login')
  }
})
</script>

