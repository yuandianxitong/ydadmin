<template>
  <div class="w-full max-w-400px">
    <div class="card p-8">
      <h2 class="text-2xl font-bold text-center text-gray-900 mb-8">注册</h2>
      <form @submit.prevent="handleRegister">
        <div class="mb-4">
          <label class="block text-sm text-gray-600 mb-1">手机号/账号</label>
          <input
            v-model="form.account"
            type="text"
            placeholder="请输入手机号或账号"
            class="form-input"
          />
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

definePageMeta({ layout: 'blank' })

const message = useMessage()
const userStore = useUserStore()
const router = useRouter()
const form = reactive({ account: '', password: '', password_confirmation: '' })
const submitting = ref(false)

async function handleRegister() {
  if (!form.account) { message.warning('请输入手机号或账号'); return }
  if (!form.password || form.password.length < 6) { message.warning('密码长度至少6位'); return }
  if (form.password !== form.password_confirmation) { message.warning('两次密码输入不一致'); return }

  submitting.value = true
  try {
    const res = await authApi.register(form)
    if (res.code === 1) {
      message.success('注册成功')
      userStore.$patch({ token: res.data.token })
      setToken(res.data.token)
      router.push('/')
    } else {
      message.error(res.msg || '注册失败')
    }
  } catch {
    message.error('网络错误，请重试')
  } finally {
    submitting.value = false
  }
}
</script>
