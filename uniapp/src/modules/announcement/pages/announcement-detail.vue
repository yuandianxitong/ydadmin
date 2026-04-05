<template>
  <d-page>
    <view v-if="detail" class="detail">
      <text class="detail__title">{{ detail.title }}</text>
      <view class="detail__meta">
        <u-tag v-if="detail.type === 1" type="primary" size="small">通知</u-tag>
        <u-tag v-else-if="detail.type === 2" type="warning" size="small">更新</u-tag>
        <u-tag v-else type="success" size="small">活动</u-tag>
        <text class="detail__time">{{ detail.publish_at }}</text>
      </view>
      <view class="detail__divider" />
      <view class="detail__content">
        <rich-text :nodes="detail.content" />
      </view>
    </view>
    <d-empty v-else-if="!loading" text="公告不存在" />
  </d-page>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { announcementApi, type AnnouncementItem } from '@/api/announcement'

const detail = ref<AnnouncementItem | null>(null)
const loading = ref(true)

onLoad(async (options) => {
  const id = parseInt(options?.id || '0', 10)
  if (id > 0) {
    try {
      detail.value = await announcementApi.getDetail(id)
    } catch {
      uni.showToast({ title: '加载失败', icon: 'none' })
    }
  }
  loading.value = false
})
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.detail {
  background: #ffffff;
  border-radius: 16rpx;
  padding: 40rpx 32rpx;
  box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.04);

  &__title {
    display: block;
    font-size: 36rpx;
    font-weight: 600;
    color: $text-color;
    line-height: 1.5;
    margin-bottom: 20rpx;
  }

  &__meta {
    display: flex;
    align-items: center;
    gap: 16rpx;
    margin-bottom: 24rpx;
  }

  &__time {
    font-size: 24rpx;
    color: $text-color-secondary;
  }

  &__divider {
    height: 1rpx;
    background: $border-color;
    margin-bottom: 30rpx;
  }

  &__content {
    font-size: 28rpx;
    color: $text-color;
    line-height: 1.8;
  }
}
</style>
