<template>
  <div
    v-if="showBack"
    class="container mx-auto px-4 pt-3 pb-0"
  >
    <RouterLink
      :to="{ name: 'dashboard' }"
      class="inline-flex items-center gap-1 text-sm text-base-content/45 hover:text-base-content/80 transition-colors no-underline"
    >
      <span aria-hidden="true">←</span>
      <span>{{ $t('common.backToDashboard') }}</span>
    </RouterLink>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useUserStore } from '../stores/userStore'

const route = useRoute()
const userStore = useUserStore()

const hideOn = new Set(['dashboard', 'login', 'register'])

const showBack = computed(
  () => Boolean(userStore.account) && !hideOn.has(String(route.name ?? '')),
)
</script>
