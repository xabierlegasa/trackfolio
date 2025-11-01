<template>
  <div class="container mx-auto p-8 max-w-md">
    <h1 class="text-4xl font-bold mb-8 text-center">{{ $t('login.title') }}</h1>
    
    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Email Field -->
      <div class="form-control">
        <label class="label" for="email">
          <span class="label-text">{{ $t('login.email') }}</span>
        </label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          autocomplete="email"
          required
          class="input input-bordered"
          :class="{ 'input-error': errors.email }"
          @blur="validateEmail"
        />
        <label v-if="errors.email" class="label">
          <span class="label-text-alt text-error">{{ errors.email }}</span>
        </label>
      </div>

      <!-- Password Field -->
      <div class="form-control">
        <label class="label" for="password">
          <span class="label-text">{{ $t('login.password') }}</span>
        </label>
        <div class="relative">
          <input
            id="password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="current-password"
            required
            class="input input-bordered w-full pr-10"
            :class="{ 'input-error': errors.password }"
            @blur="validatePassword"
          />
          <button
            type="button"
            class="absolute right-3 top-1/2 -translate-y-1/2 btn btn-ghost btn-sm btn-circle"
            @click="showPassword = !showPassword"
            :aria-label="showPassword ? $t('login.hidePassword') : $t('login.showPassword')"
          >
            <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 11-4.243-4.243m4.242 4.242L9.88 9.88" />
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </button>
        </div>
        <label v-if="errors.password" class="label">
          <span class="label-text-alt text-error">{{ errors.password }}</span>
        </label>
      </div>

      <!-- Submit Button -->
      <button
        type="submit"
        class="btn btn-primary w-full"
        :disabled="isLoading || !isFormValid"
      >
        <span v-if="isLoading" class="loading loading-spinner loading-sm"></span>
        <span v-else>{{ $t('login.submit') }}</span>
      </button>

      <!-- Error Message -->
      <div v-if="submitError" class="alert alert-error">
        <span>{{ submitError }}</span>
      </div>
    </form>

    <!-- Link to Register -->
    <div class="mt-6 text-center">
      <p class="text-sm">
        {{ $t('login.noAccount') }}
        <RouterLink to="/register" class="link link-primary">
          {{ $t('login.signUp') }}
        </RouterLink>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { authService, type LoginData } from '../services/authService'
import { useUserStore } from '../stores/userStore'

const { t } = useI18n()
const router = useRouter()
const userStore = useUserStore()

const form = reactive<LoginData>({
  email: '',
  password: ''
})

const errors = reactive({
  email: '',
  password: ''
})

const isLoading = ref(false)
const submitError = ref('')
const showPassword = ref(false)

const validateEmail = () => {
  if (!form.email) {
    errors.email = t('login.errors.emailRequired')
    return false
  }

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (!emailRegex.test(form.email)) {
    errors.email = t('login.errors.emailInvalid')
    return false
  }

  errors.email = ''
  return true
}

const validatePassword = () => {
  if (!form.password) {
    errors.password = t('login.errors.passwordRequired')
    return false
  }

  errors.password = ''
  return true
}

const isFormValid = computed(() => {
  return (
    form.email &&
    form.password &&
    !errors.email &&
    !errors.password
  )
})

const handleSubmit = async () => {
  // Clear previous errors
  submitError.value = ''

  // Validate all fields
  const isEmailValid = validateEmail()
  const isPasswordValid = validatePassword()

  if (!isEmailValid || !isPasswordValid) {
    return
  }

  isLoading.value = true

  try {
    await authService.login(form)
    
    // Fetch user account info and update store
    await userStore.fetchAccount()
    
    // Redirect to dashboard on success
    router.push('/dashboard')
  } catch (error: any) {
    if (error.response?.data?.errors) {
      // Handle validation errors from API
      const apiErrors = error.response.data.errors
      
      if (apiErrors.email) {
        errors.email = apiErrors.email[0] || t('login.errors.emailInvalid')
      }
      
      if (apiErrors.password) {
        errors.password = apiErrors.password[0] || t('login.errors.passwordRequired')
      }
      
      submitError.value = error.response.data.message || t('login.errors.loginFailed')
    } else if (error.response?.data?.message) {
      submitError.value = error.response.data.message
    } else {
      submitError.value = t('login.errors.loginFailed')
    }
  } finally {
    isLoading.value = false
  }
}
</script>

