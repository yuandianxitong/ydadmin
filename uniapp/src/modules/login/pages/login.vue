<template>
  <view class="login-page">
    <!-- 状态栏占位 -->
    <view :style="{ height: statusBarHeight + 'px' }" />

    <!-- Logo 区域 -->
    <view class="logo-area">
      <image class="logo" src="/static/logo.png" mode="aspectFit" />
      <text class="app-name">元点Admin</text>
      <text class="app-slogan">专业开发者的首选框架</text>
    </view>

    <!-- 登录表单卡片 -->
    <view class="form-card">
      <!-- Tab 切换 -->
      <view class="tab-bar">
        <view
          class="tab-item"
          :class="{ active: loginType === 'password' }"
          @tap="loginType = 'password'"
        >
          密码登录
        </view>
        <view
          class="tab-item"
          :class="{ active: loginType === 'sms' }"
          @tap="loginType = 'sms'"
        >
          验证码登录
        </view>
      </view>

      <!-- 手机号输入 -->
      <view class="input-group">
        <wd-icon name="mobile" size="36rpx" color="#999" class="input-prefix" />
        <input
          v-model="mobile"
          type="number"
          maxlength="11"
          placeholder="请输入手机号"
          class="uni-input"
          placeholder-class="input-placeholder"
        />
      </view>

      <!-- 密码输入 -->
      <view v-if="loginType === 'password'" class="input-group">
        <wd-icon name="lock-on" size="36rpx" color="#999" class="input-prefix" />
        <input
          v-model="password"
          :password="!showPwd"
          placeholder="请输入密码（6-20位）"
          class="uni-input"
          placeholder-class="input-placeholder"
        />
        <view class="pwd-toggle" @tap="showPwd = !showPwd">
          <text class="pwd-toggle-text">{{ showPwd ? '隐藏' : '显示' }}</text>
        </view>
      </view>

      <!-- 验证码输入 -->
      <view v-else class="input-group sms-group">
        <wd-icon name="secured" size="36rpx" color="#999" class="input-prefix" />
        <input
          v-model="smsCode"
          type="number"
          maxlength="6"
          placeholder="请输入验证码"
          class="uni-input"
          placeholder-class="input-placeholder"
        />
        <view
          class="send-code-btn"
          :class="{ disabled: countdown > 0 }"
          @tap="handleSendCode"
        >
          {{ countdown > 0 ? `${countdown}s` : '获取验证码' }}
        </view>
      </view>

      <!-- 协议 -->
      <d-agreement-check v-model="agreed" />

      <!-- 登录按钮 -->
      <wd-button
        block
        :loading="loading"
        :disabled="loading"
        class="login-btn"
        @click="handleLogin"
      >
        登录
      </wd-button>

      <!-- #ifdef MP-WEIXIN -->
      <view class="wechat-quick-section">
        <view class="divider-line">
          <view class="line" /><text class="divider-text">其他登录方式</text><view class="line" />
        </view>

        <view
          v-if="!needBindPhone"
          class="wechat-login-icon"
          :class="{ loading: wechatQuickLoading }"
          @tap="handleWechatQuickLogin"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 28 28" width="48" height="48">
            <path fill="#ffffff" d="M17.6 6.32C16.27 5.45 14.6 5 12.8 5 8.15 5 4.39 8.2 4.39 12.14c0 2.18 1.13 4.16 2.95 5.56l-.74 2.2 2.56-1.3c.89.25 1.84.38 2.8.38.23 0 .47-.01.7-.03a6.06 6.06 0 0 1-.26-1.78c0-3.5 3.16-6.35 7.05-6.35.24 0 .47.01.7.03C19.68 8.47 18.93 7.16 17.6 6.32zM10.23 8.72c.5 0 .9.38.9.85s-.4.85-.9.85-.9-.38-.9-.85.4-.85.9-.85zm-4.85.85c0-.47.4-.85.9-.85s.9.38.9.85-.4.85-.9.85-.9-.38-.9-.85zM23.5 17.07c0-3.07-3.12-5.57-6.97-5.57s-6.97 2.5-6.97 5.57 3.12 5.57 6.97 5.57c.82 0 1.6-.13 2.33-.36l1.97 1.07-.57-1.71C22.37 20.56 23.5 18.93 23.5 17.07zM14.5 15.8c-.37 0-.68-.29-.68-.65s.3-.65.68-.65.68.29.68.65-.3.65-.68.65zm4.06 0c-.37 0-.68-.29-.68-.65s.3-.65.68-.65.68.29.68.65-.3.65-.68.65z"/>
          </svg>
        </view>

        <button
          v-else
          open-type="getPhoneNumber"
          class="phone-bind-btn"
          @getphonenumber="handleGetPhoneNumber"
        >
          授权手机号完成注册
        </button>
      </view>
      <!-- #endif -->

      <!-- 底部链接 -->
      <view class="bottom-links">
        <text class="link" @tap="goRegister">立即注册</text>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useLogin } from '../composables/useLogin'

const {
  loading, loginType, countdown,
  loginByPassword, loginBySms, sendCode,
  wechatQuickLoading, needBindPhone,
  loginByWechatQuick, bindPhoneAndLogin,
} = useLogin()

const mobile = ref('')
const password = ref('')
const smsCode = ref('')
const agreed = ref(false)
const showPwd = ref(false)

// 获取状态栏高度
const statusBarHeight = ref(0)
const systemInfo = uni.getSystemInfoSync()
statusBarHeight.value = systemInfo.statusBarHeight || 0

function checkAgreement(): boolean {
  if (!agreed.value) {
    uni.showToast({ title: '请先同意用户协议', icon: 'none' })
    return false
  }
  return true
}

async function handleLogin() {
  if (!checkAgreement()) return
  if (loginType.value === 'password') {
    await loginByPassword(mobile.value, password.value)
  } else {
    await loginBySms(mobile.value, smsCode.value)
  }
}

async function handleSendCode() {
  await sendCode(mobile.value)
}

function goRegister() {
  uni.navigateTo({ url: '/modules/login/pages/register' })
}

async function handleWechatQuickLogin() {
  if (!checkAgreement()) return
  await loginByWechatQuick()
}

function handleGetPhoneNumber(e: any) {
  if (e.detail.code) {
    bindPhoneAndLogin(e.detail.code)
  } else {
    uni.showToast({ title: '已取消授权', icon: 'none' })
  }
}
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.login-page {
  min-height: 100vh;
  background: linear-gradient(160deg, #e8f0ff 0%, #f5f7ff 40%, #ffffff 100%);
  display: flex;
  flex-direction: column;
  padding: 0 60rpx 60rpx;
  box-sizing: border-box;
}

.logo-area {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 80rpx 0 60rpx;

  .logo {
    width: 160rpx;
    height: 160rpx;
    border-radius: 36rpx;
    box-shadow: 0 8rpx 24rpx rgba(41, 121, 255, 0.2);
  }

  .app-name {
    font-size: 52rpx;
    font-weight: 700;
    color: $primary-color;
    margin-top: 24rpx;
    letter-spacing: 4rpx;
  }

  .app-slogan {
    font-size: 26rpx;
    color: $text-color-secondary;
    margin-top: 12rpx;
  }
}

.form-card {
  background: #ffffff;
  border-radius: 32rpx;
  padding: 48rpx 40rpx;
  box-shadow: 0 8rpx 40rpx rgba(0, 0, 0, 0.06);
}

.tab-bar {
  display: flex;
  margin-bottom: 40rpx;
  background: #f5f5f5;
  border-radius: 16rpx;
  padding: 6rpx;
  gap: 6rpx;

  .tab-item {
    flex: 1;
    text-align: center;
    padding: 18rpx 0;
    font-size: 28rpx;
    color: $text-color-secondary;
    border-radius: 12rpx;
    transition: all 0.2s;

    &.active {
      background: #ffffff;
      color: $primary-color;
      font-weight: 600;
      box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.08);
    }
  }
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

.login-btn {
  margin-top: 16rpx;
  border-radius: 16rpx !important;
  height: 96rpx !important;
  font-size: 32rpx !important;
}

.bottom-links {
  display: flex;
  justify-content: center;
  margin-top: 32rpx;

  .link {
    font-size: 28rpx;
    color: $primary-color;
    padding: 10rpx 20rpx;
  }
}

.wechat-quick-section {
  margin-top: 40rpx;
}

.divider-line {
  display: flex;
  align-items: center;
  margin-bottom: 32rpx;
  .line {
    flex: 1;
    height: 1rpx;
    background: $border-color;
  }
  .divider-text {
    font-size: 24rpx;
    color: $text-color-secondary;
    padding: 0 24rpx;
  }
}

.wechat-login-icon {
  width: 96rpx;
  height: 96rpx;
  border-radius: 50%;
  background: #07c160;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto;
  box-shadow: 0 4rpx 16rpx rgba(7, 193, 96, 0.3);

  &.loading {
    opacity: 0.6;
  }
}

.phone-bind-btn {
  width: 100%;
  height: 88rpx;
  line-height: 88rpx;
  background: #07c160;
  color: #ffffff;
  border-radius: 16rpx;
  font-size: 30rpx;
  border: none;
}
</style>
