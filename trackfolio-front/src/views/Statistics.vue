<template>
  <div class="container mx-auto p-8">
    <h1 class="text-4xl font-bold mb-6">{{ $t('statistics.title') }}</h1>

    <div role="tablist" class="tabs tabs-boxed mb-6 w-full max-w-2xl">
      <button
        type="button"
        role="tab"
        class="tab flex-1"
        :class="{ 'tab-active': activeTab === 'portfolio' }"
        :aria-selected="activeTab === 'portfolio'"
        @click="setTab('portfolio')"
      >
        {{ $t('statistics.tabs.portfolio') }}
      </button>
      <button
        type="button"
        role="tab"
        class="tab flex-1"
        :class="{ 'tab-active': activeTab === 'evolution' }"
        :aria-selected="activeTab === 'evolution'"
        @click="setTab('evolution')"
      >
        {{ $t('statistics.tabs.evolution') }}
      </button>
      <button
        type="button"
        role="tab"
        class="tab flex-1"
        :class="{ 'tab-active': activeTab === 'trades' }"
        :aria-selected="activeTab === 'trades'"
        @click="setTab('trades')"
      >
        {{ $t('statistics.tabs.trades') }}
      </button>
      <button
        type="button"
        role="tab"
        class="tab flex-1"
        :class="{ 'tab-active': activeTab === 'trade-summary' }"
        :aria-selected="activeTab === 'trade-summary'"
        @click="setTab('trade-summary')"
      >
        {{ $t('statistics.tabs.tradeSummary') }}
      </button>
    </div>

    <div v-show="activeTab === 'portfolio'">
      <PortfolioStats embedded />
    </div>

    <div v-if="visitedEvolution" v-show="activeTab === 'evolution'">
      <Suspense>
        <EvolutionPanel embedded />
        <template #fallback>
          <div class="flex justify-center py-12">
            <span class="loading loading-spinner loading-lg"></span>
          </div>
        </template>
      </Suspense>
    </div>

    <div v-if="visitedTrades" v-show="activeTab === 'trades'">
      <Suspense>
        <TradesPanel embedded />
        <template #fallback>
          <div class="flex justify-center py-12">
            <span class="loading loading-spinner loading-lg"></span>
          </div>
        </template>
      </Suspense>
    </div>

    <div v-if="visitedTradeSummary" v-show="activeTab === 'trade-summary'">
      <Suspense>
        <TradeSummaryPanel embedded />
        <template #fallback>
          <div class="flex justify-center py-12">
            <span class="loading loading-spinner loading-lg"></span>
          </div>
        </template>
      </Suspense>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PortfolioStats from './PortfolioStats.vue'

const EvolutionPanel = defineAsyncComponent(() => import('./PortfolioEvolution.vue'))
const TradesPanel = defineAsyncComponent(() => import('./Trades.vue'))
const TradeSummaryPanel = defineAsyncComponent(() => import('./TradeSummary.vue'))

type StatsTab = 'portfolio' | 'evolution' | 'trades' | 'trade-summary'

const VALID_TABS: StatsTab[] = ['portfolio', 'evolution', 'trades', 'trade-summary']

const route = useRoute()
const router = useRouter()

const parseTab = (value: unknown): StatsTab => {
  if (typeof value === 'string' && VALID_TABS.includes(value as StatsTab)) {
    return value as StatsTab
  }
  return 'portfolio'
}

const activeTab = computed(() => parseTab(route.query.tab))

const visitedEvolution = ref(false)
const visitedTrades = ref(false)
const visitedTradeSummary = ref(false)

watch(
  activeTab,
  (tab) => {
    if (tab === 'evolution') {
      visitedEvolution.value = true
    }
    if (tab === 'trades') {
      visitedTrades.value = true
    }
    if (tab === 'trade-summary') {
      visitedTradeSummary.value = true
    }
  },
  { immediate: true },
)

const setTab = (tab: StatsTab) => {
  if (tab === activeTab.value) {
    return
  }

  const query = { ...route.query }
  if (tab === 'portfolio') {
    delete query.tab
    delete query.year
    delete query.view
  } else {
    query.tab = tab
  }
  if (tab !== 'evolution') {
    delete query.year
    delete query.view
  }

  router.replace({ name: 'statistics', query })
}
</script>
