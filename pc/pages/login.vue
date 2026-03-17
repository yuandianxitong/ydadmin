<template>
  <div class="w-full max-w-400px">
    <div class="card p-8">
      <h2 class="text-2xl font-bold text-center text-gray-900 mb-8">登录</h2>
      <form @submit.prevent="handleLogin">
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
        <div class="mb-6">
          <label class="block text-sm text-gray-600 mb-1">密码</label>
          <input
            v-model="form.password"
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
      <p class="text-center text-sm text-gray-400 mt-6">
        还没有账号？<NuxtLink to="/register" class="text-[var(--color-primary)] hover:text-[var(--color-primary-hover)]">立即注册</NuxtLink>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useUserStore } from '~/store/user'

definePageMeta({ layout: 'blank' })

const userStore = useUserStore()
const router = useRouter()
const form = reactive({ mobile: '', password: '' })
const submitting = ref(false)

async function handleLogin() {
  if (!form.mobile || !form.password) return
  submitting.value = true
  try {
    const res = await userStore.login(form)
    if (res.code === 1) {
      router.push('/')
    } else {
      alert(res.msg || '登录失败')
    }
  } catch {
    alert('网络错误，请重试')
  } finally {
    submitting.value = false
  }
}
</script>
