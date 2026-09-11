<template>
  <view class="home-page" :style="cssVars">
    <view v-if="!ready" class="home-loading">
      <view class="loading-spinner" />
      <text class="loading-text">加载中...</text>
    </view>
    <template v-else>
      <DiyRenderer
        v-if="hasDecoration"
        :components="homeDecoration!.components"
        :page-settings="homeDecoration!.page_settings"
      />
      <view v-else class="home-empty">
        <text class="home-empty__text">暂无首页装修内容，请在后台「页面装修」发布首页</text>
      </view>
    </template>
    <AppTabBar current="pages/index/index" />
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { useMobileConfigStore } from '@/store/mobile-config.store'
import DiyRenderer from '@/components/diy/DiyRenderer.vue'
import AppTabBar from '@/components/tabbar/AppTabBar.vue'
import { provideMemberStats } from '@/hooks/useMemberStats'
import { useTheme } from '@/hooks/useTheme'

const mobileConfigStore = useMobileConfigStore()
const { cssVars, applyNavBar } = useTheme()
const { refresh: refreshMemberStats } = provideMemberStats()

const ready = computed(() => mobileConfigStore.loaded)
const homeDecoration = computed(() => mobileConfigStore.config.home_decoration ?? null)
const hasDecoration = computed(
  () => Array.isArray(homeDecoration.value?.components) && homeDecoration.value!.components.length > 0
)

onShow(() => {
  uni.hideTabBar({ fail: () => {} })
  applyNavBar()
  mobileConfigStore.load().then(() => {
    applyNavBar()
    refreshMemberStats(homeDecoration.value?.components)
  })
})
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.home-page {
  min-height: 100vh;
  background-color: var(--yd-color-page-bg, #f5f5f5);
  padding-bottom: calc(50px + constant(safe-area-inset-bottom));
  padding-bottom: calc(50px + env(safe-area-inset-bottom));
}

.home-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 70vh;
  .loading-spinner {
    width: 36px;
    height: 36px;
    border: 3px solid #e5e5e5;
    border-top-color: var(--yd-color-primary, #2979ff);
    border-radius: 50%;
    animation: home-spin 0.8s linear infinite;
  }
  .loading-text {
    margin-top: 12px;
    font-size: 13px;
    color: #999;
  }
}
@keyframes home-spin {
  to { transform: rotate(360deg); }
}

.home-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  padding: 48rpx;
  &__text {
    font-size: 26rpx;
    color: $text-color-secondary;
    text-align: center;
    line-height: 1.6;
  }
}
</style>
