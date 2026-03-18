<template>
  <div class="w-full max-w-400px">
    <div class="card p-8">
      <h2 class="text-2xl font-bold text-center text-gray-900 mb-8">登录</h2>

      <!-- Tab 切换 -->
      <div class="flex border-b border-gray-200 mb-6">
        <button
          class="flex-1 pb-2.5 text-sm transition-colors relative"
          :class="loginType === 'password'
            ? 'text-[var(--color-primary)] font-medium'
            : 'text-gray-400 hover:text-gray-600'"
          @click="loginType = 'password'"
        >
          密码登录
          <span
            v-if="loginType === 'password'"
            class="absolute bottom-0 left-0 right-0 mx-auto w-10 h-0.5 bg-[var(--color-primary)]"
          />
        </button>
        <button
          class="flex-1 pb-2.5 text-sm transition-colors relative"
          :class="loginType === 'sms'
            ? 'text-[var(--color-primary)] font-medium'
            : 'text-gray-400 hover:text-gray-600'"
          @click="loginType = 'sms'"
        >
          验证码登录
          <span
            v-if="loginType === 'sms'"
            class="absolute bottom-0 left-0 right-0 mx-auto w-10 h-0.5 bg-[var(--color-primary)]"
          />
        </button>
      </div>

      <!-- 密码登录 -->
      <form v-if="loginType === 'password'" @submit.prevent="handlePasswordLogin">
        <div class="mb-4">
          <label class="block text-sm text-gray-600 mb-1">手机号/账号</label>
          <input
            v-model="passwordForm.account"
            type="text"
            placeholder="请输入手机号或账号"
            class="form-input"
          />
        </div>
        <div class="mb-6">
          <label class="block text-sm text-gray-600 mb-1">密码</label>
          <input
            v-model="passwordForm.password"
            type="password"
            placeholder="请输入密码"
            class="form-input"
          />
        </div>
        <button
          type="submit"
          :disabled="submitting"
          class="w-full btn-primary justify-center"
          :class="{ 'opacity-60 cursor-not-allowed': submitting }"
        >
          {{ submitting ? '登录中...' : '登录' }}
        </button>
      </form>

      <!-- 验证码登录 -->
      <form v-else @submit.prevent="handleSmsLogin">
        <div class="mb-4">
          <label class="block text-sm text-gray-600 mb-1">手机号</label>
          <input
            v-model="smsForm.mobile"
            type="text"
            maxlength="11"
            placeholder="请输入手机号"
            class="form-input"
          />
        </div>
        <div class="mb-6">
          <label class="block text-sm text-gray-600 mb-1">验证码</label>
          <div class="flex gap-2">
            <input
              v-model="smsForm.code"
              type="text"
              maxlength="6"
              placeholder="请输入验证码"
              class="form-input flex-1"
            />
            <button
              type="button"
              :disabled="countdown > 0"
              class="btn-outline text-sm flex-shrink-0 !px-3"
              @click="handleSendCode"
            >
              {{ countdown > 0 ? `${countdown}s` : '获取验证码' }}
            </button>
          </div>
        </div>
        <button
          type="submit"
          :disabled="submitting"
          class="w-full btn-primary justify-center"
          :class="{ 'opacity-60 cursor-not-allowed': submitting }"
        >
          {{ submitting ? '登录中...' : '登录' }}
        </button>
      </form>

      <!-- 第三方登录 -->
      <div class="mt-6 pt-6 border-t border-gray-100">
        <p class="text-center text-xs text-gray-400 mb-4">其他登录方式</p>
        <div class="flex justify-center">
          <button
            class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center hover:bg-green-600 transition-colors"
            title="微信登录"
            @click="handleWechatLogin"
          >
            <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor">
              <path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098 10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178A1.17 1.17 0 0 1 4.623 7.17c0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178 1.17 1.17 0 0 1-1.162-1.178c0-.651.52-1.18 1.162-1.18zm5.34 2.867c-1.797-.052-3.746.512-5.28 1.786-1.72 1.428-2.687 3.72-1.78 6.22.942 2.453 3.666 4.229 6.884 4.229.826 0 1.622-.12 2.361-.336a.722.722 0 0 1 .598.082l1.584.926a.272.272 0 0 0 .14.047c.134 0 .24-.111.24-.247 0-.06-.023-.12-.038-.177l-.327-1.233a.582.582 0 0 1-.023-.156.49.49 0 0 1 .201-.398C23.024 18.48 24 16.82 24 14.98c0-3.21-2.931-5.837-6.656-6.088v-.035h-.407zm-2.53 3.274c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982zm4.844 0c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.969-.982z" />
            </svg>
          </button>
        </div>
      </div>

      <p class="text-center text-sm text-gray-400 mt-6">
        还没有账号？<NuxtLink to="/register" class="text-[var(--color-primary)] hover:text-[var(--color-primary-hover)]">立即注册</NuxtLink>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useMessage } from 'naive-ui'
import { useUserStore } from '~/store/user'
import { commonApi } from '~/api/common'
import { authApi } from '~/api/auth'
import { setToken } from '~/composables/useRequest'

definePageMeta({ layout: 'blank' })

const message = useMessage()
const userStore = useUserStore()
const router = useRouter()
const route = useRoute()
const submitting = ref(false)
const loginType = ref<'password' | 'sms'>('password')
const countdown = ref(0)
let timer: ReturnType<typeof setInterval> | null = null

const passwordForm = reactive({ account: '', password: '' })
const smsForm = reactive({ mobile: '', code: '' })

// 密码登录
async function handlePasswordLogin() {
  if (!passwordForm.account || !passwordForm.password) return
  submitting.value = true
  try {
    const res = await userStore.login(passwordForm)
    if (res.code === 1) {
      message.success('登录成功')
      router.push('/')
    } else {
      message.error(res.msg || '登录失败')
    }
  } catch {
    message.error('网络错误，请重试')
  } finally {
    submitting.value = false
  }
}

// 验证码登录
async function handleSmsLogin() {
  if (!smsForm.mobile || !smsForm.code) return
  submitting.value = true
  try {
    const res = await userStore.smsLogin(smsForm)
    if (res.code === 1) {
      message.success('登录成功')
      router.push('/')
    } else {
      message.error(res.msg || '登录失败')
    }
  } catch {
    message.error('网络错误，请重试')
  } finally {
    submitting.value = false
  }
}

// 发送验证码
async function handleSendCode() {
  if (!smsForm.mobile) { message.warning('请输入手机号'); return }
  if (countdown.value > 0) return
  try {
    const res = await commonApi.sendSmsCode({ mobile: smsForm.mobile })
    if (res.code === 1) {
      message.success('验证码已发送')
      countdown.value = 60
      timer = setInterval(() => {
        countdown.value--
        if (countdown.value <= 0 && timer) {
          clearInterval(timer)
          timer = null
        }
      }, 1000)
    } else {
      message.error(res.msg || '发送失败')
    }
  } catch {
    message.error('网络错误')
  }
}

// 微信登录
function handleWechatLogin() {
  // 构造微信 OAuth 授权 URL，跳转后微信会回调到当前页面带 code 参数
  const redirectUri = encodeURIComponent(window.location.origin + '/pc/login')
  const appId = 'wx_appid' // TODO: 从系统配置获取
  const url = `https://open.weixin.qq.com/connect/qrconnect?appid=${appId}&redirect_uri=${redirectUri}&response_type=code&scope=snsapi_login&state=pc_login#wechat_redirect`
  window.location.href = url
}

// 处理微信回调
async function handleWechatCallback(code: string) {
  submitting.value = true
  try {
    const res = await authApi.wechatLogin({ code })
    if (res.code === 1) {
      userStore.$patch({ token: res.data.token })
      setToken(res.data.token)
      message.success('登录成功')
      router.push('/')
    } else {
      message.error(res.msg || '微信登录失败')
    }
  } catch {
    message.error('微信登录失败，请重试')
  } finally {
    submitting.value = false
  }
}

// 页面加载时检查微信回调 code
onMounted(() => {
  const code = route.query.code as string
  if (code) {
    handleWechatCallback(code)
  }
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>
