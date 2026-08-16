<template>
  <div>
    <div role="tablist" class="tabs tabs-boxed mb-4 w-full max-w-lg">
      <button
        type="button"
        role="tab"
        class="tab flex-1"
        :class="{ 'tab-active': activeView === 'day' }"
        :aria-selected="activeView === 'day'"
        @click="setView('day')"
      >
        {{ $t('portfolioEvolution.views.day') }}
      </button>
      <button
        type="button"
        role="tab"
        class="tab flex-1"
        :class="{ 'tab-active': activeView === 'month' }"
        :aria-selected="activeView === 'month'"
        @click="setView('month')"
      >
        {{ $t('portfolioEvolution.views.month') }}
      </button>
      <button
        type="button"
        role="tab"
        class="tab flex-1"
        :class="{ 'tab-active': activeView === 'year' }"
        :aria-selected="activeView === 'year'"
        @click="setView('year')"
      >
        {{ $t('portfolioEvolution.views.year') }}
      </button>
    </div>

    <div v-if="isLoading" class="flex justify-center py-12">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <div v-else-if="error" class="alert alert-error">
      <span>{{ error }}</span>
    </div>

    <div v-else class="card bg-base-100 shadow-xl">
      <div class="card-body">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="text-xl font-bold text-base-content">
              {{ $t('portfolioEvolution.title') }}
            </h2>
            <p class="text-sm text-base-content/60 mt-1">
              {{ subtitle }}
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <label v-if="activeView === 'day'" class="form-control w-auto">
              <span class="label py-0 pb-1">
                <span class="label-text text-xs">{{ $t('portfolioEvolution.yearLabel') }}</span>
              </span>
              <select
                v-model.number="selectedYear"
                class="select select-bordered select-sm"
                :disabled="isLoadingChart || isRecalculating || availableYears.length === 0"
              >
                <option v-for="year in availableYears" :key="year" :value="year">
                  {{ year }}
                </option>
              </select>
            </label>
            <button
              type="button"
              class="btn btn-primary btn-sm self-end"
              :disabled="isRecalculating"
              @click="recalculate"
            >
              <span v-if="isRecalculating" class="loading loading-spinner loading-sm" />
              <span>{{ $t('portfolioEvolution.recalculate') }}</span>
            </button>
          </div>
        </div>

        <div v-if="recalculateMessage" class="alert alert-success mt-4">
          <span>{{ recalculateMessage }}</span>
        </div>
        <div v-if="recalculateError" class="alert alert-error mt-4">
          <span>{{ recalculateError }}</span>
        </div>

        <div v-if="!rangeFrom" class="py-10 text-base-content/60">
          {{ $t('portfolioEvolution.empty') }}
        </div>

        <div v-else class="mt-4">
          <div v-if="isLoadingChart" class="flex justify-center py-12">
            <span class="loading loading-spinner loading-md"></span>
          </div>
          <div v-else ref="chartHost" class="w-full">
            <svg
              class="block w-full"
              :height="chartHeight"
              :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
              preserveAspectRatio="xMinYMid meet"
              role="img"
              :aria-label="$t('portfolioEvolution.title')"
            >
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
                v-for="(segment, idx) in lineSegments"
                :key="`s-${idx}`"
                fill="none"
                class="stroke-primary"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                :points="segment"
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

              <template v-if="showPointAmounts">
                <text
                  v-for="(pt, idx) in plotted"
                  :key="`v-${idx}`"
                  :x="pt.x"
                  :y="pt.y - 10"
                  text-anchor="middle"
                  class="fill-base-content/80"
                  font-size="11"
                >
                  {{ pt.amountLabel }}
                </text>
              </template>

              <text
                v-for="(label, idx) in xLabels"
                :key="`x-${idx}`"
                :x="label.x"
                :y="chartHeight - 10"
                :text-anchor="label.anchor"
                class="fill-base-content/50"
                font-size="11"
              >
                {{ label.text }}
              </text>
            </svg>
          </div>

          <p v-if="!isLoadingChart && points.length === 0" class="py-6 text-base-content/60">
            {{ $t('portfolioEvolution.empty') }}
          </p>

          <div v-if="!isLoadingChart && points.length > 0" class="mt-4 overflow-x-auto">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>{{ periodColumnLabel }}</th>
                  <th class="text-right">{{ $t('portfolioEvolution.table.balance') }}</th>
                  <th class="text-right">{{ $t('portfolioEvolution.table.portfolio') }}</th>
                  <th class="text-right">{{ $t('portfolioEvolution.table.cash') }}</th>
                  <th v-if="activeView === 'day'" class="text-right">{{ $t('portfolioEvolution.table.day') }}</th>
                  <th v-if="activeView === 'day'" class="text-right">{{ $t('portfolioEvolution.table.totalPl') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in tableRows" :key="rowKey(row)">
                  <td>
                    <button
                      v-if="row.snapshot_date"
                      type="button"
                      class="link link-hover font-medium text-left"
                      @click="openPortfolioAsOf(row.snapshot_date)"
                    >
                      {{ formatPeriodLabel(row) }}
                    </button>
                    <span v-else>{{ formatPeriodLabel(row) }}</span>
                  </td>
                  <td class="text-right tabular-nums">{{ formatEuro(row.balance_eur_min_unit) }}</td>
                  <td class="text-right tabular-nums text-base-content/60">
                    {{ formatEuro(row.portfolio_eur_min_unit) }}
                  </td>
                  <td class="text-right tabular-nums text-base-content/60">
                    {{ formatEuro(row.cash_eur_min_unit) }}
                  </td>
                  <td
                    v-if="activeView === 'day'"
                    class="text-right tabular-nums"
                    :class="signedClass(row.day_change_eur_min_unit)"
                  >
                    {{ formatSignedEuro(row.day_change_eur_min_unit) }}
                  </td>
                  <td
                    v-if="activeView === 'day'"
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
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { authService, PortfolioDailySnapshot } from '../services/authService'
import { formatPricePrefixFromCents, formatSignedCurrencyFromCents } from '../utils/numberFormat'

type EvolutionView = 'day' | 'month' | 'year'

withDefaults(defineProps<{ embedded?: boolean }>(), {
  embedded: false,
})

const { locale, t } = useI18n()
const route = useRoute()
const router = useRouter()

const VALID_VIEWS: EvolutionView[] = ['day', 'month', 'year']

const parseYearQuery = (value: unknown): number | null => {
  const raw = Array.isArray(value) ? value[0] : value
  if (typeof raw !== 'string' && typeof raw !== 'number') {
    return null
  }
  const year = Number(raw)
  if (!Number.isInteger(year) || year < 1990 || year > 2100) {
    return null
  }
  return year
}

const parseViewQuery = (value: unknown): EvolutionView => {
  const raw = Array.isArray(value) ? value[0] : value
  if (typeof raw === 'string' && VALID_VIEWS.includes(raw as EvolutionView)) {
    return raw as EvolutionView
  }
  return 'day'
}

const activeView = ref<EvolutionView>(parseViewQuery(route.query.view))
const selectedYear = ref(parseYearQuery(route.query.year) ?? new Date().getFullYear())
const availableYears = ref<number[]>([selectedYear.value])
const isLoading = ref(true)
const isLoadingChart = ref(false)
const isRecalculating = ref(false)
const error = ref<string | null>(null)
const recalculateMessage = ref<string | null>(null)
const recalculateError = ref<string | null>(null)
const points = ref<PortfolioDailySnapshot[]>([])
const rangeFrom = ref<string | null>(null)
const rangeTo = ref<string | null>(null)
let suppressYearWatch = false
let suppressViewWatch = false

const syncQueryToUrl = () => {
  const query: Record<string, string | string[]> = {
    ...route.query,
    tab: 'evolution',
  }
  if (activeView.value === 'day') {
    query.year = String(selectedYear.value)
    delete query.view
  } else {
    query.view = activeView.value
    delete query.year
  }
  router.replace({ name: 'statistics', query })
}

const subtitle = computed(() => {
  if (activeView.value === 'month') {
    return t('portfolioEvolution.subtitleMonth')
  }
  if (activeView.value === 'year') {
    return t('portfolioEvolution.subtitleYear')
  }
  return t('portfolioEvolution.subtitle', { year: selectedYear.value })
})

const periodColumnLabel = computed(() => {
  if (activeView.value === 'month') {
    return t('portfolioEvolution.table.month')
  }
  if (activeView.value === 'year') {
    return t('portfolioEvolution.table.year')
  }
  return t('portfolioEvolution.table.date')
})

const tableRows = computed(() => [...points.value].reverse())

const chartHost = ref<HTMLElement | null>(null)
const chartWidth = ref(800)
const chartHeight = 280
const padding = { top: 28, right: 8, bottom: 36, left: 56 }

let chartObserver: ResizeObserver | null = null

watch(chartHost, (el) => {
  chartObserver?.disconnect()
  chartObserver = null
  if (!el) {
    return
  }
  const applyWidth = () => {
    chartWidth.value = Math.max(320, Math.floor(el.clientWidth))
  }
  applyWidth()
  chartObserver = new ResizeObserver(applyWidth)
  chartObserver.observe(el)
})

onBeforeUnmount(() => {
  chartObserver?.disconnect()
})

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

const formatMonthShort = (iso: string): string => {
  const date = new Date(`${iso}T12:00:00`)
  if (Number.isNaN(date.getTime())) {
    return iso
  }
  const displayLocale = locale.value === 'es' ? 'es-ES' : 'en-GB'
  return new Intl.DateTimeFormat(displayLocale, {
    month: 'short',
  }).format(date)
}

const formatPeriodLabel = (row: PortfolioDailySnapshot): string => {
  if (activeView.value === 'day') {
    return formatDate(row.snapshot_date)
  }
  if (activeView.value === 'month') {
    const period = row.period ?? row.snapshot_date.slice(0, 7)
    const date = new Date(`${period}-01T12:00:00`)
    const displayLocale = locale.value === 'es' ? 'es-ES' : 'en-GB'
    return new Intl.DateTimeFormat(displayLocale, { month: 'short', year: 'numeric' }).format(date)
  }
  return row.period ?? row.snapshot_date.slice(0, 4)
}

const rowKey = (row: PortfolioDailySnapshot): string =>
  row.period ?? row.snapshot_date

const toIso = (date: Date): string => {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

const axisKeys = computed((): string[] => {
  if (!rangeFrom.value || !rangeTo.value) {
    return []
  }
  if (activeView.value === 'day') {
    const days: string[] = []
    const cursor = new Date(`${rangeFrom.value}T12:00:00`)
    const end = new Date(`${rangeTo.value}T12:00:00`)
    if (Number.isNaN(cursor.getTime()) || Number.isNaN(end.getTime()) || cursor > end) {
      return []
    }
    while (cursor <= end) {
      days.push(toIso(cursor))
      cursor.setDate(cursor.getDate() + 1)
    }
    return days
  }
  if (activeView.value === 'month') {
    const months: string[] = []
    const cursor = new Date(`${rangeFrom.value.slice(0, 7)}-01T12:00:00`)
    const end = new Date(`${rangeTo.value.slice(0, 7)}-01T12:00:00`)
    if (Number.isNaN(cursor.getTime()) || Number.isNaN(end.getTime()) || cursor > end) {
      return []
    }
    while (cursor <= end) {
      const y = cursor.getFullYear()
      const m = String(cursor.getMonth() + 1).padStart(2, '0')
      months.push(`${y}-${m}`)
      cursor.setMonth(cursor.getMonth() + 1)
    }
    return months
  }
  const years: string[] = []
  const fromDataYears = points.value.map((p) =>
    Number(p.period ?? p.snapshot_date.slice(0, 4)),
  )
  const startY = Math.min(
    Number(rangeFrom.value.slice(0, 4)),
    ...(fromDataYears.length > 0 ? fromDataYears : [Number(rangeFrom.value.slice(0, 4))]),
  )
  const endY = Math.max(
    Number(rangeTo.value.slice(0, 4)),
    ...(fromDataYears.length > 0 ? fromDataYears : [Number(rangeTo.value.slice(0, 4))]),
  )
  for (let y = startY; y <= endY; y++) {
    years.push(String(y))
  }
  return years
})

const pointsByKey = computed(() => {
  const map = new Map<string, PortfolioDailySnapshot>()
  for (const point of points.value) {
    if (activeView.value === 'day') {
      map.set(point.snapshot_date, point)
    } else {
      const key = point.period ?? (activeView.value === 'month'
        ? point.snapshot_date.slice(0, 7)
        : point.snapshot_date.slice(0, 4))
      map.set(key, point)
    }
  }
  return map
})

const innerW = computed(() => Math.max(1, chartWidth.value - padding.left - padding.right))
const innerH = computed(() => chartHeight - padding.top - padding.bottom)

const xAt = (index: number, count: number): number => {
  if (count <= 1) {
    return padding.left + innerW.value / 2
  }
  return padding.left + (innerW.value * index) / (count - 1)
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
    return chartHeight - padding.bottom - ratio * innerH.value
  }),
)

const plotted = computed(() => {
  const keys = axisKeys.value
  const { min, max } = yDomain.value
  const out: Array<{ x: number; y: number; key: string; label: string; amountLabel: string }> = []

  keys.forEach((key, index) => {
    const point = pointsByKey.value.get(key)
    if (!point) {
      return
    }
    const ratio = (point.balance_eur_min_unit - min) / (max - min || 1)
    const y = chartHeight - padding.bottom - ratio * innerH.value
    const amountLabel = formatEuro(point.balance_eur_min_unit)
    out.push({
      x: xAt(index, keys.length),
      y,
      key,
      amountLabel,
      label: `${formatPeriodLabel(point)}: ${amountLabel}`,
    })
  })

  return out
})

/** Amount labels only on year view (few points, readable). */
const showPointAmounts = computed(() => activeView.value === 'year')

const lineSegments = computed(() => {
  const keys = axisKeys.value
  const byKey = new Map(plotted.value.map((pt) => [pt.key, pt]))
  const segments: string[] = []
  let current: string[] = []

  for (const key of keys) {
    const pt = byKey.get(key)
    if (pt) {
      current.push(`${pt.x},${pt.y}`)
      continue
    }
    if (current.length > 0) {
      segments.push(current.join(' '))
      current = []
    }
  }
  if (current.length > 0) {
    segments.push(current.join(' '))
  }

  return segments
})

const xLabels = computed(() => {
  const keys = axisKeys.value
  if (keys.length === 0) {
    return [] as Array<{ x: number; text: string; anchor: 'start' | 'middle' | 'end' }>
  }

  const labels: Array<{ x: number; text: string; anchor: 'start' | 'middle' | 'end' }> = []

  if (activeView.value === 'year') {
    keys.forEach((key, index) => {
      const x = xAt(index, keys.length)
      const anchor = index === 0 ? 'start' : index === keys.length - 1 ? 'end' : 'middle'
      labels.push({ x, text: key, anchor })
    })
    return labels
  }

  if (activeView.value === 'month') {
    const seenYears = new Set<string>()
    keys.forEach((key, index) => {
      const year = key.slice(0, 4)
      const month = Number(key.slice(5, 7))
      const isYearStart = month === 1 || index === 0
      if (!isYearStart || seenYears.has(year)) {
        return
      }
      seenYears.add(year)
      const x = xAt(index, keys.length)
      const anchor = index === 0 ? 'start' : index === keys.length - 1 ? 'end' : 'middle'
      labels.push({ x, text: year, anchor })
    })
    return labels
  }

  const seenMonths = new Set<string>()
  keys.forEach((key, index) => {
    const date = new Date(`${key}T12:00:00`)
    const monthKey = `${date.getFullYear()}-${date.getMonth()}`
    const isMonthStart = index === 0 || date.getDate() === 1
    if (!isMonthStart || seenMonths.has(monthKey)) {
      return
    }
    seenMonths.add(monthKey)
    const x = xAt(index, keys.length)
    const anchor = index === 0 ? 'start' : index === keys.length - 1 ? 'end' : 'middle'
    labels.push({ x, text: formatMonthShort(key), anchor })
  })

  return labels
})

const applyEvolutionResponse = (response: Awaited<ReturnType<typeof authService.getPortfolioEvolution>>) => {
  availableYears.value = response.years.length > 0 ? response.years : [response.year]
  if (response.granularity === 'day' && selectedYear.value !== response.year) {
    suppressYearWatch = true
    selectedYear.value = response.year
    suppressYearWatch = false
  }
  points.value = response.data
  rangeFrom.value = response.from
  rangeTo.value = response.to
  syncQueryToUrl()
}

const load = async (options: {
  ensureLatestSnapshot?: boolean
  year?: number
  view?: EvolutionView
} = {}) => {
  const { ensureLatestSnapshot = true, year, view } = options
  const granularity = view ?? activeView.value
  const isInitial = isLoading.value
  if (!isInitial) {
    isLoadingChart.value = true
  }
  error.value = null
  try {
    if (ensureLatestSnapshot) {
      await authService.getPortfolioStats(1, 1)
    }
    const response = await authService.getPortfolioEvolution(
      year ?? selectedYear.value,
      granularity,
    )
    applyEvolutionResponse(response)
  } catch (err: unknown) {
    console.error('Failed to load portfolio evolution:', err)
    const anyErr = err as { response?: { data?: { message?: string } } }
    error.value = anyErr.response?.data?.message || 'Failed to load portfolio evolution'
  } finally {
    isLoading.value = false
    isLoadingChart.value = false
  }
}

const setView = async (view: EvolutionView) => {
  if (view === activeView.value) {
    return
  }
  suppressViewWatch = true
  activeView.value = view
  suppressViewWatch = false
  syncQueryToUrl()
  await load({ ensureLatestSnapshot: false, view })
}

const recalculate = async () => {
  isRecalculating.value = true
  recalculateMessage.value = null
  recalculateError.value = null
  try {
    await authService.recalculatePortfolioEvolution()
    recalculateMessage.value = t('portfolioEvolution.recalculateStarted')
    const response = await authService.getPortfolioEvolution(
      selectedYear.value,
      activeView.value,
    )
    applyEvolutionResponse(response)
  } catch (err: unknown) {
    console.error('Failed to recalculate portfolio evolution:', err)
    const anyErr = err as { response?: { data?: { message?: string } } }
    recalculateError.value = anyErr.response?.data?.message || t('portfolioEvolution.recalculateFailed')
  } finally {
    isRecalculating.value = false
  }
}

watch(selectedYear, async (year, previous) => {
  if (suppressYearWatch || activeView.value !== 'day' || year === previous || isLoading.value || isLoadingChart.value) {
    return
  }
  syncQueryToUrl()
  await load({ ensureLatestSnapshot: false, year, view: 'day' })
})

watch(
  () => route.query.year,
  (value) => {
    if (activeView.value !== 'day') {
      return
    }
    const year = parseYearQuery(value)
    if (year == null || year === selectedYear.value || isLoading.value || isLoadingChart.value) {
      return
    }
    selectedYear.value = year
  },
)

watch(
  () => route.query.view,
  (value) => {
    if (suppressViewWatch) {
      return
    }
    const view = parseViewQuery(value)
    if (view === activeView.value || isLoading.value || isLoadingChart.value) {
      return
    }
    activeView.value = view
    load({ ensureLatestSnapshot: false, view })
  },
)

const openPortfolioAsOf = (isoDate: string) => {
  const query: Record<string, string> = { asOf: isoDate }
  router.push({ name: 'statistics', query })
}

onMounted(async () => {
  await load({
    ensureLatestSnapshot: true,
    year: selectedYear.value,
    view: activeView.value,
  })
})
</script>
