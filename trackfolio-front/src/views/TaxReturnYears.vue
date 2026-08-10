<template>
  <div class="container mx-auto p-8">
    <h1 class="text-4xl font-bold mb-6">{{ $t('taxReturnYears.title') }}</h1>

    <RouterLink :to="{ name: 'dashboard' }" class="btn btn-ghost btn-sm mb-6">
      {{ $t('taxReturnYears.backDashboard') }}
    </RouterLink>

    <div v-if="isLoading" class="flex justify-center">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <div v-else-if="error" class="alert alert-error">
      <span>{{ error }}</span>
    </div>

    <div v-else-if="years.length === 0" class="card bg-base-100 shadow-xl">
      <div class="card-body">
        <p class="text-base-content/70">{{ $t('taxReturnYears.empty') }}</p>
      </div>
    </div>

    <div v-else>
      <div class="card bg-base-100 shadow-xl max-w-2xl">
        <div class="card-body">
          <h2 class="card-title mb-4">{{ $t('taxReturnYears.evolutionTitle') }}</h2>
          <div class="overflow-x-auto">
            <table class="table table-zebra">
              <thead>
                <tr>
                  <th>{{ $t('taxReturnYears.evolutionExercise') }}</th>
                  <th class="text-right">{{ $t('taxReturnYears.evolutionResult') }}</th>
                  <th>{{ $t('taxReturnYears.evolutionActions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in evolution" :key="row.year">
                  <td>
                    <RouterLink
                      :to="{ name: 'tax-return-year', params: { year: String(row.year) } }"
                      class="link link-hover font-medium"
                    >
                      {{ $t('taxReturnYears.exercise', { year: row.year }) }}
                    </RouterLink>
                  </td>
                  <td
                    class="text-right whitespace-nowrap font-medium"
                    :class="resultCellClass(row)"
                  >
                    <span v-if="row.total_net_gain_cents === null || row.total_net_gain_cents === undefined">
                      {{ $t('taxReturnYears.evolutionDash') }}
                    </span>
                    <span v-else>{{ formatSignedEuro(row.total_net_gain_cents) }}</span>
                  </td>
                  <td class="whitespace-nowrap">
                    <RouterLink
                      :to="{ name: 'tax-return-year', params: { year: String(row.year) } }"
                      class="link link-hover"
                    >
                      {{ $t('taxReturnYears.viewExercise') }}
                    </RouterLink>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="font-semibold bg-base-200">
                  <td>{{ $t('taxReturnYears.evolutionTotal') }}</td>
                  <td class="text-right whitespace-nowrap" :class="netGainClass(evolutionGrandTotalCents)">
                    {{ formatSignedEuro(evolutionGrandTotalCents) }}
                  </td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
          <p v-if="evolutionHasIncomplete" class="text-sm text-base-content/70 mt-3">
            {{ $t('taxReturnYears.evolutionIncomplete') }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { authService, type TaxReturnYearEvolutionRow } from '../services/authService'
import { formatSignedEuroFromCents } from '../utils/numberFormat'

const isLoading = ref(true)
const error = ref<string | null>(null)
const years = ref<number[]>([])
const evolution = ref<TaxReturnYearEvolutionRow[]>([])

const evolutionHasIncomplete = computed(() =>
  evolution.value.some((r) => r.fifo_incomplete === true || r.total_net_gain_cents === null)
)

const evolutionGrandTotalCents = computed(() => {
  let sum = 0
  for (const r of evolution.value) {
    if (typeof r.total_net_gain_cents === 'number') {
      sum += r.total_net_gain_cents
    }
  }
  return sum
})

const formatSignedEuro = formatSignedEuroFromCents

const netGainClass = (cents: number): string => {
  if (cents > 0) return 'text-success'
  if (cents < 0) return 'text-error'
  return ''
}

const resultCellClass = (row: TaxReturnYearEvolutionRow): string => {
  if (typeof row.total_net_gain_cents !== 'number') {
    return 'text-base-content/50'
  }
  return netGainClass(row.total_net_gain_cents)
}

onMounted(async () => {
  isLoading.value = true
  error.value = null
  try {
    const res = await authService.getTaxReturnYears()
    years.value = res.years
    evolution.value = res.evolution ?? []
  } catch (err: unknown) {
    console.error(err)
    error.value =
      (err as { response?: { data?: { message?: string } } })?.response?.data?.message ??
      'Failed to load years'
  } finally {
    isLoading.value = false
  }
})
</script>
