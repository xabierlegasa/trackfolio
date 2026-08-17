import apiClient from '../api/axios'

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface SnapshotCalculationProcessRow {
  id: number
  user_id: number
  status: string
  started_from: string | null
  deleted_snapshots: number | null
  finished_at: string | null
  created_at: string | null
}

export interface SnapshotCalculationProcessLogRow {
  id: number
  snapshot_calculation_process_id: number
  description: string
  date_processed: string | null
  isin: string | null
  symbol: string | null
  provider_request_id: number | null
  created_at: string | null
}

export interface ProviderRequestRow {
  id: number
  provider: string
  call_type: string
  method: string
  url: string
  http_status: number | null
  response_body: string | null
  duration_ms: number | null
  success: boolean
  error_message: string | null
  created_at: string | null
}

export interface SnapshotCalculationProcessesResponse {
  data: SnapshotCalculationProcessRow[]
  meta: PaginationMeta
}

export interface SnapshotCalculationProcessLogsResponse {
  process: SnapshotCalculationProcessRow
  data: SnapshotCalculationProcessLogRow[]
  meta: PaginationMeta
}

export interface RecalculateEvolutionFeatureConfig {
  code: string
  enabled: boolean
}

export const adminService = {
  async listSnapshotCalculationProcesses(page = 1, perPage = 20): Promise<SnapshotCalculationProcessesResponse> {
    const response = await apiClient.get<SnapshotCalculationProcessesResponse>(
      '/api/admin/snapshot-calculation-processes',
      { params: { page, per_page: perPage } },
    )
    return response.data
  },

  async listSnapshotCalculationProcessLogs(
    processId: number,
    page = 1,
    perPage = 20,
    filters: { isin?: string; symbol?: string } = {},
  ): Promise<SnapshotCalculationProcessLogsResponse> {
    const response = await apiClient.get<SnapshotCalculationProcessLogsResponse>(
      `/api/admin/snapshot-calculation-processes/${processId}/logs`,
      {
        params: {
          page,
          per_page: perPage,
          ...(filters.isin ? { isin: filters.isin } : {}),
          ...(filters.symbol ? { symbol: filters.symbol } : {}),
        },
      },
    )
    return response.data
  },

  async getProviderRequest(providerRequestId: number): Promise<ProviderRequestRow> {
    const response = await apiClient.get<{ data: ProviderRequestRow }>(
      `/api/admin/provider-requests/${providerRequestId}`,
    )
    return response.data.data
  },

  async getRecalculateEvolutionFeature(): Promise<RecalculateEvolutionFeatureConfig> {
    const response = await apiClient.get<RecalculateEvolutionFeatureConfig>(
      '/api/admin/global-config/recalculate-evolution-feature',
    )
    return response.data
  },

  async setRecalculateEvolutionFeature(enabled: boolean): Promise<RecalculateEvolutionFeatureConfig> {
    const response = await apiClient.put<RecalculateEvolutionFeatureConfig>(
      '/api/admin/global-config/recalculate-evolution-feature',
      { enabled },
    )
    return response.data
  },
}
