<template>
  <div :class="embedded ? undefined : 'container mx-auto p-8'">
    <h1 v-if="!embedded" class="text-4xl font-bold mb-8">{{ $t('portfolioStats.title') }}</h1>
    
    <div v-if="isLoading && !hasLoadedOnce" class="flex flex-col items-center justify-center gap-4 py-16 px-4 text-center">
      <span class="loading loading-spinner loading-lg"></span>
      <div class="max-w-md space-y-2">
        <p class="text-lg font-semibold text-base-content/80">
          {{ $t('portfolioStats.initialLoading.title') }}
        </p>
        <p class="text-sm text-base-content/60">
          {{ $t('portfolioStats.initialLoading.description') }}
        </p>
      </div>
    </div>

    <div v-else-if="fatalError" class="alert alert-error">
      <span>{{ fatalError }}</span>
    </div>

    <div v-else class="space-y-6">
      <div
        v-if="portfolioEurMinUnit != null || netMarketValueEurMinUnit != null || hasLoadedOnce"
        class="rounded-2xl bg-base-200/70 px-5 py-4 sm:px-6"
      >
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
          <div>
            <p class="text-sm text-base-content/60 flex items-center gap-1">
              <span>{{ $t('portfolioStats.summary.balance') }}</span>
              <button
                type="button"
                class="btn btn-ghost btn-xs btn-circle text-base-content/50 hover:text-base-content"
                :aria-label="$t('portfolioStats.summaryTooltips.balance.title')"
                @click="showSummaryTooltip('balance')"
              >
                ?
              </button>
            </p>
            <p class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight mt-1">
              {{ formatEurAmount(netMarketValueEurMinUnit) }}
            </p>
          </div>
          <div>
            <p class="text-sm text-base-content/60 flex items-center gap-1">
              <span>{{ $t('portfolioStats.summary.portfolio') }}</span>
              <button
                type="button"
                class="btn btn-ghost btn-xs btn-circle text-base-content/50 hover:text-base-content"
                :aria-label="$t('portfolioStats.summaryTooltips.portfolio.title')"
                @click="showSummaryTooltip('portfolio')"
              >
                ?
              </button>
            </p>
            <p class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight mt-1 text-base-content/45">
              {{ formatEurAmount(portfolioEurMinUnit) }}
            </p>
          </div>
          <div>
            <p class="text-sm text-base-content/60 flex items-center gap-1">
              <span>{{ $t('portfolioStats.summary.cash') }}</span>
              <button
                type="button"
                class="btn btn-ghost btn-xs btn-circle text-base-content/50 hover:text-base-content"
                :aria-label="$t('portfolioStats.summaryTooltips.cash.title')"
                @click="showSummaryTooltip('cash')"
              >
                ?
              </button>
            </p>
            <p class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight mt-1 text-base-content/45">
              {{ formatEurAmount(cashEurMinUnit) }}
            </p>
          </div>
          <div>
            <p class="text-sm text-base-content/60 flex items-center gap-1">
              <span>{{ $t('portfolioStats.summary.day') }}</span>
              <button
                type="button"
                class="btn btn-ghost btn-xs btn-circle text-base-content/50 hover:text-base-content"
                :aria-label="$t('portfolioStats.summaryTooltips.day.title')"
                @click="showSummaryTooltip('day')"
              >
                ?
              </button>
            </p>
            <p
              class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight mt-1"
              :class="signedAmountColorClass(dayChangeEurMinUnit)"
            >
              {{ formatSignedEurAmount(dayChangeEurMinUnit) }}
            </p>
          </div>
          <div>
            <p class="text-sm text-base-content/60 flex items-center gap-1">
              <span>{{ $t('portfolioStats.summary.totalPl') }}</span>
              <button
                type="button"
                class="btn btn-ghost btn-xs btn-circle text-base-content/50 hover:text-base-content"
                :aria-label="$t('portfolioStats.summaryTooltips.totalPl.title')"
                @click="showSummaryTooltip('totalPl')"
              >
                ?
              </button>
            </p>
            <p
              class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight mt-1"
              :class="signedAmountColorClass(totalGainLossEurMinUnit)"
            >
              {{ formatSignedEurAmount(totalGainLossEurMinUnit) }}
            </p>
          </div>
        </div>
      </div>

      <div
        class="rounded-xl border border-base-300 bg-base-100 px-4 py-3 sm:px-5"
      >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
          <div>
            <p class="text-xs uppercase tracking-wide text-base-content/50 font-medium">
              {{ $t('portfolioStats.dataAsOfLabel') }}
            </p>
            <div class="mt-1 flex flex-wrap items-center gap-2">
              <p class="text-lg sm:text-xl font-semibold text-base-content capitalize">
                {{ closingDateFriendly || '—' }}
              </p>
              <div class="inline-flex items-center gap-1">
                <button
                  type="button"
                  class="btn btn-sm btn-square border border-base-300 bg-base-100"
                  :disabled="isLoading || !canGoPrevAsOfDay"
                  :aria-label="$t('portfolioStats.asOfPrevDay')"
                  @click="shiftAsOfByDays(-1)"
                >
                  &lt;
                </button>
                <AsOfDatePicker
                  :model-value="selectedAsOf"
                  :max="lastUsMarketOpenDate"
                  :disabled="isLoading"
                  :aria-label="$t('portfolioStats.asOfPickerLabel')"
                  @update:model-value="onAsOfSelected"
                />
                <button
                  type="button"
                  class="btn btn-sm btn-square border border-base-300 bg-base-100"
                  :disabled="isLoading || !canGoNextAsOfDay"
                  :aria-label="$t('portfolioStats.asOfNextDay')"
                  @click="shiftAsOfByDays(1)"
                >
                  &gt;
                </button>
              </div>
            </div>
          </div>
          <div v-if="fxRateLabel">
            <p class="text-xs uppercase tracking-wide text-base-content/50 font-medium sm:text-right">
              {{ $t('portfolioStats.fxRateLabel') }}
            </p>
            <p class="text-lg sm:text-xl font-semibold text-base-content mt-0.5 tabular-nums sm:text-right">
              {{ fxRateValue }}
            </p>
            <p v-if="fxRateDateFriendly" class="text-sm text-base-content/55 mt-0.5 capitalize sm:text-right">
              {{ $t('portfolioStats.fxRateAsOf', { date: fxRateDateFriendly }) }}
            </p>
          </div>
        </div>
        <div v-if="dateError" class="alert alert-warning mt-3 py-2">
          <span>{{ dateError }}</span>
        </div>
      </div>

      <div v-if="isLoading" class="flex justify-center py-6">
        <span class="loading loading-spinner loading-lg"></span>
      </div>

      <div v-else-if="holdings.length === 0" class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <p class="text-base-content/70">{{ $t('portfolioStats.noHoldings') }}</p>
        </div>
      </div>

      <template v-else>

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
          <h2 class="text-xl font-bold text-base-content mb-3">
            {{ $t('portfolioStats.title') }}
          </h2>
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
      </template>
    </div>

    <dialog ref="summaryTooltipModal" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="font-bold text-lg mb-3">{{ summaryTooltipTitle }}</h3>
        <p class="whitespace-pre-line text-base-content/80 leading-relaxed">{{ summaryTooltipDescription }}</p>
        <div class="modal-action">
          <button type="button" class="btn" @click="closeSummaryTooltip">
            {{ $t('portfolioStats.summaryTooltips.close') }}
          </button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop">
        <button type="button" @click="closeSummaryTooltip">close</button>
      </form>
    </dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { RouterLink, useRoute, useRouter, type LocationQuery } from 'vue-router'
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
import AsOfDatePicker from '../components/AsOfDatePicker.vue'

withDefaults(defineProps<{ embedded?: boolean }>(), {
  embedded: false,
})

/** Same cap as API `product` filter (200 chars). */
const MAX_PRODUCT_QUERY_LEN = 200

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()

const parseAsOfQuery = (value: unknown): string | null => {
  const raw = Array.isArray(value) ? value[0] : value
  if (typeof raw !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
    return null
  }
  return raw
}

type SummaryTooltipKey = 'balance' | 'portfolio' | 'cash' | 'day' | 'totalPl'

const summaryTooltipModal = ref<HTMLDialogElement | null>(null)
const summaryTooltipTitle = ref('')
const summaryTooltipDescription = ref('')

const showSummaryTooltip = (key: SummaryTooltipKey) => {
  summaryTooltipTitle.value = t(`portfolioStats.summaryTooltips.${key}.title`)
  summaryTooltipDescription.value = t(`portfolioStats.summaryTooltips.${key}.description`)
  summaryTooltipModal.value?.showModal()
}

const closeSummaryTooltip = () => {
  summaryTooltipModal.value?.close()
}

const transactionsLinkForProduct = (product: string) => {
  const q = product.trim().slice(0, MAX_PRODUCT_QUERY_LEN)
  return {
    name: 'degiro-transactions-list' as const,
    query: q ? { product: q } : {}
  }
}

const isLoading = ref(true)
const hasLoadedOnce = ref(false)
const fatalError = ref<string | null>(null)
const dateError = ref<string | null>(null)
const holdings = ref<PortfolioHolding[]>([])
const concentration = ref<PortfolioConcentrationItem[]>([])
const performanceTemperature = ref<PortfolioPerformanceTemperatureItem[]>([])
const portfolioEurMinUnit = ref<number | null>(null)
const netMarketValueEurMinUnit = ref<number | null>(null)
const cashEurMinUnit = ref(0)
const dayChangeEurMinUnit = ref<number | null>(null)
const totalGainLossEurMinUnit = ref<number | null>(null)
const usdToEurRate = ref<number | null>(null)
const usdToEurRateDate = ref<string | null>(null)
const lastUsMarketOpenDate = ref<string | null>(null)
const selectedAsOf = ref<string | null>(null)
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

const closingDateIso = computed(() => {
  return selectedAsOf.value
    ?? lastUsMarketOpenDate.value
    ?? holdings.value.find((h) => h.closing_date)?.closing_date
    ?? null
})

const closingDateFriendly = computed(() => {
  if (!closingDateIso.value) {
    return null
  }
  return formatFriendlyClosingDate(closingDateIso.value)
})
const fxRateDateFriendly = computed(() => {
  if (!usdToEurRateDate.value) {
    return null
  }
  return formatFriendlyClosingDate(usdToEurRateDate.value)
})

const fxRateValue = computed(() => {
  if (usdToEurRate.value == null || usdToEurRate.value <= 0) {
    return null
  }
  return t('portfolioStats.fxRateValue', {
    rate: formatDecimal(usdToEurRate.value, 4, 6),
  })
})

const fxRateLabel = computed(() => fxRateValue.value != null)

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

const formatEurAmount = (cents: number | null): string => {
  if (cents == null) {
    return t('portfolioStats.table.priceUnavailable')
  }
  return formatPricePrefixFromCents(cents, 'EUR', 0)
}

const formatSignedEurAmount = (cents: number | null): string => {
  if (cents == null) {
    return t('portfolioStats.table.priceUnavailable')
  }
  return formatSignedCurrencyFromCents(cents, 'EUR', 0)
}

const signedAmountColorClass = (cents: number | null): string => {
  if (cents == null || cents === 0) {
    return 'text-base-content'
  }
  return cents > 0 ? 'text-success' : 'text-error'
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

const marketClosedMessage = (data: {
  message?: string
  reason?: string
  date?: string
  holiday?: string | null
}): string => {
  const dateLabel = data.date ? formatFriendlyClosingDate(data.date) : data.date
  if (data.reason === 'weekend' && dateLabel) {
    return t('portfolioStats.marketClosedWeekend', { date: dateLabel })
  }
  if (data.reason === 'holiday' && dateLabel) {
    return t('portfolioStats.marketClosedHoliday', {
      date: dateLabel,
      holiday: data.holiday || t('portfolioStats.marketHolidayFallback'),
    })
  }
  if (data.reason === 'too_recent' && dateLabel) {
    const latest = lastUsMarketOpenDate.value
      ? formatFriendlyClosingDate(lastUsMarketOpenDate.value)
      : lastUsMarketOpenDate.value
    return t('portfolioStats.marketClosedTooRecent', {
      date: dateLabel,
      latest: latest || '',
    })
  }
  return data.message || t('portfolioStats.marketClosedGeneric')
}

const onAsOfSelected = (value: string) => {
  if (!value || value === selectedAsOf.value) {
    return
  }
  loadPage(1, value)
}

const shiftIsoDate = (iso: string, days: number): string => {
  const [y, m, d] = iso.split('-').map(Number)
  const date = new Date(Date.UTC(y, m - 1, d))
  date.setUTCDate(date.getUTCDate() + days)
  const yy = date.getUTCFullYear()
  const mm = String(date.getUTCMonth() + 1).padStart(2, '0')
  const dd = String(date.getUTCDate()).padStart(2, '0')
  return `${yy}-${mm}-${dd}`
}

const canGoPrevAsOfDay = computed(() => Boolean(selectedAsOf.value))

const canGoNextAsOfDay = computed(() => {
  if (!selectedAsOf.value) {
    return false
  }
  const next = shiftIsoDate(selectedAsOf.value, 1)
  if (lastUsMarketOpenDate.value && next > lastUsMarketOpenDate.value) {
    return false
  }
  return true
})

const shiftAsOfByDays = (days: number) => {
  if (!selectedAsOf.value) {
    return
  }
  const next = shiftIsoDate(selectedAsOf.value, days)
  if (days > 0 && lastUsMarketOpenDate.value && next > lastUsMarketOpenDate.value) {
    return
  }
  onAsOfSelected(next)
}

const syncAsOfToUrl = (asOf: string) => {
  if (parseAsOfQuery(route.query.asOf) === asOf) {
    return
  }
  const query: LocationQuery = { ...route.query, asOf }
  if (!query.tab || query.tab === 'portfolio') {
    delete query.tab
  }
  router.replace({ name: 'statistics', query })
}

const loadPage = async (page: number, asOfOverride?: string) => {
  if (page < 1) return

  const asOf = asOfOverride ?? selectedAsOf.value ?? undefined
  isLoading.value = true
  fatalError.value = null
  dateError.value = null

  try {
    const response = await authService.getPortfolioStats(
      perPage.value,
      page,
      sortBy.value,
      sortOrder.value,
      asOf,
    )
    holdings.value = response.data
    concentration.value = response.concentration ?? []
    performanceTemperature.value = response.performance_temperature ?? []
    portfolioEurMinUnit.value = response.total_market_value_eur_min_unit ?? null
    netMarketValueEurMinUnit.value = response.net_market_value_eur_min_unit ?? null
    cashEurMinUnit.value = response.cash_eur_min_unit ?? 0
    dayChangeEurMinUnit.value = response.day_change_eur_min_unit ?? null
    totalGainLossEurMinUnit.value = response.total_gain_loss_eur_min_unit ?? null
    usdToEurRate.value = response.usd_to_eur_rate ?? null
    usdToEurRateDate.value = response.usd_to_eur_rate_date ?? null
    lastUsMarketOpenDate.value = response.last_us_market_open_date ?? null
    selectedAsOf.value = response.as_of_date ?? response.last_us_market_open_date ?? asOf ?? null
    currentPage.value = response.current_page
    lastPage.value = response.last_page
    hasLoadedOnce.value = true
    if (selectedAsOf.value) {
      syncAsOfToUrl(selectedAsOf.value)
    }
  } catch (err: any) {
    console.error('Failed to load portfolio stats:', err)
    const data = err.response?.data
    if (err.response?.status === 400 && data?.error === 'market_closed') {
      dateError.value = marketClosedMessage(data)
    } else {
      fatalError.value = data?.message || 'Failed to load portfolio stats'
    }
  } finally {
    isLoading.value = false
  }
}

const handlePerPageChange = () => {
  loadPage(1)
}

watch(
  () => route.query.asOf,
  (value) => {
    const asOf = parseAsOfQuery(value)
    if (asOf == null || asOf === selectedAsOf.value || isLoading.value) {
      return
    }
    loadPage(1, asOf)
  },
)

onMounted(async () => {
  await loadPage(1, parseAsOfQuery(route.query.asOf) ?? undefined)
})
</script>
