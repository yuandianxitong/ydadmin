import { getToken, removeToken } from './auth'
import type { ApiResponse } from '@/types/api'

let BASE_URL = import.meta.env.VITE_APP_API_URL || ''
// #ifdef H5
// H5 开发模式通过 Vite 代理转发，使用相对路径避免跨域
if (import.meta.env.DEV) BASE_URL = ''
// #endif

function getClientType(): string {
  // #ifdef MP-WEIXIN
  return 'miniapp'
  // #endif
  // #ifdef APP-PLUS
  return 'app'
  // #endif
  // #ifdef H5
  const ua = navigator.userAgent.toLowerCase()
  return ua.includes('micromessenger') ? 'wechat_h5' : 'h5'
  // #endif
  // fallback for unknown platforms
  return 'unknown'
}

interface RequestOptions {
  url: string
  method?: 'GET' | 'POST' | 'PUT' | 'DELETE'
  data?: any
  header?: Record<string, string>
  loading?: boolean
}

function request<T = any>(options: RequestOptions): Promise<T> {
  const { url, method = 'GET', data, header = {}, loading = false } = options

  if (loading) {
    uni.showLoading({ title: '加载中...' })
  }

  const token = getToken()
  if (token) {
    header['Authorization'] = `Bearer ${token}`
  }

  return new Promise((resolve, reject) => {
    uni.request({
      url: `${BASE_URL}${url}`,
      method,
      data,
      header: {
        'Content-Type': 'application/json',
        'X-Client-Type': getClientType(),
        ...header,
      },
      success: (res: any) => {
        if (loading) uni.hideLoading()

        const response = res.data as ApiResponse<T>

        if (response.code === 200) {
          resolve(response.data)
        } else if (response.code === 401 || res.statusCode === 401) {
          removeToken()
          uni.reLaunch({ url: '/modules/login/pages/login' })
          reject(new Error(response.message || '请先登录'))
        } else {
          uni.showToast({ title: response.message || '请求失败', icon: 'none' })
          reject(new Error(response.message))
        }
      },
      fail: (err: any) => {
        if (loading) uni.hideLoading()
        uni.showToast({ title: '网络异常', icon: 'none' })
        reject(err)
      },
    })
  })
}

export const http = {
  get: <T = any>(url: string, data?: any) => request<T>({ url, method: 'GET', data }),
  post: <T = any>(url: string, data?: any) => request<T>({ url, method: 'POST', data }),
  put: <T = any>(url: string, data?: any) => request<T>({ url, method: 'PUT', data }),
  delete: <T = any>(url: string, data?: any) => request<T>({ url, method: 'DELETE', data }),
}

export default http
