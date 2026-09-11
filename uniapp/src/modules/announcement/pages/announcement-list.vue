<template>
  <d-page>
    <scroll-view
      scroll-y
      class="announcement-scroll"
      @scrolltolower="getList"
    >
      <view
        v-for="item in list"
        :key="item.id"
        class="announcement-item"
        @tap="goDetail(item.id)"
      >
        <view class="announcement-item__header">
          <u-tag v-if="item.type === 1" type="primary" size="small">通知</u-tag>
          <u-tag v-else-if="item.type === 2" type="warning" size="small">更新</u-tag>
          <u-tag v-else type="success" size="small">活动</u-tag>
          <text class="announcement-item__time">{{ item.publish_at }}</text>
        </view>
        <text class="announcement-item__title">{{ item.title }}</text>
      </view>

      <d-list-loader
        :loading="loading"
        :finished="finished"
        :total="total"
        empty-text="暂无公告"
      />
    </scroll-view>
  </d-page>
</template>

<script setup lang="ts">
import { announcementApi, type AnnouncementItem } from '@/api/announcement'
import { usePagingList } from '@/hooks/usePagingList'

// usePagingList 自动注册 onShow + onPullDownRefresh 生命周期
const { list, loading, finished, total, getList } = usePagingList<AnnouncementItem>({
  fetchFun: (params) => announcementApi.getList(params),
})

function goDetail(id: number) {
  uni.navigateTo({ url: `/modules/announcement/pages/announcement-detail?id=${id}` })
}

getList()
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.announcement-scroll {
  // 减去顶部容器内边距 + 底部安全区域，避免 iPhone 刘海屏遮挡最后一条
  height: calc(100vh - 100rpx - env(safe-area-inset-bottom));
}

.announcement-item {
  background: #ffffff;
  border-radius: 16rpx;
  padding: 28rpx 32rpx;
  margin-bottom: 20rpx;
  box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.04);

  &__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16rpx;
  }

  &__time {
    font-size: 24rpx;
    color: $text-color-secondary;
  }

  &__title {
    display: block;
    font-size: 30rpx;
    color: $text-color;
    font-weight: 500;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
}
</style>
