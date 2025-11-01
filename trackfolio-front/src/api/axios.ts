import axios from 'axios'

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8080',
  withCredentials: true, // Important for Sanctum CSRF cookies
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Add request interceptor to include CSRF token in headers
apiClient.interceptors.request.use(
  (config) => {
    // Get CSRF token from cookie
    const name = 'XSRF-TOKEN='
    const decodedCookie = decodeURIComponent(document.cookie)
    const ca = decodedCookie.split(';')
    for (let i = 0; i < ca.length; i++) {
      let c = ca[i]
      while (c.charAt(0) === ' ') {
        c = c.substring(1)
      }
      if (c.indexOf(name) === 0) {
        const token = c.substring(name.length, c.length)
        // Set X-XSRF-TOKEN header for Laravel Sanctum
        config.headers['X-XSRF-TOKEN'] = token
        break
      }
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

export default apiClient

