import type {
    AxiosInstance,
    AxiosRequestConfig,
    AxiosResponse,
    InternalAxiosRequestConfig
} from 'axios'
import axios, { CanceledError } from 'axios'
import { ElMessage } from 'element-plus'

import { PageEnum } from '@/constants/page'
import { getLocale } from '@/locales/setupI18n'
import router from '@/router'
import type { ApiResponse } from '@/types/common'
import { clearAuthInfo, getToken } from '@/utils/auth'
import { t } from '@/utils/i18n'
import { getAuthRevision, pendingGetRequests, trackRequest } from '@/utils/request-context'

type ContextRequestConfig = InternalAxiosRequestConfig & {
    authRevision?: number
    authToken?: string | null
    requestLocale?: string
    releaseRequest?: () => void
}

function staleRequest(): CanceledError<unknown> {
    return Object.assign(new CanceledError('请求所属的登录会话或语言已改变'), { __handled: true })
}

function isStaleRequest(config: ContextRequestConfig | undefined): boolean {
    return (
        config?.authRevision !== undefined &&
        (config.authRevision !== getAuthRevision() || config.requestLocale !== getLocale())
    )
}

function shouldClearAuth(config: ContextRequestConfig | undefined): boolean {
    return (
        !isRefreshRequest(config) &&
        !isStaleRequest(config) &&
        !!config?.authToken &&
        config.authToken === getToken()
    )
}

// 创建axios实例
// 开发环境：留空，通过 vite proxy 代理
// 生产环境：读取 VITE_APP_API_URL，留空则请求当前域名（同域部署），填写则请求指定域名（跨域部署）
const request: AxiosInstance = axios.create({
    baseURL: import.meta.env.DEV ? '' : import.meta.env.VITE_APP_API_URL || '',
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json;charset=UTF-8',
        Accept: 'application/json'
    }
})

// ========== Token 静默刷新 ==========
const REFRESH_URL = '/adminapi/auth/refresh'
let refreshingPromise: Promise<void> | null = null

/**
 * 解析 JWT payload
 * 处理 base64url → base64 转换，解析失败返回 null
 */
function parseJwtPayload(token: string): Record<string, any> | null {
    try {
        const parts = token.split('.')
        if (parts.length !== 3) return null
        const base64 = parts[1].replace(/-/g, '+').replace(/_/g, '/')
        return JSON.parse(atob(base64))
    } catch {
        return null
    }
}

/**
 * 检查 token 是否需要刷新（剩余有效期 < 总有效期的 50%）
 * 阈值从 token 自身的 iat/exp 动态计算，无需硬编码
 */
function shouldRefresh(token: string): boolean {
    const payload = parseJwtPayload(token)
    if (!payload?.exp || !payload?.iat) return false
    const now = Math.floor(Date.now() / 1000)
    const remaining = payload.exp - now
    const total = payload.exp - payload.iat
    return remaining > 0 && remaining < total * 0.5
}

/**
 * 执行 token 刷新（带并发锁）
 */
const REFRESH_LOCK = 'ydadmin-token-refresh'

function isRefreshRequest(config: { url?: string } | undefined): boolean {
    return config?.url === REFRESH_URL
}

async function doRefresh(): Promise<void> {
    if (refreshingPromise) return refreshingPromise

    refreshingPromise = (async () => {
        const run = async () => {
            const current = getToken()
            if (!current || !shouldRefresh(current)) return
            const { useUserStore } = await import('@/store/modules/user.store')
            await useUserStore().refreshToken()
        }
        try {
            // 各标签页共用 localStorage 里的 token。锁住之后再看一眼，避免两个标签页各换一次。
            if (typeof navigator !== 'undefined' && navigator.locks?.request) {
                await navigator.locks.request(REFRESH_LOCK, run)
            } else {
                await run()
            }
        } finally {
            refreshingPromise = null
        }
    })()

    return refreshingPromise
}

// 请求拦截器
request.interceptors.request.use(
    async (config: ContextRequestConfig) => {
        const revision = config.authRevision ?? getAuthRevision()
        const locale = config.requestLocale ?? getLocale()
        if (revision !== getAuthRevision() || locale !== getLocale()) throw staleRequest()
        // 添加Token（从统一缓存读取）
        let token = getToken()

        // 静默刷新：非 refresh 请求且 token 即将过期时自动刷新
        if (token && config.url !== REFRESH_URL && shouldRefresh(token)) {
            try {
                await doRefresh()
            } catch {
                // 网络失败时保留当前登录。别的标签页可能已经写好新 token。
            }
            token = getToken()
        }

        if (revision !== getAuthRevision() || locale !== getLocale() || config.signal?.aborted) {
            throw staleRequest()
        }
        const controller = new AbortController()
        const signal = config.signal
        const abort = () => controller.abort()
        signal?.addEventListener?.('abort', abort)
        const untrack = trackRequest(controller)
        config.releaseRequest = () => {
            untrack()
            signal?.removeEventListener?.('abort', abort)
        }
        config.signal = controller.signal
        config.authRevision = revision
        config.authToken = token

        if (token && config.headers) {
            config.headers.Authorization = `Bearer ${token}`
        }

        // 添加当前语言（同步前后端多语言）
        config.requestLocale = locale
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
        const config = response.config as ContextRequestConfig
        config.releaseRequest?.()
        if (isStaleRequest(config)) return Promise.reject(staleRequest())
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
            if (config.authToken && config.authToken !== getToken()) {
                return Promise.reject(staleRequest())
            }
            const err = new Error(t('http.loginExpired'))
            ;(err as any).__handled = true
            ;(err as any).code = 401
            // 刷新接口自己的 401 交给 refreshToken 决定要不要清登录，这里清掉会把别的标签页刚写入的 token 一起抹掉
            if (shouldClearAuth(config)) {
                clearAuthInfo()
                if (router.currentRoute.value.path !== PageEnum.LOGIN) {
                    router.push(PageEnum.LOGIN)
                }
            }
            return Promise.reject(err)
        }

        // 其他业务错误处理
        ElMessage.error(data.message || t('http.operationFailed'))
        const err = Object.assign(new Error(data.message || t('http.operationFailed')), {
            code: data.code,
            data: data.data
        })
        ;(err as any).__handled = true
        return Promise.reject(err)
    },
    (error) => {
        const config = error.config as ContextRequestConfig | undefined
        config?.releaseRequest?.()
        if (axios.isCancel(error)) return Promise.reject(error)
        if (isStaleRequest(config)) return Promise.reject(staleRequest())
        let message = t('http.requestFailed')

        if (error.response) {
            const { status, data } = error.response

            switch (status) {
                case 401:
                    if (config?.authToken && config.authToken !== getToken()) {
                        return Promise.reject(staleRequest())
                    }
                    message = t('http.loginExpired')
                    ;(error as any).code = 401
                    if (shouldClearAuth(config)) {
                        clearAuthInfo()
                        if (router.currentRoute.value.path !== PageEnum.LOGIN) {
                            router.push(PageEnum.LOGIN)
                        }
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

function buildDedupKey(url: string, config?: AxiosRequestConfig): string {
    const params = config?.params ? JSON.stringify(config.params) : ''
    return `${getAuthRevision()}:${getLocale()}:${url}:${params}`
}

function bindRequestContext(config?: AxiosRequestConfig): AxiosRequestConfig & {
    authRevision: number
    requestLocale: string
} {
    // 在调用时固定上下文，避免请求拦截器进入异步队列前已切换账号或语言。
    return { ...config, authRevision: getAuthRevision(), requestLocale: getLocale() }
}

// 封装请求方法
export const myRequest = {
    get<T = any>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
        // signal、headers、responseType 等配置具有调用方语义，各自发送，避免串用响应或取消权。
        if (config && Object.keys(config).some((key) => key !== 'params')) {
            return request.get(url, bindRequestContext(config))
        }
        const key = buildDedupKey(url, config)
        const pending = pendingGetRequests.get(key)
        if (pending) {
            return pending as Promise<ApiResponse<T>>
        }
        const req = request.get(url, bindRequestContext(config)).finally(() => {
            if (pendingGetRequests.get(key) === req) pendingGetRequests.delete(key)
        }) as unknown as Promise<ApiResponse<T>>
        pendingGetRequests.set(key, req)
        return req
    },

    post<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
        return request.post(url, data, bindRequestContext(config))
    },

    put<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
        return request.put(url, data, bindRequestContext(config))
    },

    delete<T = any>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
        return request.delete(url, bindRequestContext(config))
    },

    patch<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
        return request.patch(url, data, bindRequestContext(config))
    }
}

export default request
