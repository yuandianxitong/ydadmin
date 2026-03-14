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
        <!-- 手机号 -->
        <view class="input-group">
          <wd-input
            v-model="form.mobile"
            type="number"
            maxlength="11"
            placeholder="请输入手机号"
            prefix-icon="mobile"
            clearable
            no-border
            class="custom-input"
          />
        </view>

        <!-- 验证码 -->
        <view class="input-group sms-group">
          <wd-input
            v-model="form.code"
            type="number"
            maxlength="6"
            placeholder="请输入验证码"
            prefix-icon="secured"
            no-border
            class="custom-input sms-input"
          />
          <view
            class="send-code-btn"
            :class="{ disabled: countdown > 0 }"
            @tap="handleSendCode"
          >
            {{ countdown > 0 ? `${countdown}s` : '获取验证码' }}
          </view>
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
import { useUserStore } from '@/store/user.store'
import { isMobile, isPassword, isVerifyCode } from '@/utils/validate'

const userStore = useUserStore()

const loading = ref(false)
const countdown = ref(0)
const agreed = ref(false)

const form = reactive({
  mobile: '',
  code: '',
  password: '',
  confirmPassword: '',
})

async function handleSendCode() {
  if (!isMobile(form.mobile)) {
    uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
    return
  }
  if (countdown.value > 0) return

  try {
    await authApi.sendSmsCode({ mobile: form.mobile })
    uni.showToast({ title: '验证码已发送', icon: 'none' })
    countdown.value = 60
    const timer = setInterval(() => {
      countdown.value--
      if (countdown.value <= 0) clearInterval(timer)
    }, 1000)
  } catch {
    // error handled by request interceptor
  }
}

async function handleRegister() {
  if (!isMobile(form.mobile)) {
    uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
    return
  }
  if (!isVerifyCode(form.code)) {
    uni.showToast({ title: '请输入正确的验证码', icon: 'none' })
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
      mobile: form.mobile,
      password: form.password,
      code: form.code,
    })
    // Use register result directly (contains token + user)
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

  &.sms-group {
    display: flex;
    align-items: center;
  }

  .custom-input {
    flex: 1;
    background: transparent;
  }

  .sms-input {
    border-right: 2rpx solid $border-color;
  }

  .send-code-btn {
    padding: 0 28rpx;
    font-size: 26rpx;
    color: $primary-color;
    white-space: nowrap;
    flex-shrink: 0;
    height: 100%;
    display: flex;
    align-items: center;

    &.disabled {
      color: $text-color-secondary;
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
