<template>
  <u-button
    :type="type"
    :size="size"
    :block="block"
    :round="round"
    :loading="loading"
    :disabled="loading"
    @click="handleLogin"
  >
    {{ loading ? '登录中...' : text }}
  </u-button>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { authApi } from '@/api/auth'
import type { LoginResult, UserInfo } from '@/types/api'

withDefaults(defineProps<{
  text?: string
  type?: 'primary' | 'success' | 'warning' | 'danger' | 'error' | 'default' | 'info'
  size?: 'small' | 'medium' | 'large'
  block?: boolean
  round?: boolean
}>(), {
  text: '微信登录',
  type: 'primary',
  size: 'large',
  block: true,
  round: true,
})

const emit = defineEmits<{
  success: [userInfo: LoginResult]
  fail: [error: any]
}>()

const loading = ref(false)

async function handleLogin() {
  if (loading.value) return
  loading.value = true

  try {
    // #ifdef MP-WEIXIN
    const loginRes = await new Promise<UniApp.LoginRes>((resolve, reject) => {
      uni.login({
        provider: 'weixin',
        success: resolve,
        fail: reject,
      })
    })

    if (!loginRes.code) {
      throw new Error('获取微信登录凭证失败')
    }

    // 尝试获取用户信息
    try {
      await new Promise<any>((resolve, reject) => {
        uni.getUserProfile({
          desc: '用于展示用户信息',
          success: resolve,
          fail: reject,
        })
      })
    } catch {
      // 用户拒绝授权，仍可继续登录
    }

    const res = await authApi.wechatMiniLogin({ code: loginRes.code })
    emit('success', res as unknown as LoginResult)
    // #endif

    // #ifndef MP-WEIXIN
    throw new Error('当前环境不支持微信登录')
    // #endif
  } catch (err: any) {
    emit('fail', err)
  } finally {
    loading.value = false
  }
}
</script>
