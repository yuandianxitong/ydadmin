// src/store/modules/user.store.ts
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { RouteRecordRaw } from 'vue-router'

import { authApi } from '@/api/auth'
import { TOKEN_KEY } from '@/constants/cache'
import { PageEnum } from '@/constants/page'
import router from '@/router'
import type { AdminInfo, AuthInfoRes, MenuInfo, RoleInfo } from '@/types/system'
import { clearAuthInfo, getToken } from '@/utils/auth'
import cache from '@/utils/cache'
import { getAuthRevision, invalidateAuthRequests } from '@/utils/request-context'

export const useUserStore = defineStore('user', () => {
    // ========== State ==========
    const token = ref<string>(getToken() || '')
    const userInfo = ref<Partial<AdminInfo>>({})
    const permissions = ref<string[]>([])
    const menus = ref<MenuInfo[]>([])
    const routes = ref<RouteRecordRaw[]>([])
    const isRoutesInited = ref(false)

    // ========== Computed ==========
    const getAdminName = computed<string>(() => userInfo.value?.username || '')
    const getAdminNickname = computed<string>(
        () => userInfo.value?.nickname || userInfo.value?.username || ''
    )
    const getAdminAvatar = computed<string>(() => userInfo.value?.avatar || '')
    const getAdminRoles = computed<RoleInfo[]>(() => userInfo.value?.roles || [])
    const isLoggedIn = computed(() => !!token.value && Object.keys(userInfo.value).length > 0)

    // ========== Methods ==========

    function resetState() {
        token.value = ''
        userInfo.value = {}
        permissions.value = []
        menus.value = []
        routes.value = []
        isRoutesInited.value = false
    }

    function getTokenValue(): string {
        return token.value
    }

    async function login(payload: {
        username: string
        password: string
        captcha: string
        captcha_key: string
    }) {
        const revision = getAuthRevision()
        try {
            const response = await authApi.login({
                username: payload.username.trim(),
                password: payload.password,
                captcha: payload.captcha,
                captcha_key: payload.captcha_key
            })
            if (revision !== getAuthRevision()) throw new Error('登录会话已改变')
            cache.set(TOKEN_KEY, response.data.token)
            invalidateAuthRequests()
            token.value = response.data.token
            userInfo.value = response.data.admin
            permissions.value = response.data.admin?.permissions || []
            return response.data
        } catch (error) {
            return Promise.reject(error)
        }
    }

    async function logout() {
        const revision = getAuthRevision()
        try {
            await authApi.logout()
            if (revision !== getAuthRevision()) throw new Error('登录会话已改变')
            resetState()
            clearAuthInfo()
            await router.push(PageEnum.LOGIN)
            return true
        } catch (error) {
            return Promise.reject(error)
        }
    }

    async function getUserInfo() {
        const revision = getAuthRevision()
        try {
            const response = await authApi.getAdminInfo()
            if (revision !== getAuthRevision()) throw new Error('登录会话已改变')
            const data = response.data as unknown as AuthInfoRes
            userInfo.value = data.admin || {}
            permissions.value = data.permissions || userInfo.value.permissions || []

            menus.value = data.routes || []
            return { admin: userInfo.value, menu: menus.value }
        } catch (error) {
            return Promise.reject(error)
        }
    }

    async function fetchMenus(): Promise<MenuInfo[]> {
        if (!menus.value || menus.value.length === 0) {
            await getUserInfo()
        }
        return menus.value
    }

    async function refreshToken() {
        const previous = getToken()
        const revision = getAuthRevision()
        try {
            const response = await authApi.refreshToken()
            const newToken = response.data.token
            if (revision !== getAuthRevision()) throw new Error('登录会话已改变')
            token.value = newToken
            cache.set(TOKEN_KEY, newToken)
            return response
        } catch (error) {
            if (revision !== getAuthRevision()) throw error
            const latest = getToken()
            if (latest && latest !== previous) {
                token.value = latest
                throw error
            }
            const code = (error as { code?: number }).code
            if (code === 401) {
                resetState()
                clearAuthInfo()
            }
            throw error
        }
    }

    function setRoutes(newRoutes: RouteRecordRaw[]) {
        routes.value = newRoutes
        isRoutesInited.value = true
    }

    // ========== 权限检查 ==========

    function hasPermission(permission: string): boolean {
        if (!permission) return true
        if (permissions.value.includes('*')) {
            return true
        }
        return permissions.value.includes(permission)
    }

    function hasAnyPermission(permissionList: string[]): boolean {
        if (!permissionList || permissionList.length === 0) return true
        return permissionList.some((p) => hasPermission(p))
    }

    function hasAllPermissions(permissionList: string[]): boolean {
        if (!permissionList || permissionList.length === 0) return true
        return permissionList.every((p) => hasPermission(p))
    }

    return {
        // State
        token,
        userInfo,
        permissions,
        menus,
        routes,
        isRoutesInited,

        // Computed
        getAdminName,
        getAdminNickname,
        getAdminAvatar,
        getAdminRoles,
        isLoggedIn,

        // Methods
        resetState,
        getToken: getTokenValue,
        login,
        logout,
        getUserInfo,
        fetchMenus,
        refreshToken,
        setRoutes,
        hasPermission,
        hasAnyPermission,
        hasAllPermissions
    }
})

export default useUserStore
