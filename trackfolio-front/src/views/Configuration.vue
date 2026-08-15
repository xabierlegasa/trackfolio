<template>
  <div class="container mx-auto p-8">
    <h1 class="text-4xl font-bold mb-8">{{ $t('configuration.title') }}</h1>

    <div v-if="pageError" class="alert alert-error mb-6">
      <span>{{ pageError }}</span>
    </div>

    <div v-if="saveSuccess" class="alert alert-success mb-6">
      <span>{{ $t('configuration.leverage.saved') }}</span>
    </div>

    <div class="card bg-base-100 shadow-xl max-w-2xl mb-6">
      <div class="card-body">
        <h2 class="card-title">{{ $t('configuration.leverage.title') }}</h2>
        <p class="text-base-content/70 mb-4">{{ $t('configuration.leverage.description') }}</p>

        <div v-if="isLoadingLeverage" class="flex justify-center py-4">
          <span class="loading loading-spinner loading-md" />
        </div>

        <form v-else class="flex flex-col gap-4" @submit.prevent="saveLeverage">
          <div class="form-control max-w-xs">
            <label class="label" for="leverage-amount">
              <span class="label-text font-medium">{{ $t('configuration.leverage.amountLabel') }}</span>
            </label>
            <div class="join w-full">
              <input
                id="leverage-amount"
                v-model="leverageDraft"
                type="number"
                min="0"
                step="1"
                inputmode="decimal"
                class="input input-bordered join-item w-full"
                :disabled="isSavingLeverage"
                required
              />
              <span class="btn btn-disabled join-item no-animation">€</span>
            </div>
            <label class="label">
              <span class="label-text-alt text-base-content/50">
                {{ $t('configuration.leverage.hint') }}
              </span>
            </label>
          </div>

          <button type="submit" class="btn btn-primary w-fit" :disabled="isSavingLeverage">
            <span v-if="isSavingLeverage" class="loading loading-spinner loading-sm" />
            <span v-else>{{ $t('configuration.leverage.save') }}</span>
          </button>
        </form>

        <div v-if="leverageHistory.length > 0" class="mt-6">
          <h3 class="font-semibold mb-2">{{ $t('configuration.leverage.historyTitle') }}</h3>
          <div class="overflow-x-auto">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>{{ $t('configuration.leverage.historyDate') }}</th>
                  <th class="text-right">{{ $t('configuration.leverage.historyAmount') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in leverageHistory" :key="row.id">
                  <td>{{ formatLeverageDate(row.recorded_at) }}</td>
                  <td class="text-right tabular-nums">
                    {{ formatPricePrefixFromCents(row.amount_eur_min_unit, 'EUR', 0) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
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
        {{ $t('common.backToDashboard') }}
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
import { onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { authService, UserLeverageHistoryItem } from '../services/authService'
import { formatPricePrefixFromCents } from '../utils/numberFormat'

const { locale } = useI18n()
const router = useRouter()
const confirmModal = ref<HTMLDialogElement | null>(null)
const isDeleting = ref(false)
const pageError = ref<string | null>(null)
const saveSuccess = ref(false)

const isLoadingLeverage = ref(true)
const isSavingLeverage = ref(false)
/** Whole euros in the input (no cents). */
const leverageDraft = ref('0')
const leverageHistory = ref<UserLeverageHistoryItem[]>([])

const formatLeverageDate = (iso: string): string => {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) {
    return iso
  }
  const displayLocale = locale.value === 'es' ? 'es-ES' : 'en-GB'
  return new Intl.DateTimeFormat(displayLocale, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

const loadLeverage = async () => {
  isLoadingLeverage.value = true
  try {
    const data = await authService.getLeverage()
    leverageDraft.value = String(Math.round(data.amount_eur_min_unit / 100))
    leverageHistory.value = data.history
  } catch (err: unknown) {
    console.error('Failed to load leverage:', err)
    const anyErr = err as { response?: { data?: { message?: string } } }
    pageError.value = anyErr.response?.data?.message || 'Failed to load leverage'
  } finally {
    isLoadingLeverage.value = false
  }
}

const saveLeverage = async () => {
  isSavingLeverage.value = true
  pageError.value = null
  saveSuccess.value = false
  try {
    const euros = Math.max(0, Math.round(Number(leverageDraft.value) || 0))
    const cents = euros * 100
    await authService.saveLeverage(cents)
    leverageDraft.value = String(euros)
    saveSuccess.value = true
    await loadLeverage()
  } catch (err: unknown) {
    console.error('Failed to save leverage:', err)
    const anyErr = err as { response?: { data?: { message?: string } } }
    pageError.value = anyErr.response?.data?.message || 'Failed to save leverage'
  } finally {
    isSavingLeverage.value = false
  }
}

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

onMounted(async () => {
  await loadLeverage()
})
</script>
