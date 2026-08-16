<template>
  <div ref="rootEl" class="relative inline-flex">
    <button
      type="button"
      class="btn btn-sm border border-base-300 bg-base-100 gap-2 font-semibold tabular-nums"
      :disabled="disabled"
      :aria-label="ariaLabel"
      :aria-expanded="open"
      @click="toggle"
    >
      {{ displayValue }}
      <svg class="h-4 w-4 opacity-70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path
          fill-rule="evenodd"
          d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm9 7H5v7a1 1 0 001 1h8a1 1 0 001-1V9z"
          clip-rule="evenodd"
        />
      </svg>
    </button>

    <div
      v-if="open"
      class="absolute left-0 top-full z-50 mt-1 w-72 rounded-xl border border-base-300 bg-base-100 p-3 shadow-xl"
    >
      <div class="mb-2 flex items-center justify-between gap-2">
        <button
          type="button"
          class="btn btn-ghost btn-xs btn-square"
          :disabled="!canGoPrev"
          :aria-label="prevNavLabel"
          @click="shiftView(-1)"
        >
          ‹
        </button>
        <button
          v-if="panel === 'days' || panel === 'months'"
          type="button"
          class="btn btn-ghost btn-xs h-8 min-h-8 px-2 font-semibold capitalize hover:bg-base-200"
          :aria-label="panel === 'days' ? pickMonthYearLabel : pickYearLabel"
          @click="drillUp"
        >
          {{ headerTitle }}
        </button>
        <p v-else class="text-sm font-semibold tabular-nums">{{ headerTitle }}</p>
        <button
          type="button"
          class="btn btn-ghost btn-xs btn-square"
          :disabled="!canGoNext"
          :aria-label="nextNavLabel"
          @click="shiftView(1)"
        >
          ›
        </button>
      </div>

      <template v-if="panel === 'days'">
        <div class="grid grid-cols-7 gap-0.5 text-center text-xs font-medium text-base-content/50">
          <span v-for="day in weekdayLabels" :key="day">{{ day }}</span>
        </div>
        <div class="mt-1 grid grid-cols-7 gap-0.5">
          <button
            v-for="cell in cells"
            :key="cell.iso"
            type="button"
            class="btn btn-ghost btn-xs h-8 min-h-8 px-0 font-normal tabular-nums"
            :class="{
              'text-base-content/35': !cell.inMonth && !cell.isToday && cell.iso !== modelValue,
              'as-of-selected': cell.iso === modelValue,
              'as-of-today': cell.isToday,
            }"
            :disabled="cell.disabled"
            :aria-current="cell.isToday ? 'date' : undefined"
            :aria-pressed="cell.iso === modelValue"
            @click="selectDay(cell.iso)"
          >
            {{ cell.day }}
          </button>
        </div>
      </template>

      <div v-else-if="panel === 'months'" class="grid grid-cols-3 gap-1">
        <button
          v-for="month in monthChoices"
          :key="month.index"
          type="button"
          class="btn btn-ghost btn-sm h-10 min-h-10 font-medium capitalize"
          :class="{ 'as-of-selected': month.selected }"
          :disabled="month.disabled"
          @click="selectMonth(month.index)"
        >
          {{ month.label }}
        </button>
      </div>

      <div v-else class="grid grid-cols-3 gap-1">
        <button
          v-for="year in yearChoices"
          :key="year.value"
          type="button"
          class="btn btn-ghost btn-sm h-10 min-h-10 font-medium tabular-nums"
          :class="{ 'as-of-selected': year.selected }"
          :disabled="year.disabled"
          @click="selectYear(year.value)"
        >
          {{ year.value }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

type Panel = 'days' | 'months' | 'years'

const props = defineProps<{
  modelValue: string | null
  max?: string | null
  disabled?: boolean
  ariaLabel?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const { locale, t } = useI18n()
const open = ref(false)
const panel = ref<Panel>('days')
const rootEl = ref<HTMLElement | null>(null)
const viewYear = ref(2026)
const viewMonth = ref(0)
const yearPageStart = ref(2020)

const displayValue = computed(() => {
  if (!props.modelValue) {
    return 'YYYY/MM/DD'
  }
  return props.modelValue.replaceAll('-', '/')
})

const intlLocale = computed(() => (locale.value === 'es' ? 'es-ES' : 'en-GB'))

const weekdayLabels = computed(() => {
  const formatter = new Intl.DateTimeFormat(intlLocale.value, { weekday: 'short' })
  // 5 Jan 2026 is a Monday
  return Array.from({ length: 7 }, (_, i) =>
    formatter.format(new Date(2026, 0, 5 + i)),
  )
})

const headerTitle = computed(() => {
  if (panel.value === 'days') {
    return new Intl.DateTimeFormat(intlLocale.value, { month: 'long', year: 'numeric' }).format(
      new Date(viewYear.value, viewMonth.value, 1),
    )
  }
  if (panel.value === 'months') {
    return String(viewYear.value)
  }
  return `${yearPageStart.value} – ${yearPageStart.value + 11}`
})

const pickMonthYearLabel = computed(() => t('portfolioStats.asOfPickMonthYear'))
const pickYearLabel = computed(() => t('portfolioStats.asOfPickYear'))
const prevNavLabel = computed(() => {
  if (panel.value === 'years') {
    return t('portfolioStats.asOfPrevYears')
  }
  if (panel.value === 'months') {
    return t('portfolioStats.asOfPrevYear')
  }
  return t('portfolioStats.asOfPrevMonth')
})
const nextNavLabel = computed(() => {
  if (panel.value === 'years') {
    return t('portfolioStats.asOfNextYears')
  }
  if (panel.value === 'months') {
    return t('portfolioStats.asOfNextYear')
  }
  return t('portfolioStats.asOfNextMonth')
})

const maxDate = computed(() => parseIso(props.max ?? null))
const selectedDate = computed(() => parseIso(props.modelValue))

const canGoPrev = computed(() => true)

const canGoNext = computed(() => {
  const max = maxDate.value
  if (!max) {
    return true
  }
  if (panel.value === 'days') {
    if (viewYear.value > max.getFullYear()) {
      return false
    }
    if (viewYear.value === max.getFullYear() && viewMonth.value >= max.getMonth()) {
      return false
    }
    return true
  }
  if (panel.value === 'months') {
    return viewYear.value < max.getFullYear()
  }
  return yearPageStart.value + 11 < max.getFullYear()
})

type Cell = {
  iso: string
  day: number
  inMonth: boolean
  disabled: boolean
  isToday: boolean
}

const cells = computed((): Cell[] => {
  const first = new Date(viewYear.value, viewMonth.value, 1)
  const mondayOffset = (first.getDay() + 6) % 7
  const start = new Date(first)
  start.setDate(first.getDate() - mondayOffset)
  const todayIso = toIso(new Date())
  const max = maxDate.value
  const list: Cell[] = []

  for (let i = 0; i < 42; i++) {
    const date = new Date(start)
    date.setDate(start.getDate() + i)
    const iso = toIso(date)
    list.push({
      iso,
      day: date.getDate(),
      inMonth: date.getMonth() === viewMonth.value,
      disabled: max !== null && date > max,
      isToday: iso === todayIso,
    })
  }

  return list
})

const monthChoices = computed(() => {
  const formatter = new Intl.DateTimeFormat(intlLocale.value, { month: 'short' })
  const max = maxDate.value
  const selected = selectedDate.value
  return Array.from({ length: 12 }, (_, index) => {
    const disabled =
      max !== null &&
      (viewYear.value > max.getFullYear() ||
        (viewYear.value === max.getFullYear() && index > max.getMonth()))
    const isSelected =
      selected !== null &&
      selected.getFullYear() === viewYear.value &&
      selected.getMonth() === index
    return {
      index,
      label: formatter.format(new Date(2026, index, 1)),
      disabled,
      selected: isSelected,
    }
  })
})

const yearChoices = computed(() => {
  const max = maxDate.value
  const selected = selectedDate.value
  return Array.from({ length: 12 }, (_, i) => {
    const value = yearPageStart.value + i
    return {
      value,
      disabled: max !== null && value > max.getFullYear(),
      selected: selected !== null && selected.getFullYear() === value,
    }
  })
})

const syncViewFromValue = () => {
  const parsed = parseIso(props.modelValue) ?? maxDate.value ?? new Date()
  viewYear.value = parsed.getFullYear()
  viewMonth.value = parsed.getMonth()
  yearPageStart.value = Math.floor(parsed.getFullYear() / 12) * 12
  panel.value = 'days'
}

watch(
  () => props.modelValue,
  () => {
    if (!open.value) {
      syncViewFromValue()
    }
  },
  { immediate: true },
)

const toggle = () => {
  if (props.disabled) {
    return
  }
  open.value = !open.value
  if (open.value) {
    syncViewFromValue()
  }
}

const drillUp = () => {
  if (panel.value === 'days') {
    panel.value = 'months'
    return
  }
  if (panel.value === 'months') {
    yearPageStart.value = Math.floor(viewYear.value / 12) * 12
    panel.value = 'years'
  }
}

const shiftView = (delta: number) => {
  if (panel.value === 'days') {
    const next = new Date(viewYear.value, viewMonth.value + delta, 1)
    viewYear.value = next.getFullYear()
    viewMonth.value = next.getMonth()
    return
  }
  if (panel.value === 'months') {
    viewYear.value += delta
    return
  }
  yearPageStart.value += delta * 12
}

const selectMonth = (monthIndex: number) => {
  viewMonth.value = monthIndex
  panel.value = 'days'
}

const selectYear = (year: number) => {
  viewYear.value = year
  panel.value = 'months'
}

const selectDay = (iso: string) => {
  open.value = false
  panel.value = 'days'
  if (iso !== props.modelValue) {
    emit('update:modelValue', iso)
  }
}

const onDocumentPointerDown = (event: PointerEvent) => {
  if (!open.value || !rootEl.value) {
    return
  }
  if (!rootEl.value.contains(event.target as Node)) {
    open.value = false
  }
}

onMounted(() => {
  document.addEventListener('pointerdown', onDocumentPointerDown)
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onDocumentPointerDown)
})

function parseIso(iso: string | null): Date | null {
  if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
    return null
  }
  const date = new Date(`${iso}T12:00:00`)
  return Number.isNaN(date.getTime()) ? null : date
}

function toIso(date: Date): string {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}
</script>

<style scoped>
.as-of-today {
  position: relative;
  font-weight: 700;
  box-shadow: inset 0 0 0 2px oklch(var(--p));
}
.as-of-today:disabled {
  opacity: 1;
}
.as-of-today::after {
  content: '';
  position: absolute;
  left: 50%;
  bottom: 3px;
  width: 4px;
  height: 4px;
  border-radius: 999px;
  background: currentColor;
  transform: translateX(-50%);
}
.as-of-selected {
  background-color: oklch(var(--p)) !important;
  color: oklch(var(--pc)) !important;
  font-weight: 700;
  border-radius: 999px;
}
.as-of-selected:hover {
  background-color: oklch(var(--p)) !important;
  color: oklch(var(--pc)) !important;
}
.as-of-selected.as-of-today {
  box-shadow: inset 0 0 0 2px oklch(var(--pc));
}
.as-of-selected.as-of-today::after {
  background: oklch(var(--pc));
}
</style>
