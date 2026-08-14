<template>
  <div :class="embedded ? undefined : 'container mx-auto p-8'">
    <h1 v-if="!embedded" class="text-4xl font-bold mb-8">{{ $t('portfolioStats.title') }}</h1>
    
    <div v-if="isLoading" class="flex justify-center">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <div v-else-if="error" class="alert alert-error">
      <span>{{ error }}</span>
    </div>

    <div v-else-if="holdings.length === 0" class="card bg-base-100 shadow-xl">
      <div class="card-body">
        <p class="text-base-content/70">{{ $t('portfolioStats.noHoldings') }}</p>
      </div>
    </div>

    <div v-else class="space-y-6">
      <div v-if="performanceTiles.length > 0" class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <h2 class="text-xl font-bold text-base-content">
            {{ $t('portfolioStats.performanceTemperature.title') }}
          </h2>
          <p class="text-sm text-base-content/60 mt-1">
            {{ $t('portfolioStats.performanceTemperature.subtitle') }}
          </p>
          <div class="mt-4 relative w-full h-[28rem] sm:h-[32rem] rounded-xl overflow-hidden bg-base-100">
            <div
              v-for="tile in performanceTiles"
              :key="tile.id"
              class="absolute box-border p-0.5"
              :style="{
                left: `${tile.x}%`,
                top: `${tile.y}%`,
                width: `${tile.width}%`,
                height: `${tile.height}%`,
              }"
            >
              <div
                class="h-full w-full rounded-xl p-2.5 flex flex-col justify-between overflow-hidden"
                :style="performanceTileToneStyle(tile.day_change_percent)"
                :title="`${tile.ticker_symbol} · ${tile.product}`"
              >
                <div class="min-w-0">
                  <div class="font-bold leading-tight truncate text-[0.95rem]">
                    {{ tile.ticker_symbol }}
                  </div>
                  <div
                    v-if="tile.width >= 10 && tile.height >= 12"
                    class="text-xs leading-tight mt-0.5 truncate opacity-80"
                  >
                    {{ tile.product }}
                  </div>
                </div>
                <div
                  v-if="tile.day_change_percent != null && tile.width >= 8 && tile.height >= 10"
                  class="flex items-center gap-1.5 min-w-0"
                >
                  <span class="font-semibold tabular-nums text-sm truncate">
                    {{ formatDayChangePercent(tile.day_change_percent) }}
                  </span>
                  <span
                    class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/55"
                    aria-hidden="true"
                  >
                    <svg
                      v-if="tile.day_change_percent >= 0"
                      class="h-3 w-3"
                      viewBox="0 0 12 12"
                      fill="none"
                    >
                      <path d="M6 2.5L9.5 7H2.5L6 2.5Z" fill="currentColor" />
                    </svg>
                    <svg
                      v-else
                      class="h-3 w-3"
                      viewBox="0 0 12 12"
                      fill="none"
                    >
                      <path d="M6 9.5L2.5 5H9.5L6 9.5Z" fill="currentColor" />
                    </svg>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <p v-if="closingDateLabel" class="text-sm text-base-content/60 mb-3">
            {{ closingDateLabel }}
          </p>
          <div class="overflow-x-auto">
            <table class="table table-sm w-full">
              <thead>
                <tr class="border-b border-base-300">
                  <th class="w-[9.5rem] max-w-[9.5rem]">
                    <button type="button" class="font-medium text-base-content/70 hover:text-base-content" @click="toggleSort('symbols')">
                      {{ $t('portfolioStats.table.symbols') }}
                      <span v-if="sortBy === 'symbols'" class="text-xs ml-1">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                    </button>
                  </th>
                  <th class="w-[5.5rem] text-right whitespace-nowrap">
                    <button type="button" class="font-medium text-base-content/70 hover:text-base-content" @click="toggleSort('price')">
                      {{ $t('portfolioStats.table.price') }}
                      <span v-if="sortBy === 'price'" class="text-xs ml-1">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                    </button>
                  </th>
                  <th class="w-[4rem] text-right whitespace-nowrap">
                    <button type="button" class="font-medium text-base-content/70 hover:text-base-content" @click="toggleSort('quantity')">
                      {{ $t('portfolioStats.table.quantity') }}
                      <span v-if="sortBy === 'quantity'" class="text-xs ml-1">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                    </button>
                  </th>
                  <th class="w-[5.5rem] text-right whitespace-nowrap">
                    <button type="button" class="font-medium text-base-content/70 hover:text-base-content" @click="toggleSort('total')">
                      {{ $t('portfolioStats.table.total') }}
                      <span v-if="sortBy === 'total'" class="text-xs ml-1">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                    </button>
                  </th>
                  <th class="w-[5.5rem] text-right whitespace-nowrap">
                    <button type="button" class="font-medium text-base-content/70 hover:text-base-content" @click="toggleSort('total_eur')">
                      {{ $t('portfolioStats.table.totalEur') }}
                      <span v-if="sortBy === 'total_eur'" class="text-xs ml-1">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                    </button>
                  </th>
                  <th class="w-[6.5rem] text-right whitespace-nowrap">
                    <button type="button" class="font-medium text-base-content/70 hover:text-base-content" @click="toggleSort('change')">
                      {{ $t('portfolioStats.table.change') }}
                      <span v-if="sortBy === 'change'" class="text-xs ml-1">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                    </button>
                  </th>
                  <th class="w-[5.5rem] text-right whitespace-nowrap">
                    <button type="button" class="font-medium text-base-content/70 hover:text-base-content" @click="toggleSort('total_gain_loss')">
                      {{ $t('portfolioStats.table.totalGainLoss') }}
                      <span v-if="sortBy === 'total_gain_loss'" class="text-xs ml-1">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                    </button>
                  </th>
                  <th class="w-[5.5rem] text-right whitespace-nowrap">
                    <button type="button" class="font-medium text-base-content/70 hover:text-base-content" @click="toggleSort('total_gain_loss_eur')">
                      {{ $t('portfolioStats.table.totalGainLossEur') }}
                      <span v-if="sortBy === 'total_gain_loss_eur'" class="text-xs ml-1">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                    </button>
                  </th>
                  <th class="w-[3.5rem] text-right whitespace-nowrap">
                    <button type="button" class="font-medium text-base-content/70 hover:text-base-content" @click="toggleSort('weight')">
                      {{ $t('portfolioStats.table.weight') }}
                      <span v-if="sortBy === 'weight'" class="text-xs ml-1">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                    </button>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="holding in holdings"
                  :key="holding.isin"
                  class="border-b border-base-200 hover:bg-base-200/40"
                >
                  <td class="py-2.5 w-[9.5rem] max-w-[9.5rem]">
                    <RouterLink
                      v-if="holding.product.trim()"
                      :to="transactionsLinkForProduct(holding.product)"
                      class="block min-w-0 hover:opacity-80"
                      :title="holding.product"
                    >
                      <div class="font-bold text-base-content leading-tight truncate">
                        {{ holding.ticker_symbol || holding.isin }}
                      </div>
                      <div class="text-sm text-base-content/60 leading-tight mt-0.5 truncate">
                        {{ holding.product }}
                      </div>
                    </RouterLink>
                    <div v-else class="min-w-0" :title="holding.product">
                      <div class="font-bold text-base-content leading-tight truncate">
                        {{ holding.ticker_symbol || holding.isin }}
                      </div>
                      <div class="text-sm text-base-content/60 leading-tight mt-0.5 truncate">
                        {{ holding.product }}
                      </div>
                    </div>
                  </td>
                  <td class="whitespace-nowrap text-right py-2.5 align-middle px-2">
                    {{ formatClosingPrice(holding) }}
                  </td>
                  <td class="whitespace-nowrap text-right py-2.5 align-middle px-2">
                    {{ formatQuantity(holding.quantity) }}
                  </td>
                  <td class="whitespace-nowrap text-right py-2.5 align-middle px-2">
                    {{ formatPositionTotal(holding) }}
                  </td>
                  <td class="whitespace-nowrap text-right py-2.5 align-middle px-2">
                    {{ formatPositionTotalEur(holding) }}
                  </td>
                  <td class="whitespace-nowrap text-right py-2.5 align-middle px-2">
                    <div v-if="holding.day_change_min_unit != null" :class="changeColorClass(holding.day_change_min_unit)">
                      <div class="leading-tight font-medium">
                        {{ formatSignedMoney(holding.day_change_min_unit, holding.closing_price_currency) }}
                      </div>
                      <div
                        v-if="holding.day_change_percent != null"
                        class="text-sm leading-tight mt-0.5"
                      >
                        {{ formatSignedPercent(holding.day_change_percent) }}
                      </div>
                    </div>
                    <span v-else class="text-base-content/50">{{ $t('portfolioStats.table.priceUnavailable') }}</span>
                  </td>
                  <td class="whitespace-nowrap text-right py-2.5 align-middle px-2">
                    <div
                      v-if="holding.total_gain_loss_min_unit != null"
                      :class="changeColorClass(holding.total_gain_loss_min_unit)"
                    >
                      <div class="leading-tight font-medium">
                        {{ formatSignedMoney(holding.total_gain_loss_min_unit, 'USD', 0) }}
                      </div>
                      <div
                        v-if="holding.total_gain_loss_percent != null"
                        class="text-sm leading-tight mt-0.5 text-base-content"
                      >
                        {{ formatSignedPercent(holding.total_gain_loss_percent) }}
                      </div>
                    </div>
                    <span v-else class="text-base-content/50">{{ $t('portfolioStats.table.priceUnavailable') }}</span>
                  </td>
                  <td class="whitespace-nowrap text-right py-2.5 align-middle px-2">
                    <div
                      v-if="holding.total_gain_loss_eur_min_unit != null"
                      :class="changeColorClass(holding.total_gain_loss_eur_min_unit)"
                    >
                      <div class="leading-tight font-medium">
                        {{ formatSignedMoney(holding.total_gain_loss_eur_min_unit, 'EUR', 0) }}
                      </div>
                    </div>
                    <span v-else class="text-base-content/50">{{ $t('portfolioStats.table.priceUnavailable') }}</span>
                  </td>
                  <td class="whitespace-nowrap text-right py-2.5 align-middle tabular-nums px-2">
                    <span v-if="holding.weight_percent != null">{{ formatWeightPercent(holding.weight_percent) }}</span>
                    <span v-else class="text-base-content/50">{{ $t('portfolioStats.table.priceUnavailable') }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="flex flex-col items-center gap-4 mt-6">
            <div class="flex items-center gap-2">
              <label class="label">
                <span class="label-text">{{ $t('portfolioStats.pagination.itemsPerPage') }}:</span>
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
                {{ $t('portfolioStats.pagination.page', { current: formatInteger(currentPage), total: formatInteger(lastPage) }) }}
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

      <div v-if="concentration.length > 0" class="card bg-base-200/60 shadow-xl">
        <div class="card-body">
          <h2 class="text-xl font-bold text-base-content">
            {{ $t('portfolioStats.table.concentrationRisk') }}
          </h2>
          <div class="mt-4 space-y-3">
            <div
              v-for="item in concentration"
              :key="item.isin"
              class="flex items-center gap-3"
            >
              <span class="w-16 shrink-0 font-medium text-base-content">
                {{ item.ticker_symbol }}
              </span>
              <div class="flex-1 min-w-0">
                <div
                  class="h-3 rounded-full bg-[#2d5cf7]"
                  :style="{ width: `${Math.min(item.weight_percent, 100)}%` }"
                />
              </div>
              <span class="w-16 shrink-0 text-right tabular-nums text-base-content">
                {{ formatWeightPercent(item.weight_percent) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  authService,
  PortfolioHolding,
  PortfolioConcentrationItem,
  PortfolioPerformanceTemperatureItem,
} from '../services/authService'
import {
  formatHoldingQuantity,
  formatInteger,
  formatPricePrefixFromCents,
  formatSignedCurrencyFromCents,
  formatSignedPercent,
  formatDecimal,
} from '../utils/numberFormat'
import { layoutTreemap } from '../utils/treemap'

withDefaults(defineProps<{ embedded?: boolean }>(), {
  embedded: false,
})

/** Same cap as API `product` filter (200 chars). */
const MAX_PRODUCT_QUERY_LEN = 200

const { t, locale } = useI18n()

const transactionsLinkForProduct = (product: string) => {
  const q = product.trim().slice(0, MAX_PRODUCT_QUERY_LEN)
  return {
    name: 'degiro-transactions-list' as const,
    query: q ? { product: q } : {}
  }
}

const isLoading = ref(true)
const error = ref<string | null>(null)
const holdings = ref<PortfolioHolding[]>([])
const concentration = ref<PortfolioConcentrationItem[]>([])
const performanceTemperature = ref<PortfolioPerformanceTemperatureItem[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const perPage = ref(20)
const sortBy = ref('weight')
const sortOrder = ref<'asc' | 'desc'>('desc')

type PerformanceTile = PortfolioPerformanceTemperatureItem & {
  id: string
  x: number
  y: number
  width: number
  height: number
}

const performanceTiles = computed((): PerformanceTile[] => {
  const items = performanceTemperature.value
  if (items.length === 0) {
    return []
  }

  const layout = layoutTreemap(
    items.map((item) => ({
      id: item.isin,
      value: item.weight_percent,
    })),
  )

  const byIsin = new Map(items.map((item) => [item.isin, item]))

  return layout.flatMap((leaf) => {
    const item = byIsin.get(leaf.id)
    if (!item) {
      return []
    }
    return [{
      ...item,
      id: leaf.id,
      x: leaf.x,
      y: leaf.y,
      width: leaf.width,
      height: leaf.height,
    }]
  })
})

const formatQuantity = formatHoldingQuantity

const formatFriendlyClosingDate = (isoDate: string): string => {
  const date = new Date(`${isoDate}T12:00:00`)
  if (Number.isNaN(date.getTime())) {
    return isoDate
  }

  const displayLocale = locale.value === 'es' ? 'es-ES' : 'en-GB'
  return new Intl.DateTimeFormat(displayLocale, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(date)
}

const closingDateLabel = computed(() => {
  const closingDate = holdings.value.find((h) => h.closing_date)?.closing_date
  if (!closingDate) {
    return null
  }

  return t('portfolioStats.table.closingPriceWithDate', {
    date: formatFriendlyClosingDate(closingDate),
  })
})

const formatClosingPrice = (holding: PortfolioHolding): string => {
  if (holding.closing_price_min_unit == null) {
    return t('portfolioStats.table.priceUnavailable')
  }

  const currency = holding.closing_price_currency || 'USD'
  try {
    return formatPricePrefixFromCents(holding.closing_price_min_unit, currency)
  } catch {
    return formatPricePrefixFromCents(holding.closing_price_min_unit, 'USD')
  }
}

const formatPositionTotal = (holding: PortfolioHolding): string => {
  if (holding.market_value_min_unit == null) {
    return t('portfolioStats.table.priceUnavailable')
  }

  return formatPricePrefixFromCents(holding.market_value_min_unit, 'USD', 0)
}

const formatPositionTotalEur = (holding: PortfolioHolding): string => {
  if (holding.market_value_eur_min_unit == null) {
    return t('portfolioStats.table.priceUnavailable')
  }

  return formatPricePrefixFromCents(holding.market_value_eur_min_unit, 'EUR', 0)
}

const formatSignedMoney = (
  cents: number,
  currency: string | null | undefined,
  fractionDigits = 2,
): string => {
  const code = currency || 'USD'
  try {
    return formatSignedCurrencyFromCents(cents, code, fractionDigits)
  } catch {
    return formatSignedCurrencyFromCents(cents, 'USD', fractionDigits)
  }
}

const changeColorClass = (cents: number): string => {
  if (cents > 0) return 'text-success'
  if (cents < 0) return 'text-error'
  return 'text-base-content'
}

const formatWeightPercent = (percent: number): string => {
  return `${formatDecimal(percent, 1, 1)}%`
}

const formatDayChangePercent = (percent: number): string => {
  const sign = percent > 0 ? '+' : percent < 0 ? '-' : ''
  return `${sign}${formatDecimal(Math.abs(percent), 2, 2)}%`
}

const performanceMaxAbsChange = computed(() => {
  let maxAbs = 0
  for (const item of performanceTemperature.value) {
    if (item.day_change_percent != null) {
      maxAbs = Math.max(maxAbs, Math.abs(item.day_change_percent))
    }
  }
  return maxAbs > 0 ? maxAbs : 1
})

const lerpChannel = (from: number, to: number, t: number): number =>
  Math.round(from + (to - from) * t)

const mixHex = (from: string, to: string, t: number): string => {
  const parse = (hex: string) => [
    parseInt(hex.slice(1, 3), 16),
    parseInt(hex.slice(3, 5), 16),
    parseInt(hex.slice(5, 7), 16),
  ] as const
  const [fr, fg, fb] = parse(from)
  const [tr, tg, tb] = parse(to)
  const clamped = Math.min(1, Math.max(0, t))
  return `#${[lerpChannel(fr, tr, clamped), lerpChannel(fg, tg, clamped), lerpChannel(fb, tb, clamped)]
    .map((n) => n.toString(16).padStart(2, '0'))
    .join('')}`
}

const performanceTileToneStyle = (
  dayChangePercent: number | null,
): { backgroundColor: string; color: string } => {
  if (dayChangePercent == null || dayChangePercent === 0) {
    return { backgroundColor: '#e8e8e8', color: '#4b5563' }
  }

  // Relative to today's best/worst move so extremes stand out.
  const intensity = Math.sqrt(Math.min(1, Math.abs(dayChangePercent) / performanceMaxAbsChange.value))

  if (dayChangePercent > 0) {
    return {
      backgroundColor: mixHex('#e4f8ed', '#0f6b3c', intensity),
      color: intensity > 0.55 ? '#ffffff' : mixHex('#2f8f5c', '#083d24', intensity),
    }
  }

  return {
    backgroundColor: mixHex('#fce8ea', '#9f1219', intensity),
    color: intensity > 0.55 ? '#ffffff' : mixHex('#c23b45', '#6b0d12', intensity),
  }
}

const toggleSort = (column: string) => {
  if (sortBy.value === column) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = column
    sortOrder.value = 'desc'
  }
  loadPage(1)
}

const loadPage = async (page: number) => {
  if (page < 1) return
  
  isLoading.value = true
  error.value = null
  
  try {
    const response = await authService.getPortfolioStats(
      perPage.value,
      page,
      sortBy.value,
      sortOrder.value,
    )
    holdings.value = response.data
    concentration.value = response.concentration ?? []
    performanceTemperature.value = response.performance_temperature ?? []
    currentPage.value = response.current_page
    lastPage.value = response.last_page
  } catch (err: any) {
    console.error('Failed to load portfolio stats:', err)
    error.value = err.response?.data?.message || 'Failed to load portfolio stats'
  } finally {
    isLoading.value = false
  }
}

const handlePerPageChange = () => {
  loadPage(1)
}

onMounted(async () => {
  await loadPage(1)
})
</script>
