<template>
  <div class="w-full max-w-400px">
    <div class="card p-8">
      <h2 class="text-2xl font-bold text-center text-gray-900 mb-8">注册</h2>
      <form @submit.prevent="handleRegister">
        <div class="mb-4">
          <label class="block text-sm text-gray-600 mb-1">手机号</label>
          <input
            v-model="form.mobile"
            type="text"
            maxlength="11"
            placeholder="请输入手机号"
            class="form-input"
          />
        </div>
        <div class="mb-4">
          <label class="block text-sm text-gray-600 mb-1">验证码</label>
          <div class="flex gap-2">
            <input
              v-model="form.code"
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
        <div class="mb-4">
          <label class="block text-sm text-gray-600 mb-1">密码</label>
          <input
            v-model="form.password"
            type="password"
            placeholder="请设置密码（6-20位）"
            class="form-input"
          />
        </div>
        <div class="mb-6">
          <label class="block text-sm text-gray-600 mb-1">确认密码</label>
          <input
            v-model="form.password_confirmation"
            type="password"
            placeholder="请再次输入密码"
            class="form-input"
          />
        </div>
        <button
          type="submit"
          :disabled="submitting"
          class="w-full btn-primary justify-center"
          :class="{ 'opacity-60 cursor-not-allowed': submitting }"
        >
          {{ submitting ? '注册中...' : '注册' }}
        </button>
      </form>
      <p class="text-center text-sm text-gray-400 mt-6">
        已有账号？<NuxtLink to="/login" class="text-[var(--color-primary)] hover:text-[var(--color-primary-hover)]">立即登录</NuxtLink>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useMessage } from 'naive-ui'
import { useUserStore } from '~/store/user'
import { setToken } from '~/composables/useRequest'
import { authApi } from '~/api/auth'
import { commonApi } from '~/api/common'

definePageMeta({ layout: 'blank' })

const message = useMessage()
const userStore = useUserStore()
const router = useRouter()
const form = reactive({ mobile: '', code: '', password: '', password_confirmation: '' })
const submitting = ref(false)
const countdown = ref(0)
let timer: ReturnType<typeof setInterval> | null = null

async function handleSendCode() {
  if (!form.mobile) { message.warning('请输入手机号'); return }
  if (!/^1[3-9]\d{9}$/.test(form.mobile)) { message.warning('请输入正确的手机号'); return }
  if (countdown.value > 0) return
  try {
    const res = await commonApi.sendSmsCode({ mobile: form.mobile, scene: 'register' })
    if (res.code === 200) {
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
      message.error(res.message || '发送失败')
    }
  } catch {
    message.error('网络错误')
  }
}

async function handleRegister() {
  if (!form.mobile) { message.warning('请输入手机号'); return }
  if (!form.code) { message.warning('请输入验证码'); return }
  if (!form.password || form.password.length < 6) { message.warning('密码长度至少6位'); return }
  if (form.password !== form.password_confirmation) { message.warning('两次密码输入不一致'); return }

  submitting.value = true
  try {
    const res = await authApi.register(form)
    if (res.code === 200) {
      message.success('注册成功')
      userStore.$patch({ token: res.data.token })
      setToken(res.data.token)
      router.push('/')
    } else {
      message.error(res.message || '注册失败')
    }
  } catch {
    message.error('网络错误，请重试')
  } finally {
    submitting.value = false
  }
}

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>
