<template>
  <view class="diy-uic" :style="rootStyle">
    <view class="diy-uic__head" @tap="goUserAction">
      <view class="diy-uic__avatar-wrap">
        <image
          v-if="userStore.isLoggedIn && userStore.avatar"
          class="diy-uic__avatar"
          :src="avatarUrl"
          mode="aspectFill"
        />
        <view v-else class="diy-uic__avatar diy-uic__avatar--default">
          <text class="i-ri-user-fill" />
        </view>
      </view>
      <view class="diy-uic__meta">
        <text class="diy-uic__name">{{ userStore.isLoggedIn ? (userStore.nickname || '未设置昵称') : '点击登录' }}</text>
        <text class="diy-uic__sub">{{ userStore.isLoggedIn ? maskMobile(userStore.userInfo?.mobile) : '登录后享受更多功能' }}</text>
      </view>
      <view class="i-ri-arrow-right-s-line diy-uic__arrow" />
    </view>

    <view v-if="props.props?.show_assets" class="diy-uic__assets">
      <template v-for="(a, i) in assets" :key="i">
        <view v-if="i > 0" class="diy-uic__divider" />
        <view class="diy-uic__asset" @tap="goAuthPage(a.link)">
          <text class="diy-uic__num">{{ assetValue(a) }}</text>
          <text class="diy-uic__label">{{ a.label || '资产' }}</text>
        </view>
      </template>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useUserStore } from '@/store/user.store'
import { useAppStore } from '@/store/app.store'
import { useMemberStats } from '@/hooks/useMemberStats'
import { getStatusBarHeight } from '@/utils/platform'

const props = defineProps<{ props: Record<string, any>; isFirst?: boolean }>()

const userStore = useUserStore()
const appStore = useAppStore()
const stats = useMemberStats()

/** 与编辑器 useEditor.defaultAssets() 同构的存量兜底（键名 label/stat_key/link 必须一致） */
const DEFAULT_ASSETS = [
  { label: '余额', stat_key: 'user.balance', link: '/modules/user/pages/balance' },
  { label: '积分', stat_key: 'user.points', link: '/modules/user/pages/points' },
]

const assets = computed(() => {
  const list = props.props?.assets
  return Array.isArray(list) && list.length ? list : DEFAULT_ASSETS
})

/** 首位时渐变内预留状态栏高度（沉浸式，与静态兜底头部一致） */
const rootStyle = computed(() => (props.isFirst ? { paddingTop: getStatusBarHeight() + 'px' } : {}))

function assetValue(a: { stat_key?: string }): string {
  if (!userStore.isLoggedIn) return '**'
  if (!a.stat_key) return '-'
  const v = stats.value[a.stat_key]
  return v === undefined ? '0' : String(v)
}

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
  if (!url) return
  if (!userStore.isLoggedIn) {
    uni.navigateTo({ url: '/modules/login/pages/login' })
    return
  }
  uni.navigateTo({ url })
}
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.diy-uic {
  background: linear-gradient(135deg, var(--yd-color-primary, #2979ff), var(--yd-color-primary-dark, #1e5bb8));
  padding-bottom: 32rpx;
  // 底部直角：与租户端装修编辑器预览对齐（组件间距/圆角交由装修 componentStyle 控制）
}

.diy-uic__head {
  display: flex;
  align-items: center;
  padding: 48rpx 32rpx;
}

.diy-uic__avatar-wrap {
  width: 120rpx;
  height: 120rpx;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  border: 4rpx solid rgba(255, 255, 255, 0.3);
}

.diy-uic__avatar {
  width: 100%;
  height: 100%;
}

.diy-uic__avatar--default {
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.2);

  text {
    font-size: 64rpx;
    color: rgba(255, 255, 255, 0.8);
  }
}

.diy-uic__meta {
  flex: 1;
  margin-left: 24rpx;
}

.diy-uic__name {
  display: block;
  font-size: 36rpx;
  font-weight: 700;
  color: #ffffff;
  margin-bottom: 8rpx;
}

.diy-uic__sub {
  display: block;
  font-size: 26rpx;
  color: rgba(255, 255, 255, 0.8);
}

.diy-uic__arrow {
  font-size: 36rpx;
  color: rgba(255, 255, 255, 0.8);
}

.diy-uic__assets {
  display: flex;
  align-items: center;
  padding: 24rpx 32rpx 0;
}

.diy-uic__asset {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.diy-uic__num {
  font-size: 40rpx;
  font-weight: 700;
  color: #ffffff;
  margin-bottom: 4rpx;
}

.diy-uic__label {
  font-size: 24rpx;
  color: rgba(255, 255, 255, 0.7);
}

.diy-uic__divider {
  width: 1rpx;
  height: 60rpx;
  background: rgba(255, 255, 255, 0.2);
  flex-shrink: 0;
}
</style>
