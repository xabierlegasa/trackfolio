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

    <div class="card bg-base-100 shadow-xl mb-6">
      <div class="card-body flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
        <div class="form-control flex-1 min-w-[160px]">
          <label class="label py-0">
            <span class="label-text font-semibold">{{ $t('admin.filter.isin') }}</span>
          </label>
          <input
            v-model="isinDraft"
            type="search"
            class="input input-bordered input-sm w-full font-mono"
            :placeholder="$t('admin.filter.isinPlaceholder')"
            @keydown.enter.prevent="applyFilters"
          />
        </div>
        <div class="form-control flex-1 min-w-[160px]">
          <label class="label py-0">
            <span class="label-text font-semibold">{{ $t('admin.filter.symbol') }}</span>
          </label>
          <input
            v-model="symbolDraft"
            type="search"
            class="input input-bordered input-sm w-full"
            :placeholder="$t('admin.filter.symbolPlaceholder')"
            @keydown.enter.prevent="applyFilters"
          />
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn btn-primary btn-sm" @click="applyFilters">
            {{ $t('admin.filter.apply') }}
          </button>
          <button
            type="button"
            class="btn btn-ghost btn-sm"
            :disabled="!isinDraft.trim() && !symbolDraft.trim() && !appliedIsin && !appliedSymbol"
            @click="clearFilters"
          >
            {{ $t('admin.filter.clear') }}
          </button>
        </div>
      </div>
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
            {{ hasActiveFilters ? $t('admin.noMatchingLogs') : $t('admin.noLogs') }}
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
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  adminService,
  type SnapshotCalculationProcessLogRow,
  type SnapshotCalculationProcessRow,
} from '../services/adminService'
import { formatInteger } from '../utils/numberFormat'

const route = useRoute()
const router = useRouter()
const processId = ref(Number(route.params.processId))
const isLoading = ref(false)
const error = ref<string | null>(null)
const process = ref<SnapshotCalculationProcessRow | null>(null)
const rows = ref<SnapshotCalculationProcessLogRow[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const perPage = ref(20)
const isinDraft = ref('')
const symbolDraft = ref('')
const appliedIsin = ref('')
const appliedSymbol = ref('')
let suppressQueryWatch = false

const hasActiveFilters = computed(() => appliedIsin.value !== '' || appliedSymbol.value !== '')

function parsePageQuery(raw: unknown): number {
  const value = Array.isArray(raw) ? raw[0] : raw
  const page = Number(value)
  return Number.isInteger(page) && page >= 1 ? page : 1
}

function parsePerPageQuery(raw: unknown): number {
  const value = Array.isArray(raw) ? raw[0] : raw
  const n = Number(value)
  return [10, 20, 50, 100].includes(n) ? n : 20
}

function parseStringQuery(raw: unknown): string {
  const value = Array.isArray(raw) ? raw[0] : raw
  return typeof value === 'string' ? value.trim() : ''
}

function formatDateTime(value: string | null): string {
  if (!value) return '—'
  return value.replace('T', ' ').replace(/\+\d{2}:\d{2}$/, '').replace('Z', '')
}

function providerRequestHref(id: number): string {
  return `/admin/provider-requests/${id}`
}

async function syncQueryToUrl(page: number, itemsPerPage: number) {
  suppressQueryWatch = true
  try {
    const query: Record<string, string> = {
      page: String(page),
      per_page: String(itemsPerPage),
    }
    if (appliedIsin.value) {
      query.isin = appliedIsin.value
    }
    if (appliedSymbol.value) {
      query.symbol = appliedSymbol.value
    }
    await router.replace({
      name: 'admin-snapshot-calculation-process',
      params: { processId: String(processId.value) },
      query,
    })
  } finally {
    suppressQueryWatch = false
  }
}

async function loadPage(page: number, updateUrl = true) {
  if (page < 1) return

  isLoading.value = true
  error.value = null
  try {
    const response = await adminService.listSnapshotCalculationProcessLogs(
      processId.value,
      page,
      perPage.value,
      {
        isin: appliedIsin.value || undefined,
        symbol: appliedSymbol.value || undefined,
      },
    )
    process.value = response.process
    rows.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
    if (updateUrl) {
      await syncQueryToUrl(currentPage.value, perPage.value)
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || err.message || 'Failed to load logs'
  } finally {
    isLoading.value = false
  }
}

function handlePerPageChange() {
  void loadPage(1)
}

function applyFilters() {
  appliedIsin.value = isinDraft.value.trim()
  appliedSymbol.value = symbolDraft.value.trim()
  void loadPage(1)
}

function clearFilters() {
  isinDraft.value = ''
  symbolDraft.value = ''
  appliedIsin.value = ''
  appliedSymbol.value = ''
  void loadPage(1)
}

function applyFiltersFromRoute() {
  appliedIsin.value = parseStringQuery(route.query.isin)
  appliedSymbol.value = parseStringQuery(route.query.symbol)
  isinDraft.value = appliedIsin.value
  symbolDraft.value = appliedSymbol.value
  perPage.value = parsePerPageQuery(route.query.per_page)
}

watch(
  () => route.params.processId,
  (value) => {
    processId.value = Number(value)
    applyFiltersFromRoute()
    void loadPage(parsePageQuery(route.query.page))
  },
)

watch(
  () => [route.query.page, route.query.per_page, route.query.isin, route.query.symbol] as const,
  () => {
    if (suppressQueryWatch) return
    const page = parsePageQuery(route.query.page)
    const items = parsePerPageQuery(route.query.per_page)
    const isin = parseStringQuery(route.query.isin)
    const symbol = parseStringQuery(route.query.symbol)
    if (
      page === currentPage.value
      && items === perPage.value
      && isin === appliedIsin.value
      && symbol === appliedSymbol.value
      && rows.value.length > 0
    ) {
      return
    }
    perPage.value = items
    appliedIsin.value = isin
    appliedSymbol.value = symbol
    isinDraft.value = isin
    symbolDraft.value = symbol
    void loadPage(page, false)
  },
)

onMounted(() => {
  applyFiltersFromRoute()
  void loadPage(parsePageQuery(route.query.page))
})
</script>
