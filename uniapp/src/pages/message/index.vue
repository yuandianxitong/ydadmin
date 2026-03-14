<template>
  <d-page>
    <!-- Header -->
    <view class="msg-header">
      <text class="msg-header__title">消息</text>
      <text
        class="msg-header__action"
        :class="{ 'msg-header__action--disabled': total === 0 }"
        @tap="handleReadAll"
      >
        全部已读
      </text>
    </view>

    <!-- Message List -->
    <scroll-view
      scroll-y
      class="message-scroll"
      refresher-enabled
      :refresher-triggered="refreshing"
      @refresherrefresh="handleRefresh"
      @scrolltolower="getList"
    >
      <view
        v-for="item in list"
        :key="item.id"
        class="message-item"
        :class="{ 'message-item--unread': !item.is_read }"
        @tap="handleTap(item)"
      >
        <view class="message-item__icon-wrap">
          <view class="message-item__icon" :class="getTypeClass(item.type)">
            <text class="iconfont icon-bell" />
          </view>
          <view v-if="!item.is_read" class="message-item__dot" />
        </view>
        <view class="message-item__body">
          <view class="message-item__top">
            <text class="message-item__title">{{ item.title }}</text>
            <text class="message-item__time">{{ formatTime(item.created_at) }}</text>
          </view>
          <text class="message-item__content">{{ item.content }}</text>
        </view>
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
import { onShow } from '@dcloudio/uni-app'
import { messageApi, type NotificationInfo } from '@/api/message'
import { usePaging } from '@/hooks/usePaging'

const { list, loading, finished, refreshing, total, getList, refresh } = usePaging<NotificationInfo>({
  fetchFun: (params) => messageApi.getList(params),
})

function getTypeClass(type: string): string {
  const map: Record<string, string> = {
    system: 'type-system',
    order: 'type-order',
    payment: 'type-payment',
    activity: 'type-activity',
  }
  return map[type] || 'type-system'
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
  if (!item.is_read) {
    messageApi.markAsRead([item.id]).then(() => {
      item.is_read = true
    })
  }
  const data = encodeURIComponent(JSON.stringify(item))
  uni.navigateTo({
    url: `/modules/message/pages/message-detail?data=${data}`,
  })
}

async function handleReadAll() {
  if (total.value === 0) return
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

function handleRefresh() {
  refresh()
}

onShow(() => {
  refresh()
})
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.msg-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24rpx;

  &__title {
    font-size: 36rpx;
    font-weight: 700;
    color: $text-color;
  }

  &__action {
    font-size: 26rpx;
    color: $primary-color;

    &--disabled {
      color: $text-color-secondary;
    }
  }
}

.message-scroll {
  height: calc(100vh - 200rpx - env(safe-area-inset-bottom) - 100rpx);
}

.message-item {
  display: flex;
  align-items: flex-start;
  background: #ffffff;
  border-radius: 16rpx;
  padding: 28rpx 24rpx;
  margin-bottom: 16rpx;
  box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.04);

  &--unread {
    background: #f0f7ff;
  }

  &__icon-wrap {
    position: relative;
    margin-right: 20rpx;
    flex-shrink: 0;
  }

  &__icon {
    width: 72rpx;
    height: 72rpx;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;

    .iconfont {
      font-size: 32rpx;
      color: #ffffff;
    }

    &.type-system { background: linear-gradient(135deg, #2979ff, #5b9bff); }
    &.type-order { background: linear-gradient(135deg, #ff9900, #ffb74d); }
    &.type-payment { background: linear-gradient(135deg, #19be6b, #4dd893); }
    &.type-activity { background: linear-gradient(135deg, #7c4dff, #a98bff); }
  }

  &__dot {
    position: absolute;
    top: 0;
    right: 0;
    width: 16rpx;
    height: 16rpx;
    border-radius: 50%;
    background-color: $danger-color;
    border: 2rpx solid #ffffff;
  }

  &__body {
    flex: 1;
    min-width: 0;
  }

  &__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10rpx;
  }

  &__title {
    font-size: 28rpx;
    font-weight: 500;
    color: $text-color;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-right: 16rpx;
  }

  &__time {
    font-size: 22rpx;
    color: $text-color-secondary;
    flex-shrink: 0;
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
