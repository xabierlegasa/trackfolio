/**
 * Display locale: Spanish-style numbers (e.g. 1.321,22) and amounts with " €".
 */
export const DISPLAY_NUMBER_LOCALE = 'es-ES'

export function formatDecimal(value: number, minFrac = 2, maxFrac = minFrac): string {
  return new Intl.NumberFormat(DISPLAY_NUMBER_LOCALE, {
    minimumFractionDigits: minFrac,
    maximumFractionDigits: maxFrac,
  }).format(value)
}

/** Whole numbers with thousands separators (no decimals). */
export function formatInteger(value: number): string {
  return formatDecimal(Math.round(value), 0, 0)
}

/** Main EUR unit for clipboard: comma decimal, no €, no thousands (e.g. 7168,29). */
export function formatEuroMainUnitEsNoSymbolFromCents(cents: number): string {
  return new Intl.NumberFormat('es-ES', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
    useGrouping: false,
  }).format(cents / 100)
}

export function formatEuroFromCents(cents: number): string {
  return `${formatDecimal(cents / 100)} €`
}

export function formatSignedEuroFromCents(cents: number): string {
  const v = cents / 100
  const sign = v > 0 ? '+' : ''
  return `${sign}${formatDecimal(v)} €`
}

export function formatSignedCurrencyFromMainUnit(valueMinUnit: number, currency: string): string {
  const value = valueMinUnit / 100
  const sign = value > 0 ? '+' : ''
  const suffix = currency === 'EUR' ? '€' : currency
  return `${sign}${formatDecimal(value)} ${suffix}`
}

export function formatCurrencyFromCents(amountInCents: number, currency: string): string {
  const amount = amountInCents / 100
  return new Intl.NumberFormat(DISPLAY_NUMBER_LOCALE, {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount)
}

/** Portfolio price style: "$ 1,234.56" (symbol prefix, en-US decimals). */
export function formatPricePrefixFromCents(
  amountInCents: number,
  currency: string,
  fractionDigits = 2,
): string {
  const amount = amountInCents / 100
  const formatted = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: fractionDigits,
    maximumFractionDigits: fractionDigits,
  }).format(Math.abs(amount))
  const symbol = currency === 'EUR' ? '€' : currency === 'USD' ? '$' : currency
  const sign = amount < 0 ? '-' : ''
  return `${sign}${symbol} ${formatted}`
}

/** Signed portfolio money: "+$ 1,234.56" / "-$ 1,234.56" (symbol prefix, en-US decimals). */
export function formatSignedCurrencyFromCents(
  amountInCents: number,
  currency: string,
  fractionDigits = 2,
): string {
  const amount = amountInCents / 100
  const sign = amount > 0 ? '+' : amount < 0 ? '-' : ''
  const formatted = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: fractionDigits,
    maximumFractionDigits: fractionDigits,
  }).format(Math.abs(amount))
  const symbol = currency === 'EUR' ? '€' : currency === 'USD' ? '$' : currency
  return `${sign}${symbol} ${formatted}`
}

export function formatSignedPercent(percent: number): string {
  const sign = percent > 0 ? '+' : percent < 0 ? '-' : ''
  return `(${sign}${formatDecimal(Math.abs(percent), 2, 2)}%)`
}

export function formatPriceFromTenThousandths(amountInTenThousandths: number, currency: string): string {
  const amount = amountInTenThousandths / 10000
  return new Intl.NumberFormat(DISPLAY_NUMBER_LOCALE, {
    style: 'currency',
    currency,
    minimumFractionDigits: 4,
    maximumFractionDigits: 4,
  }).format(amount)
}

export function formatQuantityInteger(quantity: number): string {
  return new Intl.NumberFormat(DISPLAY_NUMBER_LOCALE, {
    maximumFractionDigits: 0,
    minimumFractionDigits: 0,
  }).format(Math.round(Number(quantity)))
}

export function formatHoldingQuantity(quantity: number): string {
  if (Math.abs(quantity) < 1) {
    return new Intl.NumberFormat(DISPLAY_NUMBER_LOCALE, {
      minimumFractionDigits: 6,
      maximumFractionDigits: 10,
    }).format(quantity)
  }
  return new Intl.NumberFormat(DISPLAY_NUMBER_LOCALE, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 4,
  }).format(quantity)
}

/** Quantities in audit steps (fractional lots). */
export function formatAuditQuantity(n: number): string {
  return new Intl.NumberFormat(DISPLAY_NUMBER_LOCALE, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 10,
  }).format(n)
}

/** Trade summary: whole euros (rounded), with thousands separators. */
export function formatRoundedEurosWithCode(valueMinUnit: number, currency: string): string {
  const roundedEuros = Math.round(valueMinUnit / 100)
  const suffix = currency === 'EUR' ? '€' : currency
  return `${formatDecimal(roundedEuros, 0, 0)} ${suffix}`
}
