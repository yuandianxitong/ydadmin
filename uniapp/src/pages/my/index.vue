<template>
  <view class="my-page">
    <!-- User Card with gradient background -->
    <view class="user-header">
      <view class="status-bar" :style="{ height: statusBarHeight + 'px' }" />
      <view class="user-card" @tap="goUserAction">
        <view class="avatar-wrap">
          <image
            class="avatar"
            :src="avatarUrl"
            mode="aspectFill"
          />
        </view>
        <view v-if="userStore.isLoggedIn" class="user-info">
          <text class="user-name">{{ userStore.nickname || '未设置昵称' }}</text>
          <text class="user-phone">{{ maskMobile(userStore.userInfo?.mobile) }}</text>
        </view>
        <view v-else class="user-info">
          <text class="user-name">点击登录</text>
          <text class="user-phone">登录后享受更多功能</text>
        </view>
        <wd-icon name="arrow-right" color="rgba(255,255,255,0.8)" size="36rpx" />
      </view>
    </view>

    <!-- Menu Groups -->
    <view class="menu-body" :style="{ paddingTop: (statusBarHeight + 180) + 'px' }">
      <!-- Balance & Points Card -->
      <view v-if="userStore.isLoggedIn" class="assets-card" @tap.stop>
        <view class="assets-item" @tap="goAuthPage('/modules/user/pages/balance')">
          <text class="assets-label">余额</text>
          <text class="assets-value">{{ balanceInfo.balance }}</text>
          <text class="assets-action">去充值</text>
        </view>
        <view class="assets-divider" />
        <view class="assets-item" @tap="goAuthPage('/modules/user/pages/points')">
          <text class="assets-label">积分</text>
          <text class="assets-value">{{ balanceInfo.points }}</text>
          <text class="assets-action">积分明细</text>
        </view>
      </view>
      <!-- Group 1: Profile -->
      <view class="menu-card">
        <wd-cell-group>
          <wd-cell title="个人资料" is-link @click="goAuthPage('/modules/user/pages/edit-profile')">
            <template #icon>
              <wd-icon name="user" size="40rpx" color="#2979ff" class="cell-icon" />
            </template>
          </wd-cell>
          <wd-cell title="修改密码" is-link @click="goAuthPage('/modules/user/pages/change-password')">
            <template #icon>
              <wd-icon name="lock-on" size="40rpx" color="#19be6b" class="cell-icon" />
            </template>
          </wd-cell>
        </wd-cell-group>
      </view>

      <!-- Group 2: Interaction -->
      <view class="menu-card">
        <wd-cell-group>
          <wd-cell title="消息通知" is-link @click="goMessageTab">
            <template #icon>
              <wd-icon name="bell" size="40rpx" color="#ff9900" class="cell-icon" />
            </template>
            <template v-if="unreadCount > 0" #value>
              <wd-badge :value="unreadCount" />
            </template>
          </wd-cell>
          <wd-cell title="意见反馈" is-link @click="goAuthPage('/modules/feedback/pages/feedback')">
            <template #icon>
              <wd-icon name="edit" size="40rpx" color="#7c4dff" class="cell-icon" />
            </template>
          </wd-cell>
        </wd-cell-group>
      </view>

      <!-- Group 3: Info -->
      <view class="menu-card">
        <wd-cell-group>
          <wd-cell title="关于我们" is-link @click="goPage('/modules/about/pages/about')">
            <template #icon>
              <wd-icon name="info-circle" size="40rpx" color="#909399" class="cell-icon" />
            </template>
          </wd-cell>
          <wd-cell title="设置" is-link @click="goPage('/modules/user/pages/settings')">
            <template #icon>
              <wd-icon name="setting" size="40rpx" color="#fa3534" class="cell-icon" />
            </template>
          </wd-cell>
        </wd-cell-group>
      </view>

      <!-- Version -->
      <view class="version-info">
        <text class="version-text">v1.0.0</text>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { useUserStore } from '@/store/user.store'
import { useAppStore } from '@/store/app.store'
import { messageApi } from '@/api/message'
import { userApi } from '@/api/user'

const userStore = useUserStore()
const appStore = useAppStore()

const statusBarHeight = ref(0)
const unreadCount = ref(0)
const balanceInfo = ref({ balance: '0.00', points: 0 })

try {
  const sysInfo = uni.getSystemInfoSync()
  statusBarHeight.value = sysInfo.statusBarHeight || 0
} catch {
  statusBarHeight.value = 44
}

const avatarUrl = computed(() => {
  if (userStore.isLoggedIn && userStore.avatar) {
    return appStore.getImageUrl(userStore.avatar)
  }
  return '/static/logo.png'
})

function maskMobile(mobile?: string): string {
  if (!mobile || mobile.length < 11) return mobile || ''
  return mobile.replace(/(\d{3})\d{4}(\d{4})/, '$1****$2')
}

function goUserAction() {
  if (userStore.isLoggedIn) {
    uni.navigateTo({ url: '/modules/user/pages/edit-profile' })
  } else {
    uni.navigateTo({ url: '/modules/login/pages/login' })
  }
}

function goAuthPage(url: string) {
  if (!userStore.isLoggedIn) {
    uni.navigateTo({ url: '/modules/login/pages/login' })
    return
  }
  uni.navigateTo({ url })
}

function goPage(url: string) {
  uni.navigateTo({ url })
}

function goMessageTab() {
  uni.switchTab({ url: '/pages/message/index' })
}

function loadUnreadCount() {
  if (!userStore.isLoggedIn) {
    unreadCount.value = 0
    return
  }
  messageApi
    .getUnreadCount()
    .then((res) => {
      unreadCount.value = res.count || 0
    })
    .catch(() => {
      unreadCount.value = 0
    })
}

function loadAssets() {
  if (!userStore.isLoggedIn) {
    balanceInfo.value = { balance: '0.00', points: 0 }
    return
  }
  Promise.all([
    userApi.getBalance(),
    userApi.getPoints(),
  ]).then(([balRes, ptsRes]) => {
    balanceInfo.value.balance = balRes.balance || '0.00'
    balanceInfo.value.points = ptsRes.points || 0
  }).catch(() => {
    // ignore
  })
}

onShow(() => {
  if (userStore.isLoggedIn && !userStore.userInfo) {
    userStore.getUserInfo().catch(() => {})
  }
  loadUnreadCount()
  loadAssets()
})
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.my-page {
  min-height: 100vh;
  background-color: $bg-color;
}

.user-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  background: linear-gradient(135deg, #2979ff, #1e5fcc);
  padding-bottom: 40rpx;
}

.user-card {
  display: flex;
  align-items: center;
  padding: 32rpx 32rpx 0;

  .avatar-wrap {
    width: 120rpx;
    height: 120rpx;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    border: 4rpx solid rgba(255, 255, 255, 0.3);

    .avatar {
      width: 100%;
      height: 100%;
    }
  }

  .user-info {
    flex: 1;
    margin-left: 24rpx;

    .user-name {
      display: block;
      font-size: 36rpx;
      font-weight: 700;
      color: #ffffff;
      margin-bottom: 8rpx;
    }

    .user-phone {
      display: block;
      font-size: 26rpx;
      color: rgba(255, 255, 255, 0.8);
    }
  }
}

.assets-card {
  display: flex;
  align-items: center;
  background: #ffffff;
  border-radius: 24rpx;
  padding: 32rpx 0;
  margin-bottom: 24rpx;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.05);

  .assets-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .assets-label {
    font-size: 24rpx;
    color: $text-color-secondary;
    margin-bottom: 8rpx;
  }

  .assets-value {
    font-size: 40rpx;
    font-weight: 700;
    color: $text-color;
    margin-bottom: 12rpx;
  }

  .assets-action {
    font-size: 24rpx;
    color: $primary-color;
    background: rgba(41, 121, 255, 0.08);
    padding: 6rpx 24rpx;
    border-radius: 20rpx;
  }

  .assets-divider {
    width: 1rpx;
    height: 80rpx;
    background: $border-color;
    flex-shrink: 0;
  }
}

.menu-body {
  padding: 0 $page-padding $page-padding;
}

.menu-card {
  background: #ffffff;
  border-radius: 24rpx;
  overflow: hidden;
  margin-bottom: 24rpx;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.05);

  .cell-icon {
    margin-right: 16rpx;
  }
}

.version-info {
  text-align: center;
  padding: 40rpx 0;

  .version-text {
    font-size: 24rpx;
    color: $text-color-secondary;
  }
}
</style>
