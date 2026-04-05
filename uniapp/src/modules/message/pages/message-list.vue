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
import { messageApi, type NotificationInfo } from '@/api/message'
import { usePaging } from '@/hooks/usePaging'

const { list, loading, finished, total, getList, refresh } = usePaging<NotificationInfo>({
  fetchFun: (params) => messageApi.getList(params),
})

const typeMap: Record<string, string> = {
  system: '系统通知',
  order: '订单消息',
  payment: '支付通知',
  activity: '活动消息',
}

function getTypeLabel(type: string): string {
  return typeMap[type] || '通知'
}

function formatTime(time: string): string {
  if (!time) return ''
  const date = new Date(time)
  const now = new Date()
  const diff = now.getTime() - date.getTime()
  const minutes = Math.floor(diff / 60000)
  if (minutes < 1) return '刚刚'
  if (minutes < 60) return `${minutes}分钟前`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}小时前`
  const days = Math.floor(hours / 24)
  if (days < 7) return `${days}天前`
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${month}-${day}`
}

function handleTap(item: NotificationInfo) {
  // Mark single message as read
  if (!item.is_read) {
    messageApi.markAsRead([item.id]).then(() => {
      item.is_read = true
    })
  }
  // Navigate to detail
  const data = encodeURIComponent(JSON.stringify(item))
  uni.navigateTo({
    url: `/modules/message/pages/message-detail?data=${data}`,
  })
}

async function handleReadAll() {
  try {
    await messageApi.markAsRead()
    uni.showToast({ title: '全部已读', icon: 'success' })
    list.value.forEach((item) => {
      item.is_read = true
    })
  } catch {
    // error handled by request interceptor
  }
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
  height: calc(100vh - 200rpx);
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
