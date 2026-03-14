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
          <wd-tag v-if="item.type === 1" type="primary" size="small">通知</wd-tag>
          <wd-tag v-else-if="item.type === 2" type="warning" size="small">更新</wd-tag>
          <wd-tag v-else type="success" size="small">活动</wd-tag>
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
import { onPullDownRefresh } from '@dcloudio/uni-app'
import { announcementApi, type AnnouncementItem } from '@/api/announcement'
import { usePaging } from '@/hooks/usePaging'

const { list, loading, finished, total, getList, refresh } = usePaging<AnnouncementItem>({
  fetchFun: (params) => announcementApi.getList(params),
})

function goDetail(id: number) {
  uni.navigateTo({ url: `/modules/announcement/pages/announcement-detail?id=${id}` })
}

onPullDownRefresh(async () => {
  await refresh()
  uni.stopPullDownRefresh()
})

getList()
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.announcement-scroll {
  height: calc(100vh - 100rpx);
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
