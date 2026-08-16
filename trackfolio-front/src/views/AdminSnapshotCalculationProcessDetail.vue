<template>
  <div class="container mx-auto p-8">
    <div class="mb-6 flex flex-wrap items-center gap-3">
      <RouterLink :to="{ name: 'admin' }" class="link link-hover text-sm">
        ← {{ $t('admin.backToAdmin') }}
      </RouterLink>
    </div>

    <h1 class="text-3xl font-bold mb-2">{{ $t('admin.processDetailTitle', { id: processId }) }}</h1>

    <div v-if="process" class="mb-6 text-sm text-base-content/70 flex flex-wrap gap-x-4 gap-y-1">
      <span>user_id={{ process.user_id }}</span>
      <span>status={{ process.status }}</span>
      <span>started_from={{ process.started_from ?? '—' }}</span>
      <span>deleted_snapshots={{ process.deleted_snapshots ?? '—' }}</span>
      <span>finished_at={{ formatDateTime(process.finished_at) }}</span>
      <span>created_at={{ formatDateTime(process.created_at) }}</span>
    </div>

    <div class="card bg-base-100 shadow-xl">
      <div class="card-body">
        <h2 class="card-title mb-4">{{ $t('admin.logsTitle') }}</h2>

        <div v-if="isLoading" class="flex justify-center py-8">
          <span class="loading loading-spinner loading-lg"></span>
        </div>

        <div v-else-if="error" class="alert alert-error">
          <span>{{ error }}</span>
        </div>

        <template v-else>
          <div v-if="rows.length === 0" class="text-base-content/70">
            {{ $t('admin.noLogs') }}
          </div>

          <div v-else class="overflow-x-auto">
            <table class="table table-zebra table-sm">
              <thead>
                <tr>
                  <th>id</th>
                  <th>description</th>
                  <th>date_processed</th>
                  <th>isin</th>
                  <th>symbol</th>
                  <th>request</th>
                  <th>created_at</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in rows" :key="row.id">
                  <td>{{ row.id }}</td>
                  <td class="max-w-xl whitespace-pre-wrap break-words align-top">{{ row.description }}</td>
                  <td class="whitespace-nowrap">{{ row.date_processed ?? '—' }}</td>
                  <td>{{ row.isin ?? '—' }}</td>
                  <td>{{ row.symbol ?? '—' }}</td>
                  <td>
                    <a
                      v-if="row.provider_request_id"
                      :href="providerRequestHref(row.provider_request_id)"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="link link-primary"
                      @click.stop
                    >
                      {{ row.provider_request_id }}
                    </a>
                    <span v-else>—</span>
                  </td>
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
import { onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  adminService,
  type SnapshotCalculationProcessLogRow,
  type SnapshotCalculationProcessRow,
} from '../services/adminService'
import { formatInteger } from '../utils/numberFormat'

const route = useRoute()
const processId = ref(Number(route.params.processId))
const isLoading = ref(false)
const error = ref<string | null>(null)
const process = ref<SnapshotCalculationProcessRow | null>(null)
const rows = ref<SnapshotCalculationProcessLogRow[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const perPage = ref(20)

function formatDateTime(value: string | null): string {
  if (!value) return '—'
  return value.replace('T', ' ').replace(/\+\d{2}:\d{2}$/, '').replace('Z', '')
}

function providerRequestHref(id: number): string {
  return `/admin/provider-requests/${id}`
}

async function loadPage(page: number) {
  if (page < 1) return

  isLoading.value = true
  error.value = null
  try {
    const response = await adminService.listSnapshotCalculationProcessLogs(
      processId.value,
      page,
      perPage.value,
    )
    process.value = response.process
    rows.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (err: any) {
    error.value = err.response?.data?.message || err.message || 'Failed to load logs'
  } finally {
    isLoading.value = false
  }
}

function handlePerPageChange() {
  void loadPage(1)
}

watch(
  () => route.params.processId,
  (value) => {
    processId.value = Number(value)
    void loadPage(1)
  },
)

onMounted(() => {
  void loadPage(1)
})
</script>
