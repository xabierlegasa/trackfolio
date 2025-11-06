<template>
  <div class="container mx-auto p-8">
    <h1 class="text-4xl font-bold mb-8">{{ $t('tradeSummary.title') }}</h1>
    
    <div v-if="isLoading" class="flex justify-center">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <div v-else-if="error" class="alert alert-error">
      <span>{{ error }}</span>
    </div>

    <div v-else class="space-y-6">
      <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <div class="space-y-4">
            <div class="flex justify-between items-center">
              <span class="text-lg font-semibold">{{ $t('tradeSummary.positiveSum') }}:</span>
              <span class="text-lg text-success font-bold">
                {{ formatCurrency(summary.positive_sum, summary.currency) }}
              </span>
            </div>
            
            <div class="divider"></div>
            
            <div class="flex justify-between items-center">
              <span class="text-lg font-semibold">{{ $t('tradeSummary.negativeSum') }}:</span>
              <span class="text-lg text-error font-bold">
                -{{ formatCurrency(summary.negative_sum, summary.currency) }}
              </span>
            </div>
            
            <div class="divider"></div>
            
            <div class="flex justify-between items-center">
              <span class="text-lg font-semibold">{{ $t('tradeSummary.difference') }}:</span>
              <span 
                class="text-lg font-bold"
                :class="getDifferenceClass(summary.difference)"
              >
                {{ formatCurrency(summary.difference, summary.currency) }}
              </span>
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
import { authService, TradesSummary } from '../services/authService'

const { t } = useI18n()

const isLoading = ref<boolean>(true)
const error = ref<string | null>(null)
const summary = ref<TradesSummary>({
  positive_sum: 0,
  negative_sum: 0,
  difference: 0,
  currency: 'EUR'
})

const formatCurrency = (valueMinUnit: number, currency: string): string => {
  const value = valueMinUnit / 100
  const roundedValue = Math.round(value)
  const formattedValue = new Intl.NumberFormat('en-US').format(roundedValue)
  return `${formattedValue} ${currency}`
}

const getDifferenceClass = (difference: number): string => {
  if (difference > 0) {
    return 'text-success'
  } else if (difference < 0) {
    return 'text-error'
  }
  return ''
}

const loadSummary = async () => {
  isLoading.value = true
  error.value = null
  
  try {
    const response = await authService.getTradesSummary()
    summary.value = response
  } catch (err: any) {
    console.error('Failed to load trades summary:', err)
    error.value = err.response?.data?.message || 'Failed to load trades summary'
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  await loadSummary()
})
</script>

