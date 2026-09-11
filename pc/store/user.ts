import { defineStore } from 'pinia'
import { authApi, type UserInfo } from '~/api/auth'
import { getToken, setToken, removeToken } from '~/composables/useRequest'

export const useUserStore = defineStore('user', {
  state: () => ({
    token: (import.meta.client ? getToken() : null) || '',
    userInfo: null as UserInfo | null,
  }),

  getters: {
    isLoggedIn: (state) => !!state.token,
  },

  actions: {
    async login(data: { account: string; password: string }) {
      const res = await authApi.login(data)
      if (res.code === 200) {
        this.token = res.data.token
        setToken(res.data.token)
      }
      return res
    },

    async smsLogin(data: { mobile: string; code: string }) {
      const res = await authApi.smsLogin(data)
      if (res.code === 200) {
        this.token = res.data.token
        setToken(res.data.token)
      }
      return res
    },

    async fetchUserInfo() {
      if (!this.token) return
      const res = await authApi.getUserInfo()
      if (res.code === 200) {
        this.userInfo = res.data
      }
      return res
    },

    async logout() {
      try {
        await authApi.logout()
      } finally {
        this.token = ''
        this.userInfo = null
        removeToken()
      }
    },
  },
})
