<template>
  <div class="container mx-auto p-8">
    <h1 class="text-4xl font-bold mb-8">{{ $t('configuration.title') }}</h1>

    <div v-if="pageError" class="alert alert-error mb-6">
      <span>{{ pageError }}</span>
    </div>

    <div class="card bg-base-100 shadow-xl max-w-2xl">
      <div class="card-body">
        <h2 class="card-title">{{ $t('configuration.dangerZone.title') }}</h2>
        <p class="text-base-content/70 mb-4">{{ $t('configuration.dangerZone.description') }}</p>
        <button type="button" class="btn btn-error" @click="openConfirmModal">
          {{ $t('configuration.dangerZone.deleteAllButton') }}
        </button>
      </div>
    </div>

    <div class="mt-6">
      <RouterLink :to="{ name: 'dashboard' }" class="btn btn-ghost">
        {{ $t('configuration.backToDashboard') }}
      </RouterLink>
    </div>

    <dialog ref="confirmModal" class="modal">
      <div class="modal-box">
        <h3 class="font-bold text-lg">{{ $t('configuration.modal.title') }}</h3>
        <p class="py-4">{{ $t('configuration.modal.body') }}</p>
        <div class="modal-action">
          <button type="button" class="btn" :disabled="isDeleting" @click="closeConfirmModal">
            {{ $t('configuration.modal.cancel') }}
          </button>
          <button type="button" class="btn btn-error" :disabled="isDeleting" @click="confirmDelete">
            <span v-if="isDeleting" class="loading loading-spinner loading-sm" />
            <span v-else>{{ $t('configuration.modal.confirm') }}</span>
          </button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop">
        <button type="submit">close</button>
      </form>
    </dialog>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { authService } from '../services/authService'

const router = useRouter()
const confirmModal = ref<HTMLDialogElement | null>(null)
const isDeleting = ref(false)
const pageError = ref<string | null>(null)

const openConfirmModal = () => {
  pageError.value = null
  confirmModal.value?.showModal()
}

const closeConfirmModal = () => {
  if (!isDeleting.value) {
    confirmModal.value?.close()
  }
}

const confirmDelete = async () => {
  isDeleting.value = true
  pageError.value = null
  try {
    await authService.deleteAllDegiroTransactions()
    confirmModal.value?.close()
    await router.push({ name: 'dashboard' })
  } catch (err: unknown) {
    console.error('Failed to delete transactions:', err)
    const anyErr = err as { response?: { data?: { message?: string } } }
    pageError.value = anyErr.response?.data?.message || 'Failed to delete transactions'
  } finally {
    isDeleting.value = false
  }
}
</script>
