<template>
  <div class="container mx-auto p-8">
    <h1 class="text-4xl font-bold mb-2">{{ $t('taxReturnYear.title', { year: yearNum }) }}</h1>

    <div class="flex flex-wrap gap-2 mb-6">
      <RouterLink :to="{ name: 'tax-return-years' }" class="btn btn-ghost btn-sm">
        {{ $t('taxReturnYear.backYears') }}
      </RouterLink>
      <RouterLink :to="{ name: 'dashboard' }" class="btn btn-ghost btn-sm">
        {{ $t('taxReturnYear.backDashboard') }}
      </RouterLink>
    </div>

    <div v-if="isLoading" class="flex justify-center">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <div v-else-if="error" class="alert alert-error">
      <span>{{ error }}</span>
    </div>

    <div v-else class="card bg-base-100 shadow-xl">
      <div class="card-body">
        <div v-if="lines.length === 0" class="text-base-content/70 mb-4">
          {{ $t('taxReturnYear.noSales') }}
        </div>

        <div v-else>
          <div class="flex justify-end mb-2">
            <button type="button" class="btn btn-outline btn-sm" @click="exportExerciseTableCsv">
              {{ $t('taxReturnYear.exportCsv') }}
            </button>
          </div>
          <div class="overflow-x-auto">
          <table class="table table-zebra min-w-full">
            <thead>
              <tr>
                <th>
                  <button
                    type="button"
                    class="flex items-center gap-1 hover:opacity-70 font-semibold"
                    @click="toggleSort('product')"
                  >
                    {{ $t('taxReturnYear.table.product') }}
                    <span v-if="sortBy === 'product'" class="text-xs">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                  </button>
                </th>
                <th>
                  <button
                    type="button"
                    class="flex items-center gap-1 hover:opacity-70 font-semibold"
                    @click="toggleSort('isin')"
                  >
                    {{ $t('taxReturnYear.table.isin') }}
                    <span v-if="sortBy === 'isin'" class="text-xs">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                  </button>
                </th>
                <th class="text-right whitespace-nowrap">
                  <button
                    type="button"
                    class="flex items-center gap-1 hover:opacity-70 ml-auto font-semibold"
                    @click="toggleSort('acquisition_value_cents')"
                  >
                    {{ $t('taxReturnYear.table.acquisitionValue') }}
                    <span v-if="sortBy === 'acquisition_value_cents'" class="text-xs">{{
                      sortOrder === 'asc' ? '↑' : '↓'
                    }}</span>
                  </button>
                </th>
                <th class="text-right whitespace-nowrap">
                  <button
                    type="button"
                    class="flex items-center gap-1 hover:opacity-70 ml-auto font-semibold"
                    @click="toggleSort('acquisition_commissions_cents')"
                  >
                    {{ $t('taxReturnYear.table.acquisitionCommissions') }}
                    <span v-if="sortBy === 'acquisition_commissions_cents'" class="text-xs">{{
                      sortOrder === 'asc' ? '↑' : '↓'
                    }}</span>
                  </button>
                </th>
                <th class="text-right whitespace-nowrap">
                  <button
                    type="button"
                    class="flex items-center gap-1 hover:opacity-70 ml-auto font-semibold"
                    @click="toggleSort('acquisition_net_cents')"
                  >
                    {{ $t('taxReturnYear.table.acquisitionNet') }}
                    <span v-if="sortBy === 'acquisition_net_cents'" class="text-xs">{{
                      sortOrder === 'asc' ? '↑' : '↓'
                    }}</span>
                  </button>
                </th>
                <th class="text-right whitespace-nowrap">
                  <button
                    type="button"
                    class="flex items-center gap-1 hover:opacity-70 ml-auto font-semibold"
                    @click="toggleSort('transmission_value_cents')"
                  >
                    {{ $t('taxReturnYear.table.transmissionValue') }}
                    <span v-if="sortBy === 'transmission_value_cents'" class="text-xs">{{
                      sortOrder === 'asc' ? '↑' : '↓'
                    }}</span>
                  </button>
                </th>
                <th class="text-right whitespace-nowrap">
                  <button
                    type="button"
                    class="flex items-center gap-1 hover:opacity-70 ml-auto font-semibold"
                    @click="toggleSort('transmission_commissions_cents')"
                  >
                    {{ $t('taxReturnYear.table.transmissionCommissions') }}
                    <span v-if="sortBy === 'transmission_commissions_cents'" class="text-xs">{{
                      sortOrder === 'asc' ? '↑' : '↓'
                    }}</span>
                  </button>
                </th>
                <th class="text-right whitespace-nowrap">
                  <button
                    type="button"
                    class="flex items-center gap-1 hover:opacity-70 ml-auto font-semibold"
                    @click="toggleSort('transmission_net_cents')"
                  >
                    {{ $t('taxReturnYear.table.transmissionNet') }}
                    <span v-if="sortBy === 'transmission_net_cents'" class="text-xs">{{
                      sortOrder === 'asc' ? '↑' : '↓'
                    }}</span>
                  </button>
                </th>
                <th class="text-right whitespace-nowrap">
                  <button
                    type="button"
                    class="flex items-center gap-1 hover:opacity-70 ml-auto font-semibold"
                    @click="toggleSort('net_gain_cents')"
                  >
                    {{ $t('taxReturnYear.table.netGain') }}
                    <span v-if="sortBy === 'net_gain_cents'" class="text-xs">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                  </button>
                </th>
                <th class="whitespace-nowrap">{{ $t('taxReturnYear.table.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in sortedLines" :key="line.isin">
                <td class="whitespace-nowrap max-w-[min(28rem,55vw)]">
                  <div class="inline-flex items-center gap-0.5 min-w-0">
                    <span class="truncate" :title="line.product">{{ line.product }}</span>
                    <button
                      type="button"
                      class="btn btn-ghost btn-xs btn-square h-7 w-7 min-h-7 min-w-7 p-0 shrink-0"
                      :aria-label="$t('taxReturnYear.copyProduct')"
                      @click="copyProductName(line)"
                    >
                      <span
                        v-if="copiedClipKey === clipKey(line.isin, 'product')"
                        class="text-success text-sm leading-none"
                      >✓</span>
                      <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-3.5 w-3.5 opacity-70"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                        />
                      </svg>
                    </button>
                  </div>
                </td>
                <td class="whitespace-nowrap font-mono text-sm">{{ line.isin }}</td>
                <td class="text-right whitespace-nowrap">{{ formatEuro(line.acquisition_value_cents) }}</td>
                <td class="text-right whitespace-nowrap">{{ formatEuro(line.acquisition_commissions_cents) }}</td>
                <td class="text-right whitespace-nowrap">
                  <div class="inline-flex items-center justify-end gap-0.5">
                    <span class="font-semibold text-purple-600 dark:text-purple-400 tabular-nums">{{
                      formatEuro(acquisitionNetCents(line))
                    }}</span>
                    <button
                      type="button"
                      class="btn btn-ghost btn-xs btn-square h-7 w-7 min-h-7 min-w-7 p-0 shrink-0"
                      :aria-label="$t('taxReturnYear.copyAcquisitionNet')"
                      @click="copyNetEuro(line, 'acq-net')"
                    >
                      <span
                        v-if="copiedClipKey === clipKey(line.isin, 'acq-net')"
                        class="text-success text-sm leading-none"
                      >✓</span>
                      <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-3.5 w-3.5 opacity-70"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                        />
                      </svg>
                    </button>
                  </div>
                </td>
                <td class="text-right whitespace-nowrap">{{ formatEuro(line.transmission_value_cents) }}</td>
                <td class="text-right whitespace-nowrap">{{ formatEuro(line.transmission_commissions_cents) }}</td>
                <td class="text-right whitespace-nowrap">
                  <div class="inline-flex items-center justify-end gap-0.5">
                    <span class="font-semibold text-purple-600 dark:text-purple-400 tabular-nums">{{
                      formatEuro(transmissionNetCents(line))
                    }}</span>
                    <button
                      type="button"
                      class="btn btn-ghost btn-xs btn-square h-7 w-7 min-h-7 min-w-7 p-0 shrink-0"
                      :aria-label="$t('taxReturnYear.copyTransmissionNet')"
                      @click="copyNetEuro(line, 'tx-net')"
                    >
                      <span
                        v-if="copiedClipKey === clipKey(line.isin, 'tx-net')"
                        class="text-success text-sm leading-none"
                      >✓</span>
                      <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-3.5 w-3.5 opacity-70"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                        />
                      </svg>
                    </button>
                  </div>
                </td>
                <td
                  class="text-right whitespace-nowrap font-medium"
                  :class="netGainClass(line.net_gain_cents)"
                >
                  {{ formatSignedEuro(line.net_gain_cents) }}
                </td>
                <td>
                  <button type="button" class="btn btn-ghost btn-xs" @click="openAudit(line)">
                    {{ $t('taxReturnYear.audit.button') }}
                  </button>
                </td>
              </tr>
            </tbody>
            <tfoot v-if="lines.length > 0">
              <tr class="font-semibold bg-base-200">
                <td colspan="8" class="text-right">{{ $t('taxReturnYear.table.totalNetGain') }}</td>
                <td class="text-right whitespace-nowrap" :class="netGainClass(totalNetGainCents)">
                  {{ formatSignedEuro(totalNetGainCents) }}
                </td>
                <td></td>
              </tr>
            </tfoot>
          </table>
          </div>
        </div>
      </div>
    </div>

    <div class="modal" :class="{ 'modal-open': auditOpen }">
      <div class="modal-box relative max-w-6xl max-h-[90vh] flex flex-col">
        <button
          type="button"
          class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2 z-10"
          :aria-label="$t('taxReturnYear.audit.close')"
          @click="closeAudit"
        >
          ✕
        </button>
        <h3 class="font-bold text-lg pr-10 mb-1">
          {{ auditModalTitle }}
        </h3>
        <p class="text-sm text-base-content/70 mb-4">{{ $t('taxReturnYear.audit.intro') }}</p>

        <div v-if="auditLoading" class="flex justify-center py-8">
          <span class="loading loading-spinner loading-lg"></span>
        </div>
        <div v-else-if="auditError" class="alert alert-error mb-4">
          <span>{{ auditError }}</span>
        </div>
        <div v-else-if="auditPayload" class="overflow-x-auto flex-1 min-h-0">
          <p v-if="auditPayload.steps.length === 0" class="text-base-content/70 mb-4">
            {{ $t('taxReturnYear.audit.empty') }}
          </p>
          <table v-else class="table table-sm table-zebra">
            <thead>
              <tr>
                <th>{{ $t('taxReturnYear.audit.kind') }}</th>
                <th>{{ $t('taxReturnYear.table.isin') }}</th>
                <th>{{ $t('taxReturnYear.table.product') }}</th>
                <th>{{ $t('taxReturnYear.audit.date') }}</th>
                <th class="text-right">{{ $t('taxReturnYear.audit.quantity') }}</th>
                <th class="text-right whitespace-nowrap">{{ $t('taxReturnYear.audit.price') }}</th>
                <th class="text-right">{{ $t('taxReturnYear.audit.value') }}</th>
                <th class="text-right">{{ $t('taxReturnYear.audit.fees') }}</th>
                <th class="text-right font-mono text-xs">{{ $t('taxReturnYear.audit.txId') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(step, idx) in auditPayload.steps" :key="idx">
                <td>{{ stepKindLabel(step) }}</td>
                <td class="font-mono text-xs">{{ step.isin }}</td>
                <td class="max-w-[200px] truncate text-sm" :title="step.product">{{ step.product }}</td>
                <td class="whitespace-nowrap text-sm">{{ step.date }} {{ step.time }}</td>
                <td class="text-right whitespace-nowrap">{{ formatStepQty(step) }}</td>
                <td class="text-right whitespace-nowrap text-sm">{{ step.price_label }}</td>
                <td class="text-right whitespace-nowrap">{{ formatEuro(stepValueCents(step)) }}</td>
                <td class="text-right whitespace-nowrap">{{ formatEuro(stepFeesCents(step)) }}</td>
                <td class="text-right font-mono text-xs">{{ stepTxId(step) }}</td>
              </tr>
            </tbody>
          </table>

          <div v-if="auditPayload.steps.length > 0" class="mt-6 border-t border-base-300 pt-4">
            <h4 class="font-semibold mb-2">{{ $t('taxReturnYear.audit.resultTitle') }}</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
              <div>
                {{ $t('taxReturnYear.table.acquisitionValue') }}:
                {{ formatEuro(auditPayload.summary.acquisition_value_cents) }}
              </div>
              <div>
                {{ $t('taxReturnYear.table.acquisitionCommissions') }}:
                {{ formatEuro(auditPayload.summary.acquisition_commissions_cents) }}
              </div>
              <div>
                {{ $t('taxReturnYear.table.transmissionValue') }}:
                {{ formatEuro(auditPayload.summary.transmission_value_cents) }}
              </div>
              <div>
                {{ $t('taxReturnYear.table.transmissionCommissions') }}:
                {{ formatEuro(auditPayload.summary.transmission_commissions_cents) }}
              </div>
            </div>
            <p class="mt-3 text-lg font-semibold" :class="netGainClass(auditPayload.summary.net_gain_cents)">
              {{ $t('taxReturnYear.table.netGain') }}: {{ formatSignedEuro(auditPayload.summary.net_gain_cents) }}
            </p>
          </div>
        </div>

        <div class="modal-action mt-4 shrink-0">
          <button type="button" class="btn" @click="closeAudit">{{ $t('taxReturnYear.audit.close') }}</button>
        </div>
      </div>
      <div class="modal-backdrop bg-black/50" @click="closeAudit"></div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import {
  authService,
  type TaxReturnAuditStep,
  type TaxReturnAuditResponse,
  type TaxReturnYearLine
} from '../services/authService'
import {
  formatAuditQuantity,
  formatEuroFromCents,
  formatEuroMainUnitEsNoSymbolFromCents,
  formatQuantityInteger,
  formatSignedEuroFromCents
} from '../utils/numberFormat'

const { t } = useI18n()

const props = defineProps<{
  year: string
}>()

const yearNum = computed(() => parseInt(props.year, 10))

const isLoading = ref(true)
const error = ref<string | null>(null)
const lines = ref<TaxReturnYearLine[]>([])
const totalNetGainCents = ref(0)

const auditOpen = ref(false)
const auditIsin = ref('')
const auditProductName = ref('')
const auditLoading = ref(false)
const auditError = ref<string | null>(null)
const auditPayload = ref<TaxReturnAuditResponse | null>(null)

const auditModalTitle = computed(() => {
  const p =
    auditProductName.value.trim() ||
    (auditPayload.value?.product ? auditPayload.value.product.trim() : '')
  if (p) {
    return t('taxReturnYear.audit.titleWithProduct', {
      isin: auditIsin.value,
      product: p,
      year: yearNum.value
    })
  }
  return t('taxReturnYear.audit.title', { isin: auditIsin.value, year: yearNum.value })
})

const formatEuro = formatEuroFromCents
const formatSignedEuro = formatSignedEuroFromCents

const netGainClass = (cents: number): string => {
  if (cents > 0) return 'text-success'
  if (cents < 0) return 'text-error'
  return ''
}

const acquisitionNetCents = (line: TaxReturnYearLine): number => {
  return line.acquisition_value_cents - line.acquisition_commissions_cents
}

const transmissionNetCents = (line: TaxReturnYearLine): number => {
  return line.transmission_value_cents - line.transmission_commissions_cents
}

type TaxReturnSortKey =
  | 'product'
  | 'isin'
  | 'acquisition_value_cents'
  | 'acquisition_commissions_cents'
  | 'acquisition_net_cents'
  | 'transmission_value_cents'
  | 'transmission_commissions_cents'
  | 'transmission_net_cents'
  | 'net_gain_cents'

const sortBy = ref<TaxReturnSortKey>('isin')
const sortOrder = ref<'asc' | 'desc'>('asc')

type NetCopyKind = 'acq-net' | 'tx-net'
type ClipKind = 'product' | NetCopyKind

const copiedClipKey = ref<string | null>(null)
let copiedClipTimer: ReturnType<typeof setTimeout> | null = null

const clipKey = (isin: string, kind: ClipKind): string => `${kind}:${isin}`

const showClipboardCopied = (key: string) => {
  if (copiedClipTimer) {
    clearTimeout(copiedClipTimer)
  }
  copiedClipKey.value = key
  copiedClipTimer = setTimeout(() => {
    copiedClipKey.value = null
    copiedClipTimer = null
  }, 2000)
}

const copyProductName = async (line: TaxReturnYearLine) => {
  try {
    await navigator.clipboard.writeText(line.product ?? '')
    showClipboardCopied(clipKey(line.isin, 'product'))
  } catch (e) {
    console.error(e)
  }
}

const copyNetEuro = async (line: TaxReturnYearLine, kind: NetCopyKind) => {
  const cents = kind === 'acq-net' ? acquisitionNetCents(line) : transmissionNetCents(line)
  const text = formatEuroMainUnitEsNoSymbolFromCents(cents)
  try {
    await navigator.clipboard.writeText(text)
    showClipboardCopied(clipKey(line.isin, kind))
  } catch (e) {
    console.error(e)
  }
}

const sortedLines = computed(() => {
  const rows = [...lines.value]
  const key = sortBy.value
  const dir = sortOrder.value === 'asc' ? 1 : -1

  rows.sort((a, b) => {
    let cmp = 0
    switch (key) {
      case 'product':
        cmp = a.product.localeCompare(b.product, undefined, { sensitivity: 'base' })
        break
      case 'isin':
        cmp = a.isin.localeCompare(b.isin)
        break
      case 'acquisition_value_cents':
        cmp = a.acquisition_value_cents - b.acquisition_value_cents
        break
      case 'acquisition_commissions_cents':
        cmp = a.acquisition_commissions_cents - b.acquisition_commissions_cents
        break
      case 'acquisition_net_cents':
        cmp = acquisitionNetCents(a) - acquisitionNetCents(b)
        break
      case 'transmission_value_cents':
        cmp = a.transmission_value_cents - b.transmission_value_cents
        break
      case 'transmission_commissions_cents':
        cmp = a.transmission_commissions_cents - b.transmission_commissions_cents
        break
      case 'transmission_net_cents':
        cmp = transmissionNetCents(a) - transmissionNetCents(b)
        break
      case 'net_gain_cents':
        cmp = a.net_gain_cents - b.net_gain_cents
        break
      default:
        cmp = 0
    }
    return dir * cmp
  })

  return rows
})

const toggleSort = (column: TaxReturnSortKey) => {
  if (sortBy.value === column) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = column
    sortOrder.value = 'desc'
  }
}

const CSV_SEP = ';'

const escapeCsvCell = (raw: string): string => {
  if (/[;\r\n"]/.test(raw)) {
    return `"${raw.replace(/"/g, '""')}"`
  }
  return raw
}

const exportExerciseTableCsv = () => {
  const header = [
    t('taxReturnYear.table.product'),
    t('taxReturnYear.table.isin'),
    t('taxReturnYear.table.acquisitionValue'),
    t('taxReturnYear.table.acquisitionCommissions'),
    t('taxReturnYear.table.acquisitionNet'),
    t('taxReturnYear.table.transmissionValue'),
    t('taxReturnYear.table.transmissionCommissions'),
    t('taxReturnYear.table.transmissionNet'),
    t('taxReturnYear.table.netGain')
  ]

  const dataRows = sortedLines.value.map((line) => [
    line.product,
    line.isin,
    formatEuro(line.acquisition_value_cents),
    formatEuro(line.acquisition_commissions_cents),
    formatEuro(acquisitionNetCents(line)),
    formatEuro(line.transmission_value_cents),
    formatEuro(line.transmission_commissions_cents),
    formatEuro(transmissionNetCents(line)),
    formatSignedEuro(line.net_gain_cents)
  ])

  const totalRow = [
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    t('taxReturnYear.table.totalNetGain'),
    formatSignedEuro(totalNetGainCents.value)
  ]

  const linesCsv = [header, ...dataRows, totalRow].map((cells) =>
    cells.map((c) => escapeCsvCell(String(c))).join(CSV_SEP)
  )
  const csvBody = linesCsv.join('\r\n')
  const blob = new Blob([`\ufeff${csvBody}`], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `trackfolio-ejercicio-${yearNum.value}.csv`
  a.rel = 'noopener'
  a.click()
  URL.revokeObjectURL(url)
}

const stepKindLabel = (step: TaxReturnAuditStep): string => {
  if (step.kind === 'sell') return t('taxReturnYear.audit.kindSell')
  return t('taxReturnYear.audit.kindBuyFifo')
}

const stepTxId = (step: TaxReturnAuditStep): string | number => {
  if (step.kind === 'sell') return step.transaction_id
  return step.source_transaction_id
}

const formatStepQty = (step: TaxReturnAuditStep): string => {
  if (step.kind === 'sell') return formatQuantityInteger(step.quantity)
  const n = parseFloat(step.allocated_quantity)
  return Number.isFinite(n) ? formatAuditQuantity(n) : step.allocated_quantity
}

const stepValueCents = (step: TaxReturnAuditStep): number => {
  if (step.kind === 'sell') return step.value_cents
  return step.allocated_value_cents
}

const stepFeesCents = (step: TaxReturnAuditStep): number => {
  if (step.kind === 'sell') return step.fees_cents
  return step.allocated_fee_cents
}

const openAudit = async (line: TaxReturnYearLine) => {
  auditIsin.value = line.isin
  auditProductName.value = line.product ?? ''
  auditOpen.value = true
  auditLoading.value = true
  auditError.value = null
  auditPayload.value = null
  try {
    auditPayload.value = await authService.getTaxReturnYearAudit(yearNum.value, line.isin)
  } catch (err: unknown) {
    console.error(err)
    auditError.value =
      (err as { response?: { data?: { message?: string } } })?.response?.data?.message ??
      t('taxReturnYear.audit.loadError')
  } finally {
    auditLoading.value = false
  }
}

const closeAudit = () => {
  auditOpen.value = false
  auditIsin.value = ''
  auditProductName.value = ''
  auditPayload.value = null
  auditError.value = null
}

const load = async () => {
  if (!Number.isFinite(yearNum.value) || yearNum.value < 1990) {
    error.value = 'Invalid year'
    isLoading.value = false
    return
  }

  isLoading.value = true
  error.value = null
  try {
    const res = await authService.getTaxReturnYearDetail(yearNum.value)
    lines.value = res.lines
    totalNetGainCents.value = res.total_net_gain_cents
    sortBy.value = 'isin'
    sortOrder.value = 'asc'
  } catch (err: unknown) {
    console.error(err)
    const ax = err as { response?: { data?: { message?: string }; status?: number } }
    error.value = ax.response?.data?.message ?? 'Failed to load report'
  } finally {
    isLoading.value = false
  }
}

watch(
  () => props.year,
  () => {
    void load()
  },
  { immediate: true }
)

onBeforeUnmount(() => {
  if (copiedClipTimer) {
    clearTimeout(copiedClipTimer)
  }
})
</script>
