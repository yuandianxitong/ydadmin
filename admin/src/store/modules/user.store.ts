// src/store/modules/user.store.ts
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { RouteRecordRaw } from 'vue-router'

import { authApi } from '@/api/auth'
import { menuApi } from '@/api/menu'
import { TOKEN_KEY } from '@/constants/cache'
import { PageEnum } from '@/constants/page'
import router from '@/router'
import type { AdminInfo, AuthInfoRes, MenuInfo, RoleInfo } from '@/types/api'
import { clearAuthInfo, getToken } from '@/utils/auth'
import cache from '@/utils/cache'

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
        try {
            const response = await authApi.login({
                username: payload.username.trim(),
                password: payload.password,
                captcha: payload.captcha,
                captcha_key: payload.captcha_key
            })
            token.value = response.data.token
            userInfo.value = response.data.admin
            permissions.value = response.data.admin?.permissions || []
            cache.set(TOKEN_KEY, response.data.token)
            return response.data
        } catch (error) {
            return Promise.reject(error)
        }
    }

    async function logout() {
        try {
            await authApi.logout()
            resetState()
            clearAuthInfo()
            await router.push(PageEnum.LOGIN)
            return true
        } catch (error) {
            return Promise.reject(error)
        }
    }

    async function getUserInfo() {
        try {
            const response = await authApi.getAdminInfo()
            const data = response.data as unknown as AuthInfoRes
            userInfo.value = data.admin || {}
            permissions.value = data.permissions || userInfo.value.permissions || []

            // 优先使用 info 接口内置的 routes；否则单独拉取
            if (Array.isArray(data.routes)) {
                menus.value = data.routes
            } else {
                const menuResponse = await menuApi.getAdminRoutes()
                menus.value = menuResponse.data || []
            }
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
        try {
            const response = await authApi.refreshToken()
            const newToken = response.data.token
            token.value = newToken
            cache.set(TOKEN_KEY, newToken)
            return response
        } catch (error) {
            console.error('刷新Token失败:', error)
            resetState()
            clearAuthInfo()
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
        if (permissions.value.includes('*') || permissions.value.includes('super_admin')) {
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
