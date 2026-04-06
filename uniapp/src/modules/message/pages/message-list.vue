<template>
  <d-page>
    <!-- 操作栏 -->
    <view class="action-bar">
      <text class="action-bar__title">共 {{ total }} 条消息</text>
      <u-button
        type="text"
        size="small"
        :disabled="total === 0"
        @click="handleReadAll"
      >
        全部已读
      </u-button>
    </view>

    <!-- 消息列表 -->
    <scroll-view
      scroll-y
      class="message-scroll"
      @scrolltolower="getList"
    >
      <view
        v-for="item in list"
        :key="item.id"
        class="message-item"
        :class="{ 'message-item--unread': !item.is_read }"
        @tap="handleTap(item)"
      >
        <view class="message-item__header">
          <view class="message-item__type">
            <view v-if="!item.is_read" class="message-item__dot" />
            <text class="message-item__type-text">{{ getTypeLabel(item.type) }}</text>
          </view>
          <text class="message-item__time">{{ formatTime(item.created_at) }}</text>
        </view>
        <text class="message-item__title">{{ item.title }}</text>
        <text class="message-item__content">{{ item.content }}</text>
      </view>

      <d-list-loader
        :loading="loading"
        :finished="finished"
        :total="total"
        empty-text="暂无消息"
      />
    </scroll-view>
  </d-page>
</template>

<script setup lang="ts">
import { onShow, onPullDownRefresh } from '@dcloudio/uni-app'
import { useMessageList } from '@/hooks/useMessageList'

const {
  list,
  loading,
  finished,
  total,
  getList,
  refresh,
  formatTime,
  handleTap,
  handleReadAll,
} = useMessageList()

const typeMap: Record<string, string> = {
  system: '系统通知',
  order: '订单消息',
  payment: '支付通知',
  activity: '活动消息',
}

function getTypeLabel(type: string): string {
  return typeMap[type] || '通知'
}

onShow(() => {
  refresh()
})

onPullDownRefresh(async () => {
  await refresh()
  uni.stopPullDownRefresh()
})
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.action-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20rpx;

  &__title {
    font-size: 26rpx;
    color: $text-color-secondary;
  }
}

.message-scroll {
  // 顶部操作栏高度 + 底部安全区域
  height: calc(100vh - 200rpx - env(safe-area-inset-bottom));
}

.message-item {
  background: #ffffff;
  border-radius: 16rpx;
  padding: 28rpx 32rpx;
  margin-bottom: 20rpx;
  box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.04);

  &--unread {
    background: #f0f7ff;
  }

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16rpx;
  }

  &__type {
    display: flex;
    align-items: center;
  }

  &__dot {
    width: 14rpx;
    height: 14rpx;
    border-radius: 50%;
    background-color: $danger-color;
    margin-right: 10rpx;
    flex-shrink: 0;
  }

  &__type-text {
    font-size: 24rpx;
    color: $primary-color;
    font-weight: 500;
  }

  &__time {
    font-size: 24rpx;
    color: $text-color-secondary;
  }

  &__title {
    display: block;
    font-size: 30rpx;
    font-weight: 500;
    color: $text-color;
    margin-bottom: 10rpx;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__content {
    display: block;
    font-size: 26rpx;
    color: $text-color-secondary;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}
</style>
