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

export interface UploadDegiroTransactionsResponse {
  message: string
  count: number
  new_count: number
  ignored_count: number
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
  price_min_unit: number
  price_currency: string
  local_value_min_unit: number
  local_value_currency: string
  value_min_unit: number
  value_currency: string
  order_id: string
  [key: string]: any
}

export interface DegiroTransactionsListResponse {
  data: DegiroTransaction[]
  current_page: number
  per_page: number
  total: number
  last_page: number
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

  async getDegiroTransactionsCount(): Promise<DegiroTransactionsCountResponse> {
    const response = await apiClient.get<DegiroTransactionsCountResponse>('/api/degiro-transactions/count')
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

  async getDegiroTransactions(perPage: number = 20, page: number = 1): Promise<DegiroTransactionsListResponse> {
    const response = await apiClient.get<DegiroTransactionsListResponse>('/api/degiro-transactions', {
      params: {
        per_page: perPage,
        page: page
      }
    })
    return response.data
  }
}

