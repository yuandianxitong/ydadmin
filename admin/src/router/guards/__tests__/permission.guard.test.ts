import { CanceledError } from 'axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { NavigationGuard, RouteLocationNormalized, Router } from 'vue-router'

import createPermissionGuard from '../permission.guard'

const state = vi.hoisted(() => ({
    revision: 0,
    token: 'account-a',
    clearAuth: vi.fn(),
    connect: vi.fn(),
    store: {
        userInfo: {},
        isRoutesInited: false,
        getUserInfo: vi.fn(),
        fetchMenus: vi.fn(),
        setRoutes: vi.fn()
    }
}))
vi.mock('nprogress', () => ({ default: { start: vi.fn(), done: vi.fn() } }))
vi.mock('@/constants', () => ({ appConfig: { title: 'test' } }))
vi.mock('@/router', () => ({
    default: {},
    filterAsyncRoutes: () => [],
    findFirstValidPath: () => '/home'
}))
vi.mock('@/router/routes.config', () => ({ INDEX_ROUTE: {}, INDEX_ROUTE_NAME: 'root' }))
vi.mock('@/store/modules/user.store', () => ({ default: () => state.store }))
vi.mock('@/store/modules/multipleTabs.store', () => ({
    default: () => ({ setIndexRouteName: vi.fn() })
}))
vi.mock('@/store/modules/realtime.store', () => ({ default: () => ({ connect: state.connect }) }))
vi.mock('@/utils/auth', () => ({ getToken: () => state.token, clearAuthInfo: state.clearAuth }))
vi.mock('@/utils/request-context', () => ({ getAuthRevision: () => state.revision }))
vi.mock('@/utils/i18n', () => ({ translateRouteTitle: () => '' }))

function registeredGuard() {
    let guard!: NavigationGuard
    const router = {
        beforeEach: (callback: NavigationGuard) => {
            guard = callback
        },
        afterEach: vi.fn(),
        addRoute: vi.fn(),
        getRoutes: () => []
    } as unknown as Router
    createPermissionGuard(router)
    const route = {
        path: '/system/admin',
        fullPath: '/system/admin',
        meta: {},
        name: 'admins'
    } as RouteLocationNormalized
    const next = vi.fn()
    return { run: () => guard.call(undefined, route, route, next), next, router }
}

beforeEach(() => {
    state.revision = 0
    state.token = 'account-a'
    vi.clearAllMocks()
})

describe('异步鉴权导航', () => {
    it('旧请求取消不会再次清理登录', async () => {
        state.store.getUserInfo.mockRejectedValue(new CanceledError('stale request'))
        const { run, next } = registeredGuard()
        await run()
        expect(next).toHaveBeenCalledWith(false)
        expect(state.clearAuth).not.toHaveBeenCalled()
    })

    it('账号切换后的旧导航失败不能清理新登录', async () => {
        state.store.getUserInfo.mockImplementation(async () => {
            state.revision++
            state.token = 'account-b'
            throw Object.assign(new Error('expired'), { code: 401 })
        })
        const { run, next } = registeredGuard()
        await run()
        expect(next).toHaveBeenCalledWith(false)
        expect(state.clearAuth).not.toHaveBeenCalled()
    })

    it('旧菜单请求返回后停止挂载路由', async () => {
        state.store.getUserInfo.mockResolvedValue({})
        state.store.fetchMenus.mockImplementation(async () => {
            state.revision++
            return []
        })
        const { run, next, router } = registeredGuard()
        await run()
        expect(next).toHaveBeenCalledWith(false)
        expect(router.addRoute).not.toHaveBeenCalled()
        expect(state.store.setRoutes).not.toHaveBeenCalled()
        expect(state.clearAuth).not.toHaveBeenCalled()
    })
})
