<template>
  <div class="container mx-auto p-8 max-w-2xl">
    <h1 class="text-4xl font-bold mb-2">{{ $t('uploadAccountStatements.title') }}</h1>
    <p class="text-base-content/70 mb-8">{{ $t('uploadAccountStatements.description') }}</p>

    <div class="card bg-base-100 shadow-xl">
      <div class="card-body">
        <div v-if="success" class="text-center space-y-6">
          <div class="alert alert-success text-left">
            <div>
              <h3 class="font-bold text-lg mb-2">{{ $t('uploadAccountStatements.success.title') }}</h3>
              <p>{{ $t('uploadAccountStatements.success.newCount', { count: newCount }) }}</p>
              <p v-if="ignoredCount > 0" class="mt-1">
                {{ $t('uploadAccountStatements.success.ignoredCount', { count: ignoredCount }) }}
              </p>
            </div>
          </div>
          <button type="button" class="btn btn-primary" @click="goToDashboard">
            {{ $t('uploadAccountStatements.success.goToDashboard') }}
          </button>
        </div>

        <div v-else class="space-y-4">
          <div v-if="error" class="alert alert-error">
            <div class="w-full">
              <h3 class="font-bold">{{ $t('uploadAccountStatements.error.title') }}</h3>
              <p>{{ error }}</p>
              <div
                v-if="errorDetails.length > 0"
                class="mt-3 max-h-80 overflow-y-auto rounded-lg bg-base-100/40 p-3 text-sm"
              >
                <p class="font-medium mb-2">
                  {{ $t('uploadAccountStatements.error.details', { count: errorDetails.length }) }}
                </p>
                <ul class="list-disc list-inside space-y-1">
                  <li v-for="(detail, idx) in errorDetails" :key="idx">{{ detail }}</li>
                </ul>
              </div>
            </div>
          </div>

          <input
            type="file"
            accept=".csv,text/csv"
            class="file-input file-input-bordered w-full"
            :disabled="isUploading"
            @change="onFileChange"
          />

          <button
            type="button"
            class="btn btn-primary"
            :disabled="!selectedFile || isUploading"
            @click="upload"
          >
            <span v-if="isUploading" class="loading loading-spinner loading-sm" />
            <span>{{ isUploading ? $t('uploadAccountStatements.uploading') : $t('uploadAccountStatements.upload') }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { authService } from '../services/authService'

const router = useRouter()
const selectedFile = ref<File | null>(null)
const isUploading = ref(false)
const success = ref(false)
const newCount = ref(0)
const ignoredCount = ref(0)
const error = ref<string | null>(null)
const errorDetails = ref<string[]>([])

const onFileChange = (event: Event) => {
  const input = event.target as HTMLInputElement
  selectedFile.value = input.files?.[0] ?? null
  error.value = null
  errorDetails.value = []
  success.value = false
}

const upload = async () => {
  if (!selectedFile.value) {
    return
  }
  isUploading.value = true
  error.value = null
  errorDetails.value = []
  try {
    const response = await authService.uploadAccountStatements(selectedFile.value)
    newCount.value = response.new_count
    ignoredCount.value = response.ignored_count
    success.value = true
  } catch (err: unknown) {
    const anyErr = err as { response?: { data?: { message?: string; errors?: string[] } } }
    error.value = anyErr.response?.data?.message || 'Upload failed'
    errorDetails.value = anyErr.response?.data?.errors ?? []
  } finally {
    isUploading.value = false
  }
}

const goToDashboard = async () => {
  await router.push({ name: 'dashboard' })
}
</script>
