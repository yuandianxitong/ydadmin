<template>
  <d-page :safe-area="true">
    <!-- 用户信息卡片 -->
    <view class="user-card" @tap="goEditProfile">
      <view class="avatar-wrap">
        <image
          v-if="avatarUrl"
          class="avatar"
          :src="avatarUrl"
          mode="aspectFill"
        />
        <view v-else class="default-avatar">
          <text class="iconfont icon-person-circle" />
        </view>
      </view>
      <view class="user-info">
        <text class="nickname">{{ userStore.nickname || '未设置昵称' }}</text>
        <text class="mobile">{{ userInfo?.mobile ? maskMobile(userInfo.mobile) : '' }}</text>
      </view>
      <text class="iconfont icon-chevron-right" style="color: #ccc; font-size: 36rpx" />
    </view>

    <!-- 菜单列表 -->
    <view class="menu-card">
      <wd-cell-group>
        <wd-cell
          title="编辑资料"
          is-link
          @click="goEditProfile"
        >
          <template #icon>
            <text class="iconfont icon-person cell-icon" style="color: #2979ff" />
          </template>
        </wd-cell>
        <wd-cell
          title="修改密码"
          is-link
          @click="goChangePassword"
        >
          <template #icon>
            <text class="iconfont icon-shield-shaded cell-icon" style="color: #19be6b" />
          </template>
        </wd-cell>
        <wd-cell
          title="设置"
          is-link
          @click="goSettings"
        >
          <template #icon>
            <text class="iconfont icon-gear cell-icon" style="color: #ff9900" />
          </template>
        </wd-cell>
        <wd-cell
          title="关于"
          is-link
          :value="version"
          @click="goAbout"
        >
          <template #icon>
            <text class="iconfont icon-question-circle cell-icon" style="color: #909399" />
          </template>
        </wd-cell>
      </wd-cell-group>
    </view>

    <!-- 退出登录 -->
    <view class="logout-area">
      <wd-button
        type="error"
        plain
        block
        @click="handleLogout"
        class="logout-btn"
      >
        退出登录
      </wd-button>
    </view>
  </d-page>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { useUserStore } from '@/store/user.store'
import { useUser } from '../composables/useUser'
import type { UserInfo } from '@/types/api'
import { useAppStore } from '@/store/app.store'

const userStore = useUserStore()
const appStore = useAppStore()
const { loadProfile } = useUser()

const userInfo = ref<UserInfo | null>(null)
const version = ref('v1.0.0')

const avatarUrl = computed(() => {
  return appStore.getImageUrl(userInfo.value?.avatar || '')
})

function maskMobile(mobile: string): string {
  if (!mobile || mobile.length < 11) return mobile
  return mobile.replace(/(\d{3})\d{4}(\d{4})/, '$1****$2')
}

onShow(async () => {
  try {
    userInfo.value = await loadProfile()
  } catch {
    // not logged in or error
  }
})

function goEditProfile() {
  uni.navigateTo({ url: '/modules/user/pages/edit-profile' })
}

function goChangePassword() {
  uni.navigateTo({ url: '/modules/user/pages/change-password' })
}

function goSettings() {
  uni.navigateTo({ url: '/modules/user/pages/settings' })
}

function goAbout() {
  uni.showModal({
    title: '关于 Dev007',
    content: '版本: v1.0.0\n一个专业的全栈框架',
    showCancel: false,
  })
}

function handleLogout() {
  uni.showModal({
    title: '提示',
    content: '确认退出登录？',
    success: (res) => {
      if (res.confirm) {
        userStore.logout()
      }
    },
  })
}
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.user-card {
  display: flex;
  align-items: center;
  background: #ffffff;
  border-radius: 24rpx;
  padding: 36rpx 32rpx;
  margin-bottom: 24rpx;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.05);

  .avatar-wrap {
    width: 120rpx;
    height: 120rpx;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    border: 4rpx solid #f0f4ff;

    .avatar {
      width: 100%;
      height: 100%;
    }

    .default-avatar {
      width: 100%;
      height: 100%;
      background: #e8e8e8;
      display: flex;
      align-items: center;
      justify-content: center;

      .iconfont {
        font-size: 64rpx;
        color: #c0c4cc;
      }
    }
  }

  .user-info {
    flex: 1;
    margin-left: 24rpx;

    .nickname {
      display: block;
      font-size: 34rpx;
      font-weight: 600;
      color: $text-color;
      margin-bottom: 10rpx;
    }

    .mobile {
      font-size: 26rpx;
      color: $text-color-secondary;
    }
  }
}

.menu-card {
  background: #ffffff;
  border-radius: 24rpx;
  overflow: hidden;
  margin-bottom: 24rpx;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.05);

  .cell-icon {
    font-size: 40rpx;
    margin-right: 16rpx;
  }
}

.logout-area {
  padding: 20rpx 0;

  .logout-btn {
    border-radius: 16rpx !important;
    height: 96rpx !important;
    font-size: 32rpx !important;
  }
}
</style>
