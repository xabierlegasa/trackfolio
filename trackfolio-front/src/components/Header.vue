<template>
  <header class="sticky top-0 z-50 bg-base-100 shadow-md">
    <div class="container mx-auto px-4 py-4 flex items-center justify-between">
      <RouterLink :to="userStore.account ? '/dashboard' : '/'" class="text-2xl font-bold no-underline hover:opacity-80">
        {{ $t('app.name') }}
      </RouterLink>
      <div class="flex items-center gap-4">
        <template v-if="!userStore.account">
          <RouterLink to="/login" class="link link-hover">
            {{ $t('common.login') }}
          </RouterLink>
          <RouterLink to="/register" class="link link-hover">
            {{ $t('common.register') }}
          </RouterLink>
        </template>
        <template v-else>
          <RouterLink to="/account" class="link link-hover font-semibold">
            {{ userStore.account.name }}
          </RouterLink>
        </template>
        <select 
          v-model="selectedLocale" 
          @change="changeLocale"
          class="select select-bordered select-sm"
        >
          <option value="en">English</option>
          <option value="es">Español</option>
        </select>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { useUserStore } from '../stores/userStore'

const { locale } = useI18n()
const userStore = useUserStore()
const selectedLocale = ref(locale.value)

onMounted(async () => {
  selectedLocale.value = locale.value
  
  // Try to fetch account info if not already loaded
  // This will silently fail if user is not authenticated
  if (!userStore.account) {
    try {
      await userStore.fetchAccount()
    } catch (error) {
      // User is not authenticated, which is fine
      // The store will handle the error state
    }
  }
})

const changeLocale = (event: Event) => {
  const target = event.target as HTMLSelectElement
  const newLocale = target.value
  locale.value = newLocale
  localStorage.setItem('locale', newLocale)
}
</script>

