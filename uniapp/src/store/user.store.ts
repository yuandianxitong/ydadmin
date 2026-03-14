import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { getToken, setToken, removeToken } from '@/utils/auth'
import { authApi } from '@/api/auth'
import type { UserInfo, LoginResult } from '@/types/api'

export const useUserStore = defineStore('user', () => {
  const token = ref(getToken())
  const userInfo = ref<UserInfo | null>(null)

  const isLoggedIn = computed(() => !!token.value)
  const nickname = computed(() => userInfo.value?.nickname || '')
  const avatar = computed(() => userInfo.value?.avatar || '')

  async function login(params: { mobile: string; password: string }): Promise<LoginResult> {
    const result = await authApi.login(params)
    token.value = result.token
    userInfo.value = result.user
    setToken(result.token)
    return result
  }

  async function smsLogin(params: { mobile: string; code: string }): Promise<LoginResult> {
    const result = await authApi.smsLogin(params)
    token.value = result.token
    userInfo.value = result.user
    setToken(result.token)
    return result
  }

  async function getUserInfo(): Promise<UserInfo> {
    const result = await authApi.getUserInfo()
    userInfo.value = result
    return result
  }

  function logout(options?: { redirect?: boolean }) {
    authApi.logout().catch(() => {})
    token.value = ''
    userInfo.value = null
    removeToken()
    if (options?.redirect !== false) {
      uni.reLaunch({ url: '/modules/login/pages/login' })
    }
  }

  return { token, userInfo, isLoggedIn, nickname, avatar, login, smsLogin, getUserInfo, logout }
})
