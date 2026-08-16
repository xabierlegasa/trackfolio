<template>
  <div class="container mx-auto p-8 max-w-5xl">
    <div class="mb-6">
      <RouterLink :to="{ name: 'admin' }" class="link link-hover text-sm">
        ← {{ $t('admin.backToAdmin') }}
      </RouterLink>
    </div>

    <h1 class="text-3xl font-bold mb-6">{{ $t('admin.providerRequestTitle', { id: providerRequestId }) }}</h1>

    <div v-if="isLoading" class="flex justify-center py-12">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <div v-else-if="error" class="alert alert-error">
      <span>{{ error }}</span>
    </div>

    <div v-else-if="row" class="card bg-base-100 shadow-xl">
      <div class="card-body p-0">
        <div class="overflow-x-auto">
          <table class="table">
            <thead>
              <tr>
                <th class="w-48">{{ $t('admin.providerRequestAttr') }}</th>
                <th>{{ $t('admin.providerRequestValue') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="field in fields" :key="field.key">
                <td class="font-semibold align-top whitespace-nowrap">{{ field.key }}</td>
                <td class="align-top">
                  <pre
                    v-if="field.key === 'response_body'"
                    class="whitespace-pre-wrap break-words text-xs bg-base-200 rounded-lg p-3 max-h-[70vh] overflow-auto font-mono"
                  >{{ field.value }}</pre>
                  <span
                    v-else-if="field.key === 'url'"
                    class="break-all font-mono text-xs"
                  >{{ field.value }}</span>
                  <span v-else>{{ field.value }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { adminService, type ProviderRequestRow } from '../services/adminService'

const route = useRoute()
const providerRequestId = ref(Number(route.params.providerRequestId))
const isLoading = ref(false)
const error = ref<string | null>(null)
const row = ref<ProviderRequestRow | null>(null)

function formatDateTime(value: string | null): string {
  if (!value) return '—'
  return value.replace('T', ' ').replace(/\+\d{2}:\d{2}$/, '').replace('Z', '')
}

function formatResponseBody(body: string | null): string {
  if (body === null || body === '') return '—'
  try {
    return JSON.stringify(JSON.parse(body), null, 2)
  } catch {
    return body
  }
}

const fields = computed(() => {
  if (!row.value) return []
  const data = row.value
  return [
    { key: 'id', value: String(data.id) },
    { key: 'provider', value: data.provider },
    { key: 'call_type', value: data.call_type },
    { key: 'method', value: data.method },
    { key: 'url', value: data.url },
    { key: 'http_status', value: data.http_status === null ? '—' : String(data.http_status) },
    { key: 'response_body', value: formatResponseBody(data.response_body) },
    { key: 'duration_ms', value: data.duration_ms === null ? '—' : String(data.duration_ms) },
    { key: 'success', value: data.success ? 'true' : 'false' },
    { key: 'error_message', value: data.error_message ?? '—' },
    { key: 'created_at', value: formatDateTime(data.created_at) },
  ]
})

async function load() {
  isLoading.value = true
  error.value = null
  try {
    row.value = await adminService.getProviderRequest(providerRequestId.value)
  } catch (err: any) {
    error.value = err.response?.data?.message || err.message || 'Failed to load provider request'
  } finally {
    isLoading.value = false
  }
}

watch(
  () => route.params.providerRequestId,
  (value) => {
    providerRequestId.value = Number(value)
    void load()
  },
)

onMounted(() => {
  void load()
})
</script>
