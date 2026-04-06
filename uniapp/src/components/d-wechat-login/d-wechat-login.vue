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
import type { LoginResult } from '@/types/api'

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

    // 注：微信 2.27.1+ 基础库起 uni.getUserProfile 已被废弃，调用会直接返回 fail 且无弹窗。
    // 用户昵称/头像应通过用户自己在"个人资料"页设置，此处不再尝试调用。

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
