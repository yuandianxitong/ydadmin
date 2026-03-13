import type { AxiosInstance, AxiosRequestConfig, AxiosResponse } from 'axios'
import axios from 'axios'
import { ElMessage } from 'element-plus'

import { PageEnum } from '@/constants/page'
import { getLocale } from '@/locales/setupI18n'
import router from '@/router'
import type { ApiResponse } from '@/types/api'
import { clearAuthInfo, getToken } from '@/utils/auth'
import { t } from '@/utils/i18n'

// 创建axios实例
const request: AxiosInstance = axios.create({
    baseURL: import.meta.env.DEV ? '' : import.meta.env.VITE_APP_API_URL || '',
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json;charset=UTF-8'
    }
})

// 请求拦截器
request.interceptors.request.use(
    (config: any) => {
        // 添加Token（从统一缓存读取）
        const token = getToken()
        if (token && config.headers) {
            config.headers.Authorization = `Bearer ${token}`
        }

        // 添加当前语言（同步前后端多语言）
        const locale = getLocale()
        if (config.headers) {
            const thinkLang = locale === 'zh-CN' ? 'zh-cn' : 'en'
            config.headers['think-lang'] = thinkLang
        }

        // 添加traceId
        const traceId = generateTraceId()
        if (config.headers) {
            config.headers['X-Trace-Id'] = traceId
        }

        return config
    },
    (error) => {
        console.error('Request error:', error)
        return Promise.reject(error)
    }
)

// 响应拦截器
request.interceptors.response.use(
    (response: AxiosResponse<ApiResponse>) => {
        const { data } = response
        // 检查业务状态码
        if (data.code === 200 || data.code === 0) {
            return data as any
        }

        // 处理token验证失败的业务错误码
        if (
            data.code === 401 ||
            data.message?.includes('Token验证失败') ||
            data.message?.includes('Expired token')
        ) {
            // 使用统一的认证清理函数
            clearAuthInfo()
            // 避免在登录页面时重复跳转
            if (router.currentRoute.value.path !== PageEnum.LOGIN) {
                router.push(PageEnum.LOGIN)
            }
            const err = new Error(t('http.loginExpired'))
            ;(err as any).__handled = true
            return Promise.reject(err)
        }

        // 其他业务错误处理
        ElMessage.error(data.message || t('http.operationFailed'))
        const err = new Error(data.message || t('http.operationFailed'))
        ;(err as any).__handled = true
        return Promise.reject(err)
    },
    (error) => {
        let message = t('http.requestFailed')

        if (error.response) {
            const { status, data } = error.response

            switch (status) {
                case 401:
                    message = t('http.loginExpired')
                    // 使用统一的认证清理函数
                    clearAuthInfo()
                    // 避免在登录页面时重复跳转
                    if (router.currentRoute.value.path !== PageEnum.LOGIN) {
                        router.push(PageEnum.LOGIN)
                    }
                    break
                case 403:
                    message = t('http.forbidden')
                    break
                case 404:
                    message = t('http.notFound')
                    break
                case 422:
                    message = data?.message || t('http.validationFailed')
                    break
                case 400:
                    message = data?.message || t('http.badRequest')
                    break
                case 500:
                    message = data?.message || t('http.serverError')
                    break
                case 503:
                    // 系统未安装，提示用户访问后端安装页面
                    if (data?.data?.installed === false) {
                        message = t('http.notInstalled')
                    } else {
                        message = t('http.serviceUnavailable')
                    }
                    break
                default:
                    message = data?.message || `${t('http.requestFailed')} (${status})`
            }
        } else if (error.request) {
            message = t('http.networkError')
        }

        ElMessage.error(message)
        return Promise.reject(error)
    }
)

// 生成traceId
function generateTraceId(): string {
    return 'trace_' + Date.now() + '_' + Math.random().toString(36).substring(2, 11)
}

// 封装请求方法
export const myRequest = {
    get<T = any>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
        return request.get(url, config)
    },

    post<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
        return request.post(url, data, config)
    },

    put<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
        return request.put(url, data, config)
    },

    delete<T = any>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
        return request.delete(url, config)
    },

    patch<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
        return request.patch(url, data, config)
    }
}

export default request
