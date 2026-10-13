import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { invalidateAuthRequests } from '@/utils/request-context'

import { useUserStore } from '../user.store'

const state = vi.hoisted(() => ({
    token: 'account-a' as string | null,
    refresh: vi.fn(),
    info: vi.fn(),
    logout: vi.fn(),
    clearAuth: vi.fn(),
    push: vi.fn()
}))

vi.mock('@/api/auth', () => ({
    authApi: { refreshToken: state.refresh, getAdminInfo: state.info, logout: state.logout }
}))
vi.mock('@/utils/auth', () => ({ getToken: () => state.token, clearAuthInfo: state.clearAuth }))
vi.mock('@/utils/cache', () => ({
    default: {
        get: () => state.token,
        set: (_key: string, value: string) => {
            state.token = value
        },
        getKey: (key: string) => key
    }
}))
vi.mock('@/router', () => ({ default: { push: state.push } }))

function deferred<T>() {
    let resolve!: (value: T) => void
    let reject!: (reason: unknown) => void
    const promise = new Promise<T>((accept, fail) => {
        resolve = accept
        reject = fail
    })
    return { promise, resolve, reject }
}

beforeEach(() => {
    state.token = 'account-a'
    vi.clearAllMocks()
    invalidateAuthRequests()
    setActivePinia(createPinia())
})

describe('登录状态的异步写入', () => {
    it('旧会话刷新成功不能覆盖新登录的 token', async () => {
        const response = deferred<{ data: { token: string } }>()
        state.refresh.mockReturnValue(response.promise)
        const store = useUserStore()
        const refresh = store.refreshToken()
        const rejected = expect(refresh).rejects.toThrow('登录会话已改变')
        state.token = 'account-b'
        invalidateAuthRequests()
        store.token = 'account-b'
        response.resolve({ data: { token: 'refreshed-account-a' } })
        await rejected
        expect(state.token).toBe('account-b')
        expect(store.token).toBe('account-b')
        expect(state.clearAuth).not.toHaveBeenCalled()
    })

    it('旧会话刷新失败不能清除新登录信息', async () => {
        const response = deferred<never>()
        state.refresh.mockReturnValue(response.promise)
        const store = useUserStore()
        const refresh = store.refreshToken()
        const rejected = expect(refresh).rejects.toMatchObject({ code: 401 })
        state.token = 'account-b'
        invalidateAuthRequests()
        store.token = 'account-b'
        response.reject(Object.assign(new Error('expired'), { code: 401 }))
        await rejected
        expect(state.token).toBe('account-b')
        expect(store.token).toBe('account-b')
        expect(state.clearAuth).not.toHaveBeenCalled()
    })

    it('旧账号资料返回时不能写入新账号的 store', async () => {
        const response = deferred<{ data: { admin: { username: string } } }>()
        state.info.mockReturnValue(response.promise)
        const store = useUserStore()
        const info = store.getUserInfo()
        const rejected = expect(info).rejects.toThrow('登录会话已改变')
        state.token = 'account-b'
        invalidateAuthRequests()
        store.userInfo = { username: 'new-account' }
        response.resolve({ data: { admin: { username: 'old-account' } } })
        await rejected
        expect(store.userInfo.username).toBe('new-account')
    })

    it('旧会话登出完成不能清除新账号', async () => {
        const response = deferred<void>()
        state.logout.mockReturnValue(response.promise)
        const store = useUserStore()
        const logout = store.logout()
        const rejected = expect(logout).rejects.toThrow('登录会话已改变')
        state.token = 'account-b'
        invalidateAuthRequests()
        store.token = 'account-b'
        response.resolve()
        await rejected
        expect(store.token).toBe('account-b')
        expect(state.clearAuth).not.toHaveBeenCalled()
        expect(state.push).not.toHaveBeenCalled()
    })
})
