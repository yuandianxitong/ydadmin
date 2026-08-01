<template>
  <view class="login-page" :style="cssVars">
    <!-- 状态栏占位 -->
    <view :style="{ height: statusBarHeight + 'px' }" />

    <!-- Logo 区域 -->
    <view class="logo-area">
      <image class="logo" :src="logoSrc" mode="aspectFit" />
      <text class="app-name">{{ appName }}</text>
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
        <view class="i-ri-smartphone-line input-prefix" style="font-size: 36rpx; color: #999" />
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
        <view class="i-ri-lock-line input-prefix" style="font-size: 36rpx; color: #999" />
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
        <view class="i-ri-shield-check-line input-prefix" style="font-size: 36rpx; color: #999" />
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
      <u-button
        block
        :loading="loading"
        :disabled="loading"
        class="login-btn"
        @click="handleLogin"
      >
        登录
      </u-button>

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
          <view class="i-ri-wechat-fill wechat-icon-inner" />
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
import { computed, ref } from 'vue'
import { useAppStore } from '@/store/app.store'
import { useLogin } from '../composables/useLogin'
import { useThemePage } from '@/hooks/useTheme'

const {
  loading, loginType, countdown,
  loginByPassword, loginBySms, sendCode,
  wechatQuickLoading, needBindPhone,
  loginByWechatQuick, bindPhoneAndLogin,
} = useLogin()

const { cssVars } = useThemePage()
const appStore = useAppStore()
const appName = computed(() => appStore.config.site_name || '元点Admin')
const logoSrc = computed(() => {
  const logo = appStore.config.site_logo
  return logo ? appStore.getImageUrl(logo) : '/static/logo.png'
})

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

  .wechat-icon-inner {
    width: 48rpx;
    height: 48rpx;
    color: #ffffff;
  }

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
