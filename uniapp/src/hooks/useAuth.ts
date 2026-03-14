import { useUserStore } from '@/store/user.store'

export function useAuth() {
  const userStore = useUserStore()

  function checkLogin(): boolean {
    if (!userStore.isLoggedIn) {
      uni.navigateTo({ url: '/modules/login/pages/login' })
      return false
    }
    return true
  }

  return { checkLogin, isLoggedIn: userStore.isLoggedIn }
}
