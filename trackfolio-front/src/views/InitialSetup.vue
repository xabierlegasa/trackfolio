<template>
  <div class="container mx-auto p-8 max-w-2xl">
    <h1 class="text-4xl font-bold mb-2">{{ $t('initialSetup.title') }}</h1>
    <p class="text-base-content/70 mb-8">{{ $t('initialSetup.description') }}</p>

    <div class="card bg-base-100 shadow-xl">
      <div class="card-body">
        <div v-if="success" class="text-center space-y-6">
          <div class="alert alert-success text-left">
            <div>
              <h3 class="font-bold text-lg mb-2">{{ $t('initialSetup.success.title') }}</h3>
              <p>
                {{ $t('initialSetup.success.transactions', { count: formatInteger(transactionsNewCount) }) }}
              </p>
              <p class="mt-1">
                {{ $t('initialSetup.success.statements', { count: formatInteger(statementsNewCount) }) }}
              </p>
            </div>
          </div>
          <button type="button" class="btn btn-primary" @click="goToDashboard">
            {{ $t('initialSetup.success.goToDashboard') }}
          </button>
        </div>

        <form v-else class="space-y-6" @submit.prevent="handleUpload">
          <div v-if="error" class="alert alert-error">
            <div class="w-full">
              <h3 class="font-bold">{{ $t('initialSetup.error.title') }}</h3>
              <p>{{ error }}</p>
              <ul
                v-if="validationErrors.length > 0"
                class="list-disc list-inside mt-2 text-sm space-y-1"
              >
                <li v-for="(item, index) in validationErrors" :key="index">{{ item }}</li>
              </ul>
            </div>
          </div>

          <div class="form-control">
            <label class="label">
              <span class="label-text font-semibold">
                {{ $t('initialSetup.transactionsFile') }}
                <span class="text-error">*</span>
              </span>
            </label>
            <input
              ref="transactionsInput"
              type="file"
              accept=".csv,.txt,text/csv"
              class="file-input file-input-bordered w-full"
              :disabled="isUploading"
              required
              @change="onTransactionsChange"
            />
            <label v-if="transactionsFile" class="label">
              <span class="label-text-alt text-success">{{ transactionsFile.name }}</span>
            </label>
          </div>

          <div class="form-control">
            <label class="label">
              <span class="label-text font-semibold">
                {{ $t('initialSetup.statementsFile') }}
                <span class="text-error">*</span>
              </span>
            </label>
            <input
              ref="statementsInput"
              type="file"
              accept=".csv,text/csv"
              class="file-input file-input-bordered w-full"
              :disabled="isUploading"
              required
              @change="onStatementsChange"
            />
            <label v-if="statementsFile" class="label">
              <span class="label-text-alt text-success">{{ statementsFile.name }}</span>
            </label>
          </div>

          <button
            type="submit"
            class="btn btn-primary w-full"
            :disabled="!canSubmit || isUploading"
          >
            <span v-if="isUploading" class="loading loading-spinner loading-sm" />
            <span>
              {{ isUploading ? $t('initialSetup.uploading') : $t('initialSetup.upload') }}
            </span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { authService } from '../services/authService'
import { formatInteger } from '../utils/numberFormat'

const router = useRouter()
const { t } = useI18n()

const transactionsInput = ref<HTMLInputElement | null>(null)
const statementsInput = ref<HTMLInputElement | null>(null)
const transactionsFile = ref<File | null>(null)
const statementsFile = ref<File | null>(null)
const isUploading = ref(false)
const success = ref(false)
const error = ref<string | null>(null)
const validationErrors = ref<string[]>([])
const transactionsNewCount = ref(0)
const statementsNewCount = ref(0)

const canSubmit = computed(() => Boolean(transactionsFile.value && statementsFile.value))

const onTransactionsChange = (event: Event) => {
  const input = event.target as HTMLInputElement
  transactionsFile.value = input.files?.[0] ?? null
  error.value = null
  validationErrors.value = []
  success.value = false
}

const onStatementsChange = (event: Event) => {
  const input = event.target as HTMLInputElement
  statementsFile.value = input.files?.[0] ?? null
  error.value = null
  validationErrors.value = []
  success.value = false
}

const handleUpload = async () => {
  if (!transactionsFile.value || !statementsFile.value) {
    error.value = t('initialSetup.error.bothRequired')
    return
  }

  isUploading.value = true
  error.value = null
  validationErrors.value = []

  try {
    const transactionsResponse = await authService.uploadDegiroTransactions(transactionsFile.value)
    transactionsNewCount.value = transactionsResponse.new_count || 0

    try {
      const statementsResponse = await authService.uploadAccountStatements(statementsFile.value)
      statementsNewCount.value = statementsResponse.new_count || 0
    } catch (statementsErr: unknown) {
      const anyErr = statementsErr as { response?: { data?: { message?: string; errors?: string[] } } }
      error.value = anyErr.response?.data?.message || t('initialSetup.error.statementsFailed')
      validationErrors.value = anyErr.response?.data?.errors ?? []
      return
    }

    success.value = true
    transactionsFile.value = null
    statementsFile.value = null
    if (transactionsInput.value) {
      transactionsInput.value.value = ''
    }
    if (statementsInput.value) {
      statementsInput.value.value = ''
    }
  } catch (err: unknown) {
    const anyErr = err as { response?: { status?: number; data?: { message?: string; errors?: string[] } } }
    error.value = anyErr.response?.data?.message || t('initialSetup.error.transactionsFailed')
    validationErrors.value = anyErr.response?.data?.errors ?? []
  } finally {
    isUploading.value = false
  }
}

const goToDashboard = async () => {
  await router.push({ name: 'dashboard' })
}
</script>
