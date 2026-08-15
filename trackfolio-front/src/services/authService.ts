import apiClient from '../api/axios'

export interface RegisterData {
  email: string
  name: string
  password: string
  privacy_policy_accepted: boolean
  terms_conditions_accepted: boolean
}

export interface LoginData {
  email: string
  password: string
}

export interface RegisterResponse {
  message: string
  user: {
    id: number
    email: string
  }
}

export interface LoginResponse {
  message: string
  user: {
    id: number
    email: string
  }
}

export interface AccountResponse {
  email: string
  name: string
}

export interface DegiroTransactionsCountResponse {
  count: number
}

export interface DeleteAllDegiroTransactionsResponse {
  message: string
  deleted_count: number
}

export interface UploadDegiroTransactionsSkippedRow {
  line: number
  reason: string
  date: string | null
  time: string | null
  product: string | null
  local_value: string | null
}

export interface UploadDegiroTransactionsResponse {
  message: string
  count: number
  new_count: number
  ignored_count: number
  skipped_count: number
  skipped_rows: UploadDegiroTransactionsSkippedRow[]
}

export interface UploadDegiroTransactionsErrorResponse {
  message: string
  error: string
  errors?: string[]
}

export interface DegiroTransaction {
  id: number
  date: string
  time: string
  product: string
  quantity: number
  price_ten_thousandths: number
  price_currency: string
  local_value_min_unit: number
  local_value_currency: string
  value_min_unit: number
  value_currency: string
  order_id: string | null
  [key: string]: any
}

export interface DegiroTransactionsListResponse {
  data: DegiroTransaction[]
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface PortfolioHolding {
  isin: string
  product: string
  quantity: number
  is_etf?: boolean
  ticker_symbol?: string | null
  closing_price_min_unit?: number | null
  closing_price_currency?: string | null
  closing_date?: string | null
  day_change_min_unit?: number | null
  day_change_percent?: number | null
  total_gain_loss_min_unit?: number | null
  total_gain_loss_eur_min_unit?: number | null
  total_gain_loss_percent?: number | null
  market_value_min_unit?: number | null
  market_value_eur_min_unit?: number | null
  weight_percent?: number | null
}

export interface PortfolioConcentrationItem {
  isin: string
  ticker_symbol: string
  weight_percent: number
}

export interface PortfolioPerformanceTemperatureItem {
  isin: string
  ticker_symbol: string
  product: string
  weight_percent: number
  day_change_percent: number | null
}

export interface PortfolioStatsResponse {
  data: PortfolioHolding[]
  concentration?: PortfolioConcentrationItem[]
  performance_temperature?: PortfolioPerformanceTemperatureItem[]
  current_page: number
  per_page: number
  total: number
  last_page: number
  sort_by?: string
  sort_order?: 'asc' | 'desc'
  usd_to_eur_rate?: number | null
  usd_to_eur_rate_date?: string | null
  last_us_market_open_date?: string | null
  total_market_value_min_unit?: number | null
  total_market_value_eur_min_unit?: number | null
  leverage_eur_min_unit?: number
  net_market_value_eur_min_unit?: number | null
  day_change_eur_min_unit?: number | null
  total_gain_loss_eur_min_unit?: number | null
}

export interface PortfolioDailySnapshot {
  snapshot_date: string
  balance_eur_min_unit: number
  portfolio_eur_min_unit: number
  leverage_eur_min_unit: number
  day_change_eur_min_unit: number | null
  total_gain_loss_eur_min_unit: number | null
}

export interface PortfolioEvolutionResponse {
  from: string
  to: string
  months: number
  data: PortfolioDailySnapshot[]
}

export interface UserLeverageHistoryItem {
  id: number
  amount_eur_min_unit: number
  recorded_at: string
}

export interface UserLeverageResponse {
  amount_eur_min_unit: number
  history: UserLeverageHistoryItem[]
}

export interface Trade {
  isin: string
  product: string
  profit_loss: number
  currency: string
  first_purchase_date: string
  last_sale_date: string
}

export interface TradesResponse {
  data: Trade[]
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface TradesSummary {
  positive_sum: number
  negative_sum: number
  difference: number
  currency: string
}

export interface TaxReturnYearEvolutionRow {
  year: number
  total_net_gain_cents: number | null
  fifo_incomplete?: boolean
}

export interface TaxReturnYearsResponse {
  years: number[]
  evolution: TaxReturnYearEvolutionRow[]
}

export interface TaxReturnYearLine {
  isin: string
  product: string
  acquisition_value_cents: number
  acquisition_commissions_cents: number
  transmission_value_cents: number
  transmission_commissions_cents: number
  net_gain_cents: number
}

export interface TaxReturnYearDetailResponse {
  year: number
  lines: TaxReturnYearLine[]
  total_net_gain_cents: number
}

export interface TaxReturnAuditSummary {
  acquisition_value_cents: number
  acquisition_commissions_cents: number
  transmission_value_cents: number
  transmission_commissions_cents: number
  net_gain_cents: number
}

export type TaxReturnAuditStep =
  | {
      kind: 'sell'
      transaction_id: number
      date: string
      time: string
      isin: string
      product: string
      quantity: number
      price_label: string
      value_cents: number
      fees_cents: number
    }
  | {
      kind: 'buy_fifo'
      source_transaction_id: number
      date: string
      time: string
      isin: string
      product: string
      price_ten_thousandths: number
      price_currency: string
      price_label: string
      allocated_quantity: string
      allocated_value_cents: number
      allocated_fee_cents: number
    }

export interface TaxReturnAuditResponse {
  year: number
  isin: string
  product: string
  steps: TaxReturnAuditStep[]
  summary: TaxReturnAuditSummary
}

export const authService = {
  async getCsrfCookie(): Promise<void> {
    await apiClient.get('/sanctum/csrf-cookie')
  },

  async register(data: RegisterData): Promise<RegisterResponse> {
    // First get CSRF cookie
    await this.getCsrfCookie()
    
    // Then register (CSRF token will be automatically included by axios interceptor)
    const response = await apiClient.post<RegisterResponse>('/api/register', data)
    return response.data
  },

  async login(data: LoginData): Promise<LoginResponse> {
    // First get CSRF cookie
    await this.getCsrfCookie()
    
    // Then login (CSRF token will be automatically included by axios interceptor)
    const response = await apiClient.post<LoginResponse>('/api/login', data)
    return response.data
  },

  async logout(): Promise<void> {
    // Ensure CSRF cookie is available before logout
    await this.getCsrfCookie()
    
    // Then logout (CSRF token will be automatically included by axios interceptor)
    await apiClient.post('/api/logout')
  },

  async getAccount(): Promise<AccountResponse> {
    const response = await apiClient.get<AccountResponse>('/api/account')
    return response.data
  },

  async getLeverage(): Promise<UserLeverageResponse> {
    const response = await apiClient.get<UserLeverageResponse>('/api/leverage')
    return response.data
  },

  async saveLeverage(amountEurMinUnit: number): Promise<void> {
    await this.getCsrfCookie()
    await apiClient.post('/api/leverage', {
      amount_eur_min_unit: amountEurMinUnit,
    })
  },

  async getDegiroTransactionsCount(): Promise<DegiroTransactionsCountResponse> {
    const response = await apiClient.get<DegiroTransactionsCountResponse>('/api/degiro-transactions/count')
    return response.data
  },

  async deleteAllDegiroTransactions(): Promise<DeleteAllDegiroTransactionsResponse> {
    await this.getCsrfCookie()
    const response = await apiClient.delete<DeleteAllDegiroTransactionsResponse>('/api/degiro-transactions')
    return response.data
  },

  async uploadDegiroTransactions(file: File): Promise<UploadDegiroTransactionsResponse> {
    // Ensure CSRF cookie is available
    await this.getCsrfCookie()

    // Create FormData for file upload
    const formData = new FormData()
    formData.append('file', file)

    // Upload file
    const response = await apiClient.post<UploadDegiroTransactionsResponse>('/api/upload-degiro-transactions', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    return response.data
  },

  async getDegiroTransactions(
    perPage: number = 10,
    page: number = 1,
    sortOrder: 'asc' | 'desc' = 'desc',
    product?: string
  ): Promise<DegiroTransactionsListResponse> {
    const params: Record<string, string | number> = {
      per_page: perPage,
      page: page,
      sort_order: sortOrder
    }
    const trimmed = product?.trim()
    if (trimmed) {
      params.product = trimmed
    }
    const response = await apiClient.get<DegiroTransactionsListResponse>('/api/degiro-transactions', { params })
    return response.data
  },

  async getPortfolioStats(
    perPage: number = 10,
    page: number = 1,
    sortBy: string = 'weight',
    sortOrder: 'asc' | 'desc' = 'desc'
  ): Promise<PortfolioStatsResponse> {
    const response = await apiClient.get<PortfolioStatsResponse>('/api/portfolio-stats', {
      params: {
        per_page: perPage,
        page: page,
        sort_by: sortBy,
        sort_order: sortOrder,
      }
    })
    return response.data
  },

  async getPortfolioEvolution(months: number = 3): Promise<PortfolioEvolutionResponse> {
    const response = await apiClient.get<PortfolioEvolutionResponse>('/api/portfolio-evolution', {
      params: { months },
    })
    return response.data
  },

  async getTrades(
    perPage: number = 10,
    page: number = 1,
    sortBy: string = 'last_sale_date',
    sortOrder: string = 'desc',
    product?: string
  ): Promise<TradesResponse> {
    const params: Record<string, string | number> = {
      per_page: perPage,
      page: page,
      sort_by: sortBy,
      sort_order: sortOrder
    }
    const trimmed = product?.trim()
    if (trimmed) {
      params.product = trimmed
    }
    const response = await apiClient.get<TradesResponse>('/api/trades', { params })
    return response.data
  },

  async getTradesSummary(): Promise<TradesSummary> {
    const response = await apiClient.get<TradesSummary>('/api/trades-summary')
    return response.data
  },

  async getTaxReturnYears(): Promise<TaxReturnYearsResponse> {
    const response = await apiClient.get<TaxReturnYearsResponse>('/api/tax-return/years')
    return response.data
  },

  async getTaxReturnYearDetail(year: number): Promise<TaxReturnYearDetailResponse> {
    const response = await apiClient.get<TaxReturnYearDetailResponse>(`/api/tax-return/${year}`)
    return response.data
  },

  async getTaxReturnYearAudit(year: number, isin: string): Promise<TaxReturnAuditResponse> {
    const encoded = encodeURIComponent(isin)
    const response = await apiClient.get<TaxReturnAuditResponse>(`/api/tax-return/${year}/audit/${encoded}`)
    return response.data
  }
}

