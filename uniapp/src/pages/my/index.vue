<template>
  <view class="my-page">
    <!-- User Card with gradient background -->
    <view class="user-header">
      <view class="status-bar" :style="{ height: statusBarHeight + 'px' }" />
      <view class="user-card" @tap="goUserAction">
        <view class="avatar-wrap">
          <image
            v-if="userStore.isLoggedIn && userStore.avatar"
            class="avatar"
            :src="avatarUrl"
            mode="aspectFill"
          />
          <view v-else class="default-avatar">
            <text class="iconfont icon-person-circle" />
          </view>
        </view>
        <view v-if="userStore.isLoggedIn" class="user-info">
          <text class="user-name">{{ userStore.nickname || '未设置昵称' }}</text>
          <text class="user-phone">{{ maskMobile(userStore.userInfo?.mobile) }}</text>
        </view>
        <view v-else class="user-info">
          <text class="user-name">点击登录</text>
          <text class="user-phone">登录后享受更多功能</text>
        </view>
        <view class="i-ri-arrow-right-s-line" style="font-size: 36rpx; color: rgba(255,255,255,0.8)" />
      </view>

      <!-- Balance & Points always visible in header area -->
      <view class="header-assets">
        <view class="header-assets-item" @tap="goAuthPage('/modules/user/pages/balance')">
          <text class="header-assets-value">{{ userStore.isLoggedIn ? balanceInfo.balance : '**' }}</text>
          <text class="header-assets-label">余额</text>
        </view>
        <view class="header-assets-divider" />
        <view class="header-assets-item" @tap="goAuthPage('/modules/user/pages/points')">
          <text class="header-assets-value">{{ userStore.isLoggedIn ? balanceInfo.points : '**' }}</text>
          <text class="header-assets-label">积分</text>
        </view>
      </view>
    </view>

    <!-- Menu Groups -->
    <view class="menu-body">
      <!-- Group 1: Profile -->
      <view class="menu-card">
        <view class="menu-cell" @tap="goAuthPage('/modules/user/pages/edit-profile')">
          <view class="i-ri-user-3-line menu-cell-icon" style="color: #2979ff" />
          <text class="menu-cell-title">个人资料</text>
          <view class="i-ri-arrow-right-s-line menu-cell-arrow" />
        </view>
        <view class="menu-cell-divider" />
        <view class="menu-cell" @tap="goAuthPage('/modules/user/pages/change-password')">
          <view class="i-ri-lock-password-line menu-cell-icon" style="color: #19be6b" />
          <text class="menu-cell-title">修改密码</text>
          <view class="i-ri-arrow-right-s-line menu-cell-arrow" />
        </view>
      </view>

      <!-- Group 2: Interaction -->
      <view class="menu-card">
        <view class="menu-cell" @tap="goMessageTab">
          <view class="i-ri-notification-3-line menu-cell-icon" style="color: #ff9900" />
          <text class="menu-cell-title">消息通知</text>
          <u-badge v-if="unreadCount > 0" :count="unreadCount" class="menu-cell-badge" />
          <view class="i-ri-arrow-right-s-line menu-cell-arrow" />
        </view>
        <view class="menu-cell-divider" />
        <view class="menu-cell" @tap="goAuthPage('/modules/feedback/pages/feedback')">
          <view class="i-ri-chat-3-line menu-cell-icon" style="color: #7c4dff" />
          <text class="menu-cell-title">意见反馈</text>
          <view class="i-ri-arrow-right-s-line menu-cell-arrow" />
        </view>
      </view>

      <!-- Group 3: Info -->
      <view class="menu-card">
        <view class="menu-cell" @tap="goPage('/modules/about/pages/about')">
          <view class="i-ri-information-line menu-cell-icon" style="color: #909399" />
          <text class="menu-cell-title">关于我们</text>
          <view class="i-ri-arrow-right-s-line menu-cell-arrow" />
        </view>
        <view class="menu-cell-divider" />
        <view class="menu-cell" @tap="goPage('/modules/user/pages/settings')">
          <view class="i-ri-settings-3-line menu-cell-icon" style="color: #fa3534" />
          <text class="menu-cell-title">设置</text>
          <view class="i-ri-arrow-right-s-line menu-cell-arrow" />
        </view>
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
import { getStatusBarHeight } from '@/utils/platform'

const userStore = useUserStore()
const appStore = useAppStore()

const statusBarHeight = ref(getStatusBarHeight())
const unreadCount = ref(0)
const balanceInfo = ref({ balance: '0.00', points: 0 })

const avatarUrl = computed(() => {
  if (userStore.isLoggedIn && userStore.avatar) {
    return appStore.getImageUrl(userStore.avatar)
  }
  return ''
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
  background: linear-gradient(135deg, #2979ff, #1e5fcc);
  padding-bottom: 32rpx;
}

.user-card {
  display: flex;
  align-items: center;
  padding: 48rpx 32rpx;

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

    .default-avatar {
      width: 100%;
      height: 100%;
      background: rgba(255, 255, 255, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;

      .iconfont {
        font-size: 64rpx;
        color: rgba(255, 255, 255, 0.8);
      }
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

.header-assets {
  display: flex;
  align-items: center;
  padding: 24rpx 32rpx 0;

  .header-assets-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .header-assets-value {
    font-size: 40rpx;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 4rpx;
  }

  .header-assets-label {
    font-size: 24rpx;
    color: rgba(255, 255, 255, 0.7);
  }

  .header-assets-divider {
    width: 1rpx;
    height: 60rpx;
    background: rgba(255, 255, 255, 0.2);
    flex-shrink: 0;
  }
}

.menu-body {
  padding: 24rpx $page-padding $page-padding;
}

.menu-card {
  background: #ffffff;
  border-radius: 24rpx;
  overflow: hidden;
  margin-bottom: 24rpx;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.05);
  padding: 12rpx 0;
}

.menu-cell {
  display: flex;
  align-items: center;
  padding: 28rpx 32rpx;
  position: relative;
  transition: background 0.15s;

  &:active {
    background: #f5f7fa;
  }

  .menu-cell-icon {
    width: 40rpx;
    height: 40rpx;
    font-size: 40rpx;
    margin-right: 24rpx;
    flex-shrink: 0;
  }

  .menu-cell-title {
    flex: 1;
    font-size: 30rpx;
    color: $text-color;
  }

  .menu-cell-badge {
    margin-right: 16rpx;
  }

  .menu-cell-arrow {
    width: 28rpx;
    height: 28rpx;
    font-size: 28rpx;
    color: #c0c4cc;
    flex-shrink: 0;
  }
}

.menu-cell-divider {
  height: 1rpx;
  background: #f0f2f5;
  margin: 0 32rpx 0 96rpx;
}
</style>
