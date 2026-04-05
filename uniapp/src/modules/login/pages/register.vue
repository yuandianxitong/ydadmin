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
          <view class="i-ri-smartphone-line input-prefix" style="font-size: 36rpx; color: #999" />
          <input
            v-model="form.mobile"
            type="number"
            maxlength="11"
            placeholder="请输入手机号"
            class="uni-input"
            placeholder-class="input-placeholder"
          />
        </view>

        <!-- 短信验证码 -->
        <view class="input-group sms-group">
          <view class="i-ri-shield-check-line input-prefix" style="font-size: 36rpx; color: #999" />
          <input
            v-model="form.code"
            type="number"
            maxlength="6"
            placeholder="请输入验证码"
            class="uni-input"
            placeholder-class="input-placeholder"
          />
          <view class="send-code-btn" :class="{ disabled: countdown > 0 }" @tap="handleSendCode">
            {{ countdown > 0 ? `${countdown}s` : '获取验证码' }}
          </view>
        </view>

        <!-- 密码 -->
        <view class="input-group">
          <view class="i-ri-lock-line input-prefix" style="font-size: 36rpx; color: #999" />
          <input
            v-model="form.password"
            :password="!showPwd"
            placeholder="请设置密码（6-20位）"
            class="uni-input"
            placeholder-class="input-placeholder"
          />
          <view class="pwd-toggle" @tap="showPwd = !showPwd">
            <text class="pwd-toggle-text">{{ showPwd ? '隐藏' : '显示' }}</text>
          </view>
        </view>

        <!-- 确认密码 -->
        <view class="input-group">
          <view class="i-ri-lock-line input-prefix" style="font-size: 36rpx; color: #999" />
          <input
            v-model="form.confirmPassword"
            :password="!showConfirmPwd"
            placeholder="请再次输入密码"
            class="uni-input"
            placeholder-class="input-placeholder"
          />
          <view class="pwd-toggle" @tap="showConfirmPwd = !showConfirmPwd">
            <text class="pwd-toggle-text">{{ showConfirmPwd ? '隐藏' : '显示' }}</text>
          </view>
        </view>

        <!-- 协议 -->
        <d-agreement-check v-model="agreed" />

        <!-- 注册按钮 -->
        <u-button
          block
          :loading="loading"
          :disabled="loading"
          class="submit-btn"
          @click="handleRegister"
        >
          立即注册
        </u-button>

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
import { ref, reactive, onUnmounted } from 'vue'
import { authApi } from '@/api/auth'
import { isPassword } from '@/utils/validate'
import { useUserStore } from '@/store/user.store'

const userStore = useUserStore()

const loading = ref(false)
const agreed = ref(false)
const showPwd = ref(false)
const showConfirmPwd = ref(false)
const countdown = ref(0)
let countdownTimer: ReturnType<typeof setInterval> | null = null

const form = reactive({
  mobile: '',
  code: '',
  password: '',
  confirmPassword: '',
})

function startCountdown() {
  countdown.value = 60
  countdownTimer = setInterval(() => {
    countdown.value--
    if (countdown.value <= 0) {
      clearInterval(countdownTimer!)
      countdownTimer = null
    }
  }, 1000)
}

async function handleSendCode() {
  if (countdown.value > 0) return
  if (!/^1[3-9]\d{9}$/.test(form.mobile)) {
    uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
    return
  }
  try {
    await authApi.sendSmsCode({ mobile: form.mobile, scene: 'register' })
    uni.showToast({ title: '验证码已发送', icon: 'none' })
    startCountdown()
  } catch {
    // 错误已由请求拦截器处理
  }
}

async function handleRegister() {
  if (!/^1[3-9]\d{9}$/.test(form.mobile)) {
    uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
    return
  }
  if (!form.code) {
    uni.showToast({ title: '请输入验证码', icon: 'none' })
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
      code: form.code,
      password: form.password,
      password_confirmation: form.confirmPassword,
    })
    if (result.token) {
      // 通过 store 设置登录状态（token + userInfo 同步更新）
      const { setToken } = await import('@/utils/auth')
      setToken(result.token)
      userStore.token = result.token
      userStore.userInfo = (result as any).user_info || (result as any).user || null
      // #ifdef H5
      import('@/utils/wechat-oauth').then(({ bindOaOpenidAfterLogin }) => {
        bindOaOpenidAfterLogin()
      }).catch(() => {})
      // #endif
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

onUnmounted(() => {
  if (countdownTimer) {
    clearInterval(countdownTimer)
    countdownTimer = null
  }
})
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
  display: flex;
  align-items: center;
  margin-bottom: 24rpx;
  background: #f8f9fc;
  border-radius: 16rpx;
  padding: 0 24rpx;
  height: 96rpx;

  .input-prefix {
    flex-shrink: 0;
    margin-right: 16rpx;
  }

  .uni-input {
    flex: 1;
    height: 96rpx;
    font-size: 30rpx;
    color: $text-color;
    background: transparent;
  }

  .pwd-toggle {
    flex-shrink: 0;
    padding-left: 16rpx;

    .pwd-toggle-text {
      font-size: 26rpx;
      color: $text-color-secondary;
    }
  }

  .send-code-btn {
    flex-shrink: 0;
    padding-left: 20rpx;
    margin-left: 20rpx;
    border-left: 2rpx solid $border-color;
    font-size: 26rpx;
    color: $primary-color;
    white-space: nowrap;
    height: 100%;
    display: flex;
    align-items: center;

    &.disabled {
      color: $text-color-secondary;
    }
  }
}

.input-placeholder {
  color: #c0c4cc;
  font-size: 30rpx;
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
