<template>
  <div class="container mx-auto p-8 max-w-md">
    <h1 class="text-4xl font-bold mb-8 text-center">{{ $t('register.title') }}</h1>
    
    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Email Field -->
      <div class="form-control">
        <label class="label" for="email">
          <span class="label-text">{{ $t('register.email') }}</span>
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

      <!-- Name Field -->
      <div class="form-control">
        <label class="label" for="name">
          <span class="label-text">{{ $t('register.name') }}</span>
        </label>
        <input
          id="name"
          v-model="form.name"
          type="text"
          autocomplete="name"
          required
          class="input input-bordered"
          :class="{ 'input-error': errors.name }"
          @blur="validateName"
        />
        <label v-if="errors.name" class="label">
          <span class="label-text-alt text-error">{{ errors.name }}</span>
        </label>
      </div>

      <!-- Password Field -->
      <div class="form-control">
        <label class="label" for="password">
          <span class="label-text">{{ $t('register.password') }}</span>
        </label>
        <div class="relative">
          <input
            id="password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="new-password"
            required
            class="input input-bordered w-full pr-10"
            :class="{ 'input-error': errors.password || !isPasswordValid }"
            @input="validatePassword"
            @blur="validatePassword"
          />
          <button
            type="button"
            class="absolute right-3 top-1/2 -translate-y-1/2 btn btn-ghost btn-sm btn-circle"
            @click="showPassword = !showPassword"
            :aria-label="showPassword ? $t('register.hidePassword') : $t('register.showPassword')"
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
        
        <!-- Password Rules -->
        <div v-if="form.password && !isPasswordValid" class="mt-2 text-sm text-error">
          <p class="font-semibold mb-2">{{ $t('register.passwordRules.title') }}</p>
          <ul class="list-disc list-inside space-y-1">
            <li :class="{ 'text-error': !passwordRules.minLength }">
              {{ $t('register.passwordRules.minLength') }}
            </li>
            <li :class="{ 'text-error': !passwordRules.uppercase }">
              {{ $t('register.passwordRules.uppercase') }}
            </li>
            <li :class="{ 'text-error': !passwordRules.lowercase }">
              {{ $t('register.passwordRules.lowercase') }}
            </li>
            <li :class="{ 'text-error': !passwordRules.numberOrSpecial }">
              {{ $t('register.passwordRules.numberOrSpecial') }}
            </li>
          </ul>
        </div>
      </div>

      <!-- Privacy Policy Checkbox -->
      <div class="form-control">
        <label class="label cursor-pointer justify-start gap-3">
          <input
            v-model="form.privacy_policy_accepted"
            type="checkbox"
            class="checkbox checkbox-primary"
            :class="{ 'checkbox-error': errors.privacy_policy_accepted }"
          />
          <span class="label-text">{{ $t('register.privacyPolicy') }}</span>
        </label>
        <label v-if="errors.privacy_policy_accepted" class="label">
          <span class="label-text-alt text-error">{{ errors.privacy_policy_accepted }}</span>
        </label>
      </div>

      <!-- Terms and Conditions Checkbox (hidden but required by API) -->
      <input
        v-model="form.terms_conditions_accepted"
        type="checkbox"
        class="hidden"
        :checked="form.terms_conditions_accepted"
      />

      <!-- Submit Button -->
      <button
        type="submit"
        class="btn btn-primary w-full"
        :disabled="isLoading || !isFormValid"
      >
        <span v-if="isLoading" class="loading loading-spinner loading-sm"></span>
        <span v-else>{{ $t('register.submit') }}</span>
      </button>

      <!-- Error Message -->
      <div v-if="submitError" class="alert alert-error">
        <span>{{ submitError }}</span>
      </div>

      <!-- Success Message -->
      <div v-if="isSuccess" class="alert alert-success">
        <span>{{ $t('register.success.message') }}</span>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { authService, type RegisterData } from '../services/authService'
import { useRouter } from 'vue-router'
import { useUserStore } from '../stores/userStore'

const { t } = useI18n()
const router = useRouter()
const userStore = useUserStore()

const form = reactive<RegisterData & { name: string }>({
  email: '',
  name: '',
  password: '',
  privacy_policy_accepted: false,
  terms_conditions_accepted: false
})

const errors = reactive({
  email: '',
  name: '',
  password: '',
  privacy_policy_accepted: '',
  terms_conditions_accepted: ''
})

const isLoading = ref(false)
const submitError = ref('')
const isSuccess = ref(false)
const showPassword = ref(false)

// Password validation rules
const passwordRules = reactive({
  minLength: false,
  uppercase: false,
  lowercase: false,
  numberOrSpecial: false
})

const isPasswordValid = computed(() => {
  return Object.values(passwordRules).every(rule => rule === true)
})

const validatePassword = () => {
  const password = form.password
  
  if (!password) {
    errors.password = t('register.errors.passwordRequired')
    return false
  }

  // Check each rule
  passwordRules.minLength = password.length >= 8
  passwordRules.uppercase = /[A-Z]/.test(password)
  passwordRules.lowercase = /[a-z]/.test(password)
  passwordRules.numberOrSpecial = /[0-9]/.test(password) || /[^A-Za-z0-9]/.test(password)

  if (!isPasswordValid.value) {
    errors.password = t('register.errors.passwordRequired')
    return false
  }

  errors.password = ''
  return true
}

const validateEmail = () => {
  if (!form.email) {
    errors.email = t('register.errors.emailRequired')
    return false
  }

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (!emailRegex.test(form.email)) {
    errors.email = t('register.errors.emailInvalid')
    return false
  }

  errors.email = ''
  return true
}

const validateName = () => {
  if (!form.name.trim()) {
    errors.name = t('register.errors.nameRequired')
    return false
  }

  errors.name = ''
  return true
}

const validatePrivacyPolicy = () => {
  if (!form.privacy_policy_accepted) {
    errors.privacy_policy_accepted = t('register.errors.privacyRequired')
    return false
  }

  errors.privacy_policy_accepted = ''
  return true
}

const isFormValid = computed(() => {
  return (
    form.email &&
    form.name.trim() &&
    form.password &&
    isPasswordValid.value &&
    form.privacy_policy_accepted &&
    !errors.email &&
    !errors.name &&
    !errors.password
  )
})

// Set terms_conditions_accepted to true (since privacy policy checkbox also accepts terms)
form.terms_conditions_accepted = true

const handleSubmit = async () => {
  // Clear previous errors
  submitError.value = ''
  isSuccess.value = false

  // Validate all fields
  const isEmailValid = validateEmail()
  const isNameValid = validateName()
  const isPasswordValidCheck = validatePassword()
  const isPrivacyValid = validatePrivacyPolicy()

  if (!isEmailValid || !isNameValid || !isPasswordValidCheck || !isPrivacyValid) {
    return
  }

  // Set terms to true (same as privacy policy for now)
  form.terms_conditions_accepted = true

  isLoading.value = true

  try {
    await authService.register(form)
    
    // Fetch user account info and update store
    await userStore.fetchAccount()
    
    isSuccess.value = true
    
    // Redirect to dashboard after 2 seconds
    setTimeout(() => {
      router.push('/dashboard')
    }, 2000)
  } catch (error: any) {
    if (error.response?.data?.errors) {
      // Handle validation errors from API
      const apiErrors = error.response.data.errors
      
      if (apiErrors.email) {
        if (apiErrors.email.includes('already registered') || apiErrors.email.includes('unique')) {
          errors.email = t('register.errors.emailExists')
        } else {
          errors.email = apiErrors.email[0] || t('register.errors.emailInvalid')
        }
      }
      
      if (apiErrors.password) {
        errors.password = apiErrors.password[0] || t('register.errors.passwordRequired')
      }
      
      if (apiErrors.privacy_policy_accepted) {
        errors.privacy_policy_accepted = apiErrors.privacy_policy_accepted[0] || t('register.errors.privacyRequired')
      }
      
      submitError.value = error.response.data.message || t('register.errors.registrationFailed')
    } else if (error.response?.data?.message) {
      submitError.value = error.response.data.message
    } else {
      submitError.value = t('register.errors.registrationFailed')
    }
  } finally {
    isLoading.value = false
  }
}
</script>
