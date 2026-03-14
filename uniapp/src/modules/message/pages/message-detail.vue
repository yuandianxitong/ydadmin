<template>
  <d-page>
    <view v-if="message" class="detail">
      <view class="detail__header">
        <text class="detail__type">{{ getTypeLabel(message.type) }}</text>
        <text class="detail__time">{{ message.created_at }}</text>
      </view>
      <text class="detail__title">{{ message.title }}</text>
      <view class="detail__divider" />
      <text class="detail__content">{{ message.content }}</text>
    </view>
    <d-empty v-else text="消息不存在" />
  </d-page>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import type { NotificationInfo } from '@/api/message'

const message = ref<NotificationInfo | null>(null)

const typeMap: Record<string, string> = {
  system: '系统通知',
  order: '订单消息',
  payment: '支付通知',
  activity: '活动消息',
}

function getTypeLabel(type: string): string {
  return typeMap[type] || '通知'
}

onLoad((options) => {
  if (options?.data) {
    try {
      message.value = JSON.parse(decodeURIComponent(options.data))
    } catch {
      message.value = null
    }
  }
})
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.detail {
  background: #ffffff;
  border-radius: 16rpx;
  padding: 40rpx 32rpx;
  box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.04);

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24rpx;
  }

  &__type {
    font-size: 24rpx;
    color: $primary-color;
    font-weight: 500;
    background: rgba($primary-color, 0.1);
    padding: 6rpx 20rpx;
    border-radius: 20rpx;
  }

  &__time {
    font-size: 24rpx;
    color: $text-color-secondary;
  }

  &__title {
    display: block;
    font-size: 34rpx;
    font-weight: 600;
    color: $text-color;
    margin-bottom: 24rpx;
    line-height: 1.5;
  }

  &__divider {
    height: 1rpx;
    background: $border-color;
    margin-bottom: 30rpx;
  }

  &__content {
    display: block;
    font-size: 28rpx;
    color: $text-color;
    line-height: 1.8;
    word-break: break-all;
  }
}
</style>
