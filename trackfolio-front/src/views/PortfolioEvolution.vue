<template>
  <div>
    <div v-if="isLoading" class="flex justify-center py-12">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <div v-else-if="error" class="alert alert-error">
      <span>{{ error }}</span>
    </div>

    <div v-else class="card bg-base-100 shadow-xl">
      <div class="card-body">
        <h2 class="text-xl font-bold text-base-content">
          {{ $t('portfolioEvolution.title') }}
        </h2>
        <p class="text-sm text-base-content/60 mt-1">
          {{ $t('portfolioEvolution.subtitle', { months: months }) }}
        </p>

        <div v-if="points.length === 0" class="py-10 text-base-content/60">
          {{ $t('portfolioEvolution.empty') }}
        </div>

        <div v-else class="mt-4">
          <div class="w-full overflow-x-auto">
            <svg
              class="w-full min-w-[320px] h-72"
              :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
              role="img"
              :aria-label="$t('portfolioEvolution.title')"
            >
              <rect
                x="0"
                y="0"
                :width="chartWidth"
                :height="chartHeight"
                class="fill-base-200/40"
                rx="12"
              />

              <g v-for="(gridY, idx) in gridYs" :key="`g-${idx}`">
                <line
                  :x1="padding.left"
                  :x2="chartWidth - padding.right"
                  :y1="gridY"
                  :y2="gridY"
                  class="stroke-base-300"
                  stroke-width="1"
                />
                <text
                  :x="padding.left - 8"
                  :y="gridY + 4"
                  text-anchor="end"
                  class="fill-base-content/50"
                  font-size="11"
                >
                  {{ formatAxisEuro(gridValues[idx]) }}
                </text>
              </g>

              <polyline
                fill="none"
                class="stroke-primary"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                :points="linePoints"
              />

              <circle
                v-for="(pt, idx) in plotted"
                :key="`c-${idx}`"
                :cx="pt.x"
                :cy="pt.y"
                r="3.5"
                class="fill-primary"
              >
                <title>{{ pt.label }}</title>
              </circle>

              <text
                v-for="(label, idx) in xLabels"
                :key="`x-${idx}`"
                :x="label.x"
                :y="chartHeight - 12"
                text-anchor="middle"
                class="fill-base-content/50"
                font-size="11"
              >
                {{ label.text }}
              </text>
            </svg>
          </div>

          <div class="mt-4 overflow-x-auto">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>{{ $t('portfolioEvolution.table.date') }}</th>
                  <th class="text-right">{{ $t('portfolioEvolution.table.balance') }}</th>
                  <th class="text-right">{{ $t('portfolioEvolution.table.portfolio') }}</th>
                  <th class="text-right">{{ $t('portfolioEvolution.table.leverage') }}</th>
                  <th class="text-right">{{ $t('portfolioEvolution.table.day') }}</th>
                  <th class="text-right">{{ $t('portfolioEvolution.table.totalPl') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in [...points].reverse()" :key="row.snapshot_date">
                  <td>{{ formatDate(row.snapshot_date) }}</td>
                  <td class="text-right tabular-nums">{{ formatEuro(row.balance_eur_min_unit) }}</td>
                  <td class="text-right tabular-nums text-base-content/60">
                    {{ formatEuro(row.portfolio_eur_min_unit) }}
                  </td>
                  <td class="text-right tabular-nums text-base-content/60">
                    {{ formatEuro(row.leverage_eur_min_unit) }}
                  </td>
                  <td
                    class="text-right tabular-nums"
                    :class="signedClass(row.day_change_eur_min_unit)"
                  >
                    {{ formatSignedEuro(row.day_change_eur_min_unit) }}
                  </td>
                  <td
                    class="text-right tabular-nums"
                    :class="signedClass(row.total_gain_loss_eur_min_unit)"
                  >
                    {{ formatSignedEuro(row.total_gain_loss_eur_min_unit) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { authService, PortfolioDailySnapshot } from '../services/authService'
import { formatPricePrefixFromCents, formatSignedCurrencyFromCents } from '../utils/numberFormat'

withDefaults(defineProps<{ embedded?: boolean }>(), {
  embedded: false,
})

const { locale } = useI18n()

const months = 3
const isLoading = ref(true)
const error = ref<string | null>(null)
const points = ref<PortfolioDailySnapshot[]>([])

const chartWidth = 720
const chartHeight = 280
const padding = { top: 24, right: 24, bottom: 40, left: 72 }

const formatEuro = (cents: number): string => formatPricePrefixFromCents(cents, 'EUR', 0)

const formatSignedEuro = (cents: number | null): string => {
  if (cents == null) {
    return '—'
  }
  return formatSignedCurrencyFromCents(cents, 'EUR', 0)
}

const signedClass = (cents: number | null): string => {
  if (cents == null || cents === 0) {
    return ''
  }
  return cents > 0 ? 'text-success' : 'text-error'
}

const formatDate = (iso: string): string => {
  const date = new Date(`${iso}T12:00:00`)
  if (Number.isNaN(date.getTime())) {
    return iso
  }
  const displayLocale = locale.value === 'es' ? 'es-ES' : 'en-GB'
  return new Intl.DateTimeFormat(displayLocale, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

const formatAxisEuro = (cents: number): string => {
  const euros = Math.round(cents / 100)
  return new Intl.NumberFormat(locale.value === 'es' ? 'es-ES' : 'en-US', {
    notation: 'compact',
    maximumFractionDigits: 1,
  }).format(euros)
}

const balances = computed(() => points.value.map((p) => p.balance_eur_min_unit))

const yDomain = computed(() => {
  if (balances.value.length === 0) {
    return { min: 0, max: 1 }
  }
  let min = Math.min(...balances.value)
  let max = Math.max(...balances.value)
  if (min === max) {
    min -= 10000
    max += 10000
  }
  const pad = (max - min) * 0.08
  return { min: min - pad, max: max + pad }
})

const gridValues = computed(() => {
  const { min, max } = yDomain.value
  const steps = 4
  const values: number[] = []
  for (let i = 0; i <= steps; i++) {
    values.push(min + ((max - min) * i) / steps)
  }
  return values
})

const gridYs = computed(() =>
  gridValues.value.map((value) => {
    const { min, max } = yDomain.value
    const ratio = (value - min) / (max - min || 1)
    return chartHeight - padding.bottom - ratio * (chartHeight - padding.top - padding.bottom)
  }),
)

const plotted = computed(() => {
  const n = points.value.length
  if (n === 0) {
    return [] as Array<{ x: number; y: number; label: string }>
  }
  const { min, max } = yDomain.value
  const innerW = chartWidth - padding.left - padding.right
  const innerH = chartHeight - padding.top - padding.bottom

  return points.value.map((point, index) => {
    const x = n === 1
      ? padding.left + innerW / 2
      : padding.left + (innerW * index) / (n - 1)
    const ratio = (point.balance_eur_min_unit - min) / (max - min || 1)
    const y = chartHeight - padding.bottom - ratio * innerH
    return {
      x,
      y,
      label: `${formatDate(point.snapshot_date)}: ${formatEuro(point.balance_eur_min_unit)}`,
    }
  })
})

const linePoints = computed(() =>
  plotted.value.map((p) => `${p.x},${p.y}`).join(' '),
)

const xLabels = computed(() => {
  const n = plotted.value.length
  if (n === 0) {
    return [] as Array<{ x: number; text: string }>
  }
  const indexes = n <= 4
    ? [...Array(n).keys()]
    : [0, Math.floor((n - 1) / 3), Math.floor((2 * (n - 1)) / 3), n - 1]
  const unique = [...new Set(indexes)]
  return unique.map((i) => ({
    x: plotted.value[i].x,
    text: formatDate(points.value[i].snapshot_date),
  }))
})

const load = async () => {
  isLoading.value = true
  error.value = null
  try {
    // Ensure today's photo exists (portfolio-stats upserts if missing).
    await authService.getPortfolioStats(1, 1)
    const response = await authService.getPortfolioEvolution(months)
    points.value = response.data
  } catch (err: unknown) {
    console.error('Failed to load portfolio evolution:', err)
    const anyErr = err as { response?: { data?: { message?: string } } }
    error.value = anyErr.response?.data?.message || 'Failed to load portfolio evolution'
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  await load()
})
</script>
