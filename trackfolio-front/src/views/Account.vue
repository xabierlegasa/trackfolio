<template>
  <div class="container mx-auto p-8 max-w-md">
    <h1 class="text-4xl font-bold mb-8 text-center">{{ $t('account.title') }}</h1>
    
    <div v-if="userStore.isLoading" class="flex justify-center">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <div v-else-if="userStore.error" class="alert alert-error">
      <span>{{ userStore.error }}</span>
    </div>

    <div v-else-if="userStore.account" class="card bg-base-100 shadow-xl">
      <div class="card-body">
        <h2 class="card-title">{{ $t('account.accountInfo') }}</h2>
        <div class="space-y-4">
          <div>
            <label class="label">
              <span class="label-text font-semibold">{{ $t('account.name') }}</span>
            </label>
            <p class="text-lg">{{ userStore.account.name }}</p>
          </div>
          <div>
            <label class="label">
              <span class="label-text font-semibold">{{ $t('account.email') }}</span>
            </label>
            <p class="text-lg">{{ userStore.account.email }}</p>
          </div>
        </div>
        
        <div class="mt-6 text-right">
          <a
            @click.prevent="handleLogout"
            href="#"
            class="link link-hover"
            :class="{ 'opacity-50 pointer-events-none': isLoggingOut }"
          >
            <span v-if="isLoggingOut" class="loading loading-spinner loading-sm inline-block mr-2"></span>
            {{ $t('account.signOut') }}
          </a>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '../stores/userStore'
import { authService } from '../services/authService'

const router = useRouter()
const userStore = useUserStore()
const isLoggingOut = ref(false)

onMounted(async () => {
  // Fetch account info if not already loaded
  if (!userStore.account) {
    try {
      await userStore.fetchAccount()
    } catch (error) {
      // If user is not authenticated, redirect to login
      router.push('/login')
    }
  }
})

const handleLogout = async () => {
  isLoggingOut.value = true
  
  try {
    await authService.logout()
    
    // Clear user store
    userStore.clearAccount()
    
    // Redirect to home
    router.push('/')
  } catch (error) {
    console.error('Logout failed:', error)
    // Even if logout fails, clear local state and redirect
    userStore.clearAccount()
    router.push('/')
  } finally {
    isLoggingOut.value = false
  }
}
</script>

