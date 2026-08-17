<template>
  <div class="container mx-auto p-8">
    <h1 class="text-4xl font-bold mb-8">{{ $t('admin.title') }}</h1>

    <div class="card bg-base-100 shadow-xl mb-8">
      <div class="card-body">
        <h2 class="card-title mb-2">{{ $t('admin.featureFlagsTitle') }}</h2>
        <p class="text-sm text-base-content/70 mb-4">{{ $t('admin.featureFlagsHint') }}</p>

        <div v-if="featureError" class="alert alert-error mb-4">
          <span>{{ featureError }}</span>
        </div>

        <div class="flex items-center justify-between gap-4 flex-wrap">
          <div>
            <div class="font-medium">{{ $t('admin.recalculateEvolutionFeature') }}</div>
            <div class="text-xs text-base-content/60 font-mono">
              is_recalculate_evolution_feature_enabled
            </div>
          </div>

          <div class="flex items-center gap-3">
            <span v-if="featureLoading" class="loading loading-spinner loading-sm"></span>
            <span class="text-sm" :class="recalculateEvolutionEnabled ? 'text-success' : 'text-base-content/60'">
              {{ recalculateEvolutionEnabled ? $t('admin.featureEnabled') : $t('admin.featureDisabled') }}
            </span>
            <input
              type="checkbox"
              class="toggle toggle-success"
              :checked="recalculateEvolutionEnabled"
              :disabled="featureLoading || featureSaving"
              @change="onToggleRecalculateEvolution"
            />
          </div>
        </div>
      </div>
    </div>

    <div class="card bg-base-100 shadow-xl">
      <div class="card-body">
        <h2 class="card-title mb-4">{{ $t('admin.processesTitle') }}</h2>

        <div v-if="isLoading" class="flex justify-center py-8">
          <span class="loading loading-spinner loading-lg"></span>
        </div>

        <div v-else-if="error" class="alert alert-error">
          <span>{{ error }}</span>
        </div>

        <template v-else>
          <div v-if="rows.length === 0" class="text-base-content/70">
            {{ $t('admin.noProcesses') }}
          </div>

          <div v-else class="overflow-x-auto">
            <table class="table table-zebra table-sm">
              <thead>
                <tr>
                  <th>id</th>
                  <th>user_id</th>
                  <th>status</th>
                  <th>started_from</th>
                  <th>deleted_snapshots</th>
                  <th>finished_at</th>
                  <th>created_at</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in rows"
                  :key="row.id"
                  class="hover cursor-pointer"
                  @click="goToProcess(row.id)"
                >
                  <td>{{ row.id }}</td>
                  <td>{{ row.user_id }}</td>
                  <td>
                    <span
                      class="inline-flex items-center rounded px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide"
                      :class="statusBadgeClass(row.status)"
                    >
                      {{ row.status }}
                    </span>
                  </td>
                  <td>{{ row.started_from ?? '—' }}</td>
                  <td>{{ row.deleted_snapshots ?? '—' }}</td>
                  <td class="whitespace-nowrap">{{ formatDateTime(row.finished_at) }}</td>
                  <td class="whitespace-nowrap">{{ formatDateTime(row.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="rows.length > 0" class="flex flex-col items-center gap-4 mt-6">
            <div class="flex items-center gap-2">
              <label class="label">
                <span class="label-text">{{ $t('admin.pagination.itemsPerPage') }}:</span>
              </label>
              <select v-model="perPage" class="select select-bordered select-sm" @change="handlePerPageChange">
                <option :value="10">10</option>
                <option :value="20">20</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
              </select>
            </div>
            <div v-if="lastPage > 1" class="join">
              <button
                type="button"
                class="join-item btn"
                :disabled="currentPage === 1"
                @click="loadPage(currentPage - 1)"
              >
                «
              </button>
              <button type="button" class="join-item btn btn-active">
                {{ $t('admin.pagination.page', { current: formatInteger(currentPage), total: formatInteger(lastPage) }) }}
              </button>
              <button
                type="button"
                class="join-item btn"
                :disabled="currentPage === lastPage"
                @click="loadPage(currentPage + 1)"
              >
                »
              </button>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  adminService,
  type SnapshotCalculationProcessRow,
} from '../services/adminService'
import { formatInteger } from '../utils/numberFormat'

const router = useRouter()
const isLoading = ref(false)
const error = ref<string | null>(null)
const rows = ref<SnapshotCalculationProcessRow[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const perPage = ref(20)

const featureLoading = ref(false)
const featureSaving = ref(false)
const featureError = ref<string | null>(null)
const recalculateEvolutionEnabled = ref(false)

function formatDateTime(value: string | null): string {
  if (!value) return '—'
  return value.replace('T', ' ').replace(/\+\d{2}:\d{2}$/, '').replace('Z', '')
}

function statusBadgeClass(status: string): string {
  switch (status) {
    case 'running':
      return 'bg-yellow-300 text-neutral-900 ring-1 ring-yellow-500'
    case 'stopped':
      return 'bg-red-600 text-white'
    case 'completed':
      return 'bg-emerald-600 text-white'
    case 'failed':
      return 'bg-amber-500 text-neutral-900'
    default:
      return 'bg-neutral-500 text-white'
  }
}

function goToProcess(id: number) {
  router.push({ name: 'admin-snapshot-calculation-process', params: { processId: String(id) } })
}

async function loadFeatureFlag() {
  featureLoading.value = true
  featureError.value = null
  try {
    const config = await adminService.getRecalculateEvolutionFeature()
    recalculateEvolutionEnabled.value = config.enabled
  } catch (err: any) {
    featureError.value = err.response?.data?.message || err.message || 'Failed to load feature flag'
  } finally {
    featureLoading.value = false
  }
}

async function onToggleRecalculateEvolution(event: Event) {
  const input = event.target as HTMLInputElement
  const next = input.checked
  const previous = recalculateEvolutionEnabled.value

  recalculateEvolutionEnabled.value = next
  featureSaving.value = true
  featureError.value = null
  try {
    const config = await adminService.setRecalculateEvolutionFeature(next)
    recalculateEvolutionEnabled.value = config.enabled
  } catch (err: any) {
    recalculateEvolutionEnabled.value = previous
    input.checked = previous
    featureError.value = err.response?.data?.message || err.message || 'Failed to update feature flag'
  } finally {
    featureSaving.value = false
  }
}

async function loadPage(page: number) {
  if (page < 1) return

  isLoading.value = true
  error.value = null
  try {
    const response = await adminService.listSnapshotCalculationProcesses(page, perPage.value)
    rows.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (err: any) {
    error.value = err.response?.data?.message || err.message || 'Failed to load processes'
  } finally {
    isLoading.value = false
  }
}

function handlePerPageChange() {
  void loadPage(1)
}

onMounted(() => {
  void loadFeatureFlag()
  void loadPage(1)
})
</script>
