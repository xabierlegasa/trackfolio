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
  }
}

