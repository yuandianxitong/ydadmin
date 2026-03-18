<template>
  <d-page :safe-area="true">
    <view class="register-page">
      <!-- 页面标题 -->
      <view class="page-header">
        <text class="page-title">创建账号</text>
        <text class="page-subtitle">注册后即可使用全部功能</text>
      </view>

      <!-- 注册表单 -->
      <view class="form-card">
        <!-- 手机号/账号 -->
        <view class="input-group">
          <wd-input
            v-model="form.account"
            placeholder="请输入手机号或账号"
            prefix-icon="mobile"
            clearable
            no-border
            class="custom-input"
          />
        </view>

        <!-- 密码 -->
        <view class="input-group">
          <wd-input
            v-model="form.password"
            :show-password="true"
            placeholder="请设置密码（6-20位）"
            prefix-icon="lock-on"
            clearable
            no-border
            class="custom-input"
          />
        </view>

        <!-- 确认密码 -->
        <view class="input-group">
          <wd-input
            v-model="form.confirmPassword"
            :show-password="true"
            placeholder="请再次输入密码"
            prefix-icon="lock-on"
            clearable
            no-border
            class="custom-input"
          />
        </view>

        <!-- 协议 -->
        <d-agreement-check v-model="agreed" />

        <!-- 注册按钮 -->
        <wd-button
          block
          :loading="loading"
          :disabled="loading"
          class="submit-btn"
          @click="handleRegister"
        >
          立即注册
        </wd-button>

        <!-- 去登录 -->
        <view class="bottom-links">
          <text class="normal-text">已有账号？</text>
          <text class="link" @tap="goLogin">立即登录</text>
        </view>
      </view>
    </view>
  </d-page>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { authApi } from '@/api/auth'
import { isPassword } from '@/utils/validate'

const loading = ref(false)
const agreed = ref(false)

const form = reactive({
  account: '',
  password: '',
  confirmPassword: '',
})

async function handleRegister() {
  if (!form.account) {
    uni.showToast({ title: '请输入手机号或账号', icon: 'none' })
    return
  }
  if (!isPassword(form.password)) {
    uni.showToast({ title: '密码长度6-20位', icon: 'none' })
    return
  }
  if (form.password !== form.confirmPassword) {
    uni.showToast({ title: '两次密码输入不一致', icon: 'none' })
    return
  }
  if (!agreed.value) {
    uni.showToast({ title: '请先同意用户协议', icon: 'none' })
    return
  }

  loading.value = true
  try {
    const result = await authApi.register({
      account: form.account,
      password: form.password,
      password_confirmation: form.confirmPassword,
    })
    if (result.token) {
      const { setToken } = await import('@/utils/auth')
      setToken(result.token)
    }
    uni.showToast({ title: '注册成功' })
    setTimeout(() => {
      uni.reLaunch({ url: '/pages/index/index' })
    }, 1500)
  } finally {
    loading.value = false
  }
}

function goLogin() {
  uni.navigateBack()
}
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.register-page {
  padding: 20rpx 0;
}

.page-header {
  margin-bottom: 48rpx;

  .page-title {
    display: block;
    font-size: 52rpx;
    font-weight: 700;
    color: $text-color;
    margin-bottom: 12rpx;
  }

  .page-subtitle {
    font-size: 28rpx;
    color: $text-color-secondary;
  }
}

.form-card {
  background: #ffffff;
  border-radius: 32rpx;
  padding: 48rpx 40rpx;
  box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);
}

.input-group {
  margin-bottom: 24rpx;
  background: #f8f9fc;
  border-radius: 16rpx;
  overflow: hidden;
  min-height: 96rpx;

  .custom-input {
    flex: 1;
    background: transparent;

    :deep(.wd-input) {
      min-height: 96rpx;
      padding: 0 24rpx;
    }

    :deep(.wd-input__inner) {
      font-size: 30rpx;
    }
  }
}

.submit-btn {
  margin-top: 16rpx;
  border-radius: 16rpx !important;
  height: 96rpx !important;
  font-size: 32rpx !important;
}

.bottom-links {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-top: 32rpx;

  .normal-text {
    font-size: 28rpx;
    color: $text-color-secondary;
  }

  .link {
    font-size: 28rpx;
    color: $primary-color;
    padding: 10rpx 10rpx;
  }
}
</style>
