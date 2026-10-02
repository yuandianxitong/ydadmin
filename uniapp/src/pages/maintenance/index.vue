<template>
  <view class="maintenance">
    <text class="title">网站维护中</text>
    <text class="tip">{{ tip }}</text>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useAppStore } from '@/store/app.store'

const appStore = useAppStore()
const tip = computed(() => {
  const text = String(appStore.config.site_close_tip || '').trim()
  return text || '网站维护中，请稍后再试...'
})
</script>

<style scoped>
.maintenance {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48rpx;
  box-sizing: border-box;
}
.title {
  font-size: 40rpx;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 24rpx;
}
.tip {
  font-size: 28rpx;
  color: #6b7280;
  text-align: center;
  line-height: 1.6;
}
</style>
