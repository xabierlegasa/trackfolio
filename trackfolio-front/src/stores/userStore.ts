import { defineStore } from 'pinia'
import { ref } from 'vue'
import { authService, type AccountResponse } from '../services/authService'

export const useUserStore = defineStore('user', () => {
  const account = ref<AccountResponse | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  async function fetchAccount() {
    isLoading.value = true
    error.value = null
    try {
      account.value = await authService.getAccount()
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to load account information'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  function clearAccount() {
    account.value = null
    error.value = null
  }

  return {
    account,
    isLoading,
    error,
    fetchAccount,
    clearAccount
  }
})

