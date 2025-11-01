import apiClient from '../api/axios'

export interface RegisterData {
  email: string
  name: string
  password: string
  privacy_policy_accepted: boolean
  terms_conditions_accepted: boolean
}

export interface RegisterResponse {
  message: string
  user: {
    id: number
    email: string
  }
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
  }
}

