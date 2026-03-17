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
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 transition-colors"
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
              class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 transition-colors"
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
        <div class="mb-6">
          <label class="block text-sm text-gray-600 mb-1">密码</label>
          <input
            v-model="form.password"
            type="password"
            placeholder="请设置密码"
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 transition-colors"
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
        已有账号？<NuxtLink to="/login" class="text-blue-600 hover:text-blue-700">立即登录</NuxtLink>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useUserStore } from '~/store/user'
import { setToken } from '~/composables/useRequest'
import { commonApi } from '~/api/common'
import { authApi } from '~/api/auth'

definePageMeta({ layout: 'blank' })

const userStore = useUserStore()
const router = useRouter()
const form = reactive({ mobile: '', password: '', code: '' })
const submitting = ref(false)
const countdown = ref(0)
let timer: ReturnType<typeof setInterval> | null = null

async function handleSendCode() {
  if (!form.mobile) return alert('请输入手机号')
  try {
    const res = await commonApi.sendSmsCode({ mobile: form.mobile })
    if (res.code === 1) {
      countdown.value = 60
      timer = setInterval(() => {
        countdown.value--
        if (countdown.value <= 0 && timer) {
          clearInterval(timer)
          timer = null
        }
      }, 1000)
    } else {
      alert(res.msg || '发送失败')
    }
  } catch {
    alert('网络错误')
  }
}

async function handleRegister() {
  if (!form.mobile || !form.password || !form.code) return
  submitting.value = true
  try {
    const res = await authApi.register(form)
    if (res.code === 1) {
      userStore.$patch({ token: res.data.token })
      setToken(res.data.token)
      router.push('/')
    } else {
      alert(res.msg || '注册失败')
    }
  } catch {
    alert('网络错误，请重试')
  } finally {
    submitting.value = false
  }
}

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>
