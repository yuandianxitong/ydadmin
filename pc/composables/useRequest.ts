import { ofetch } from 'ofetch'

const TOKEN_KEY = 'pc_token'

export function getToken(): string | null {
  if (import.meta.server) return null
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token: string) {
  localStorage.setItem(TOKEN_KEY, token)
}

export function removeToken() {
  localStorage.removeItem(TOKEN_KEY)
}

export const request = ofetch.create({
  onRequest({ options }) {
    const token = getToken()
    if (token) {
      options.headers = options.headers instanceof Headers
        ? options.headers
        : new Headers(options.headers as HeadersInit | undefined)
      options.headers.set('Authorization', `Bearer ${token}`)
    }
  },

  onResponseError({ response }) {
    if (response.status === 401) {
      removeToken()
      if (import.meta.client) {
        navigateTo('/login')
      }
    }
  },
})

// Typed helpers matching backend response format: { code, msg, data }
interface ApiResponse<T = any> {
  code: number
  msg: string
  data: T
}

export function get<T = any>(url: string, params?: Record<string, any>): Promise<ApiResponse<T>> {
  return request<ApiResponse<T>>(url, { method: 'GET', params })
}

export function post<T = any>(url: string, body?: Record<string, any>): Promise<ApiResponse<T>> {
  return request<ApiResponse<T>>(url, { method: 'POST', body })
}

export function put<T = any>(url: string, body?: Record<string, any>): Promise<ApiResponse<T>> {
  return request<ApiResponse<T>>(url, { method: 'PUT', body })
}
