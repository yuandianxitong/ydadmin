import { ref } from 'vue'
import { useUserStore } from '@/store/user.store'
import { authApi } from '@/api/auth'
import { isMobile, isPassword, isVerifyCode } from '@/utils/validate'

export function useLogin() {
  const userStore = useUserStore()
  const loading = ref(false)
  const loginType = ref<'password' | 'sms'>('password')
  const countdown = ref(0)

  async function loginByPassword(mobile: string, password: string) {
    if (!isMobile(mobile)) {
      uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
      return
    }
    if (!isPassword(password)) {
      uni.showToast({ title: '密码长度6-20位', icon: 'none' })
      return
    }

    loading.value = true
    try {
      await userStore.login({ mobile, password })
      uni.reLaunch({ url: '/pages/index/index' })
    } finally {
      loading.value = false
    }
  }

  async function loginBySms(mobile: string, code: string) {
    if (!isMobile(mobile)) {
      uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
      return
    }
    if (!isVerifyCode(code)) {
      uni.showToast({ title: '请输入正确的验证码', icon: 'none' })
      return
    }

    loading.value = true
    try {
      await userStore.smsLogin({ mobile, code })
      uni.reLaunch({ url: '/pages/index/index' })
    } finally {
      loading.value = false
    }
  }

  async function sendCode(mobile: string) {
    if (!isMobile(mobile)) {
      uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
      return
    }
    if (countdown.value > 0) return

    await authApi.sendSmsCode({ mobile })
    uni.showToast({ title: '验证码已发送', icon: 'none' })
    countdown.value = 60
    const timer = setInterval(() => {
      countdown.value--
      if (countdown.value <= 0) clearInterval(timer)
    }, 1000)
  }

  return { loading, loginType, countdown, loginByPassword, loginBySms, sendCode }
}
