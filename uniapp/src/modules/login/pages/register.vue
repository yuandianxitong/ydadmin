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
          <wd-icon name="mobile" size="36rpx" color="#999" class="input-prefix" />
          <input
            v-model="form.account"
            placeholder="请输入手机号或账号"
            class="uni-input"
            placeholder-class="input-placeholder"
          />
        </view>

        <!-- 密码 -->
        <view class="input-group">
          <wd-icon name="lock-on" size="36rpx" color="#999" class="input-prefix" />
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
          <wd-icon name="lock-on" size="36rpx" color="#999" class="input-prefix" />
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
import { useUserStore } from '@/store/user.store'

const userStore = useUserStore()

const loading = ref(false)
const agreed = ref(false)
const showPwd = ref(false)
const showConfirmPwd = ref(false)

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
