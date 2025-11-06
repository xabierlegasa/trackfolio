<template>
  <div class="container mx-auto p-8">
    <h1 class="text-4xl font-bold mb-8">{{ $t('statistics.title') }}</h1>
    
    <div class="space-y-6">
      <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <h2 class="card-title">{{ $t('statistics.portfolio.title') }}</h2>
          <p class="text-base-content/70 mb-4">{{ $t('statistics.portfolio.description') }}</p>
          <RouterLink :to="{ name: 'portfolio' }" class="btn btn-primary">
            {{ $t('statistics.portfolio.button') }}
          </RouterLink>
        </div>
      </div>
      
      <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <h2 class="card-title">{{ $t('statistics.trades.title') }}</h2>
          <p class="text-base-content/70 mb-4">{{ $t('statistics.trades.description') }}</p>
          <RouterLink :to="{ name: 'trades' }" class="btn btn-primary">
            {{ $t('statistics.trades.button') }}
          </RouterLink>
        </div>
      </div>
      
      <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
          <h2 class="card-title">{{ $t('statistics.tradesSummary.title') }}</h2>
          <p class="text-base-content/70 mb-4">{{ $t('statistics.tradesSummary.description') }}</p>
          <RouterLink :to="{ name: 'trade-summary' }" class="btn btn-primary">
            {{ $t('statistics.tradesSummary.button') }}
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

