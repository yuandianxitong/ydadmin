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
    const headers = options.headers instanceof Headers
      ? options.headers
      : new Headers(options.headers as HeadersInit | undefined)
    if (token) {
      headers.set('Authorization', `Bearer ${token}`)
    }
    headers.set('X-Client-Type', 'pc')
    options.headers = headers
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

// Typed helpers matching backend response format: { code, message, data, timestamp }
export interface ApiResponse<T = any> {
  code: number
  message: string
  data: T
}

/**
 * 统一处理业务错误码：非 200 时自动弹出错误提示
 * 传入 showError: false 可跳过自动提示（调用方自行处理）
 */
async function handleResponse<T>(promise: Promise<ApiResponse<T>>, showError = true): Promise<ApiResponse<T>> {
  const res = await promise
  if (res.code !== 200 && showError && import.meta.client) {
    // 使用 window.alert 的轻量替代，避免 useMessage 需要 setup 上下文
    import('naive-ui').then(({ createDiscreteApi }) => {
      const { message } = createDiscreteApi(['message'])
      message.error(res.message || '请求失败')
    }).catch(() => {
      console.error(res.message || '请求失败')
    })
  }
  return res
}

export function get<T = any>(url: string, params?: Record<string, any>, showError = true): Promise<ApiResponse<T>> {
  return handleResponse(request<ApiResponse<T>>(url, { method: 'GET', params }), showError)
}

export function post<T = any>(url: string, body?: Record<string, any>, showError = true): Promise<ApiResponse<T>> {
  return handleResponse(request<ApiResponse<T>>(url, { method: 'POST', body }), showError)
}

export function put<T = any>(url: string, body?: Record<string, any>, showError = true): Promise<ApiResponse<T>> {
  return handleResponse(request<ApiResponse<T>>(url, { method: 'PUT', body }), showError)
}

export function del<T = any>(url: string, body?: Record<string, any>, showError = true): Promise<ApiResponse<T>> {
  return handleResponse(request<ApiResponse<T>>(url, { method: 'DELETE', body }), showError)
}
