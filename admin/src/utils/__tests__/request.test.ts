import {
    AxiosError,
    AxiosHeaders,
    type AxiosResponse,
    type InternalAxiosRequestConfig
} from 'axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import request, { myRequest } from '../request'
import { getAuthRevision, invalidateAuthRequests } from '../request-context'

const state = vi.hoisted(() => ({
    token: null as string | null,
    locale: 'zh-CN',
    clearAuth: vi.fn(),
    push: vi.fn(),
    message: vi.fn()
}))

vi.mock('@/utils/cache', () => ({
    default: { get: () => state.token, getKey: (key: string) => key }
}))
vi.mock('@/utils/auth', () => ({
    getToken: () => state.token,
    clearAuthInfo: () => {
        state.clearAuth()
        state.token = null
    }
}))
vi.mock('@/locales/setupI18n', () => ({ getLocale: () => state.locale }))
vi.mock('@/utils/i18n', () => ({ t: (key: string) => key }))
vi.mock('@/router', () => ({
    default: { currentRoute: { value: { path: '/home' } }, push: state.push }
}))
vi.mock('element-plus', () => ({ ElMessage: { error: state.message } }))

type Pending = {
    config: InternalAxiosRequestConfig
    resolve: (response: AxiosResponse) => void
    reject: (error: Error) => void
}
let pending: Pending[] = []

function token(account = 1, session = 'session-a', generation = 1): string {
    const now = Math.floor(Date.now() / 1000)
    return `header.${btoa(
        JSON.stringify({
            scope: 'admin',
            data: { admin_id: account, sid: session },
            iat: now,
            exp: now + 3600,
            jti: generation
        })
    )}.signature`
}

function respond(index: number, code = 200, value = 'ok'): void {
    const item = pending[index]
    item.resolve({
        config: item.config,
        data: { code, message: 'response', data: value },
        status: 200,
        statusText: 'OK',
        headers: new AxiosHeaders()
    })
}

async function dispatched(count: number): Promise<void> {
    await vi.waitFor(() => expect(pending).toHaveLength(count))
}

beforeEach(() => {
    state.token = token()
    state.locale = 'zh-CN'
    vi.clearAllMocks()
    invalidateAuthRequests()
    pending = []
    request.defaults.adapter = (config) =>
        new Promise((resolve, reject) => pending.push({ config, resolve, reject }))
})

describe('请求的账号、语言与 token 上下文', () => {
    it('进入异步拦截器前切换账号，旧写请求不能改用新账号发送', async () => {
        const first = myRequest.post('/items', { title: 'old-account-action' })
        state.token = token(2, 'session-b')
        await expect(first).rejects.toMatchObject({ code: 'ERR_CANCELED' })
        expect(pending).toHaveLength(0)
    })

    it('同一上下文的相同 GET 共用请求', async () => {
        const first = myRequest.get('/items', { params: { page: 1 } })
        const second = myRequest.get('/items', { params: { page: 1 } })
        expect(first).toBe(second)
        await dispatched(1)
        respond(0)
        await expect(first).resolves.toMatchObject({ code: 200 })
    })

    it('切换账号取消旧请求，新账号相同 GET 使用新请求', async () => {
        const first = myRequest.get('/items')
        const cancelled = expect(first).rejects.toMatchObject({ code: 'ERR_CANCELED' })
        await dispatched(1)
        state.token = token(2, 'session-b')
        const second = myRequest.get('/items')
        expect(first).not.toBe(second)
        await dispatched(2)
        expect(pending[0].config.signal?.aborted).toBe(true)
        expect(pending[1].config.headers.Authorization).toBe(`Bearer ${state.token}`)
        respond(0, 200, 'old-account')
        respond(1, 200, 'new-account')
        await cancelled
        await expect(second).resolves.toMatchObject({ data: 'new-account' })
        expect(state.clearAuth).not.toHaveBeenCalled()
    })

    it('切换语言不复用 GET，并拒绝旧语言响应', async () => {
        const first = myRequest.get('/items')
        const cancelled = expect(first).rejects.toMatchObject({ code: 'ERR_CANCELED' })
        await dispatched(1)
        state.locale = 'en'
        const second = myRequest.get('/items')
        await dispatched(2)
        expect(pending[1].config.headers['think-lang']).toBe('en')
        respond(0)
        respond(1)
        await cancelled
        await expect(second).resolves.toMatchObject({ code: 200 })
    })

    it.each(['business', 'http'])('刷新后晚返回的旧 %s 401 保留新 token', async (kind) => {
        const first = myRequest.get('/items')
        const cancelled = expect(first).rejects.toMatchObject({ code: 'ERR_CANCELED' })
        await dispatched(1)
        const revision = getAuthRevision()
        const fresh = token(1, 'session-a', 2)
        state.token = fresh
        expect(getAuthRevision()).toBe(revision)
        if (kind === 'business') {
            respond(0, 401)
        } else {
            const item = pending[0]
            item.reject(
                new AxiosError('Unauthorized', 'ERR_BAD_REQUEST', item.config, undefined, {
                    config: item.config,
                    data: {},
                    status: 401,
                    statusText: 'Unauthorized',
                    headers: new AxiosHeaders()
                })
            )
        }
        await cancelled
        expect(state.token).toBe(fresh)
        expect(state.clearAuth).not.toHaveBeenCalled()
        expect(state.push).not.toHaveBeenCalled()
        expect(state.message).not.toHaveBeenCalled()
    })

    it('当前 token 的 401 清理登录信息', async () => {
        const first = myRequest.get('/items')
        const rejected = expect(first).rejects.toMatchObject({ code: 401 })
        await dispatched(1)
        respond(0, 401)
        await rejected
        expect(state.clearAuth).toHaveBeenCalledOnce()
        expect(state.push).toHaveBeenCalledOnce()
    })

    it('自定义 headers 和 signal 的 GET 各自发送，调用方取消互不影响', async () => {
        const controller = new AbortController()
        const first = myRequest.get('/items', {
            signal: controller.signal,
            headers: { 'X-Variant': 'one' }
        })
        const cancelled = expect(first).rejects.toMatchObject({ code: 'ERR_CANCELED' })
        const second = myRequest.get('/items', { headers: { 'X-Variant': 'two' } })
        await dispatched(2)
        controller.abort()
        expect(pending[0].config.signal?.aborted).toBe(true)
        expect(pending[1].config.signal?.aborted).toBe(false)
        respond(0)
        respond(1)
        await cancelled
        await expect(second).resolves.toMatchObject({ code: 200 })
    })

    it('退出登录后旧响应不能返回业务数据', async () => {
        const first = myRequest.get('/items')
        const cancelled = expect(first).rejects.toMatchObject({ code: 'ERR_CANCELED' })
        await dispatched(1)
        state.token = null
        invalidateAuthRequests()
        respond(0, 200, 'private-data')
        await cancelled
    })
})
