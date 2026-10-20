<template>
  <d-page>
    <view class="heading">
      <view>
        <view class="heading-title">每一条建议，都有回音</view>
        <text class="heading-hint">已提交 {{ total }} 条反馈</text>
      </view>
      <text class="submit-link" @tap="submitFeedback">写反馈 →</text>
    </view>
    <scroll-view scroll-y class="feedback-scroll" @scrolltolower="loadMore">
      <view v-for="item in list" :key="item.id" class="feedback-card">
        <view class="card-header">
          <text class="type-label">{{ types[item.type] || '其他' }}</text>
          <text class="status" :class="{ answered: !!item.reply }">{{ statuses[item.status] || '待处理' }}</text>
        </view>
        <text class="content">{{ item.content }}</text>
        <view v-if="item.images?.length" class="images">
          <image v-for="(image, index) in item.images" :key="index" :src="appStore.getImageUrl(image)" mode="aspectFill" @tap="preview(item, index)" />
        </view>
        <text class="date">{{ item.created_at }}</text>
        <view v-if="item.reply" class="reply">
          <text class="reply-label">官方回复</text>
          <text class="content">{{ item.reply }}</text>
          <text v-if="item.replied_at" class="date">{{ item.replied_at }}</text>
        </view>
      </view>
      <d-list-loader v-if="hasLoaded || loading" :loading="loading" :finished="finished" :total="total" empty-text="还没有反馈，欢迎告诉我们您的想法" />
    </scroll-view>
  </d-page>
</template>

<script setup lang="ts">
import { onShow, onPullDownRefresh } from '@dcloudio/uni-app'
import { feedbackApi, type FeedbackInfo } from '@/api/feedback'
import { usePaging } from '@/hooks/usePaging'
import { useAppStore } from '@/store/app.store'

const appStore = useAppStore()
const statuses: Record<number, string> = { 0: '待处理', 1: '处理中', 2: '已回复', 3: '已关闭' }
const types: Record<string, string> = { suggestion: '功能建议', bug: '问题反馈', complaint: '投诉', other: '其他' }
const { list, loading, finished, total, hasLoaded, getList, refresh } = usePaging<FeedbackInfo>({ fetchFun: feedbackApi.getList })
const submitFeedback = () => uni.navigateTo({ url: '/modules/feedback/pages/feedback' })
const loadMore = () => getList().catch(() => {})

function preview(item: FeedbackInfo, index: number) {
  const urls = item.images.map(image => appStore.getImageUrl(image))
  uni.previewImage({ urls, current: urls[index] })
}

onShow(() => { void refresh().catch(() => {}) })
onPullDownRefresh(async () => {
  try { await refresh() } catch { /* 请求层已提示 */ }
  finally { uni.stopPullDownRefresh() }
})
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';
.heading { display: flex; align-items: center; justify-content: space-between; padding: 12rpx 4rpx 30rpx; gap: 20rpx; }
.heading-title { font-size: 32rpx; font-weight: 600; color: $text-color; margin-bottom: 10rpx; }
.heading-hint, .date { font-size: 24rpx; color: $text-color-secondary; }
.submit-link { flex-shrink: 0; font-size: 26rpx; color: $primary-color; }
.feedback-scroll { height: calc(100vh - 240rpx - env(safe-area-inset-bottom)); }
.feedback-card { padding: 30rpx; margin-bottom: 24rpx; border-radius: 24rpx; background: white; box-shadow: 0 4rpx 16rpx rgba(0,0,0,.04); }
.card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24rpx; }
.type-label { font-size: 26rpx; font-weight: 600; color: $text-color; }
.status { font-size: 22rpx; color: #9b6600; background: #fff5df; border-radius: 30rpx; padding: 6rpx 18rpx; }
.status.answered { color: #21744a; background: #eaf7ef; }
.content { display: block; font-size: 28rpx; line-height: 1.7; color: $text-color; white-space: pre-wrap; overflow-wrap: anywhere; margin-bottom: 20rpx; }
.images { display: flex; gap: 12rpx; margin-bottom: 20rpx; flex-wrap: wrap; }
.images image { width: 160rpx; height: 160rpx; border-radius: 12rpx; }
.reply { background: #f6f8fc; padding: 24rpx; margin-top: 24rpx; border-radius: 16rpx; }
.reply-label { display: block; font-size: 24rpx; font-weight: 600; color: $primary-color; margin-bottom: 14rpx; }
</style>
