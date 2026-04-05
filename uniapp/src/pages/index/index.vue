<template>
  <view class="home-page">
    <view class="home-body">
      <!-- Banner -->
      <view v-if="bannerList.length > 0" class="banner-section">
        <swiper
          class="banner-swiper"
          :autoplay="true"
          :interval="4000"
          :circular="true"
          indicator-dots
          indicator-active-color="#2979ff"
          indicator-color="rgba(255,255,255,0.6)"
        >
          <swiper-item v-for="(item, index) in bannerList" :key="index" @tap="item.url && goPage(item.url)">
            <image class="banner-image" :src="appStore.getImageUrl(item.image)" mode="aspectFill" />
          </swiper-item>
        </swiper>
      </view>

      <!-- Notice Bar -->
      <view v-if="latestNotice" class="notice-section" @tap="goNoticeDetail">
        <u-notice-bar
          :text="latestNotice.title"
          color="#2979ff"
          bgColor="#f0f7ff"
        />
      </view>

      <!-- Function Grid -->
      <view class="grid-section">
        <view class="grid-card">
          <view class="grid-item" @tap="goPage('/modules/announcement/pages/announcement-list')">
            <view class="grid-icon icon-bg-blue">
              <text class="iconfont icon-bell" />
            </view>
            <text class="grid-text">公告</text>
          </view>
          <view class="grid-item" @tap="goPage('/modules/feedback/pages/feedback')">
            <view class="grid-icon icon-bg-green">
              <text class="iconfont icon-chat-square-text" />
            </view>
            <text class="grid-text">反馈</text>
          </view>
          <view class="grid-item" @tap="goMessageTab">
            <view class="grid-icon icon-bg-orange">
              <text class="iconfont icon-envelope" />
            </view>
            <text class="grid-text">消息</text>
          </view>
          <view class="grid-item" @tap="goPage('/modules/agreement/pages/agreement?code=user_agreement')">
            <view class="grid-icon icon-bg-purple">
              <text class="iconfont icon-file-earmark-text" />
            </view>
            <text class="grid-text">协议</text>
          </view>
        </view>
      </view>

      <!-- Latest Articles -->
      <view class="article-section">
        <view class="section-header">
          <text class="section-title">最新文章</text>
          <text class="section-more" @tap="goDiscoverTab">更多 ></text>
        </view>
        <view v-if="articles.length > 0" class="article-list">
          <view
            v-for="item in articles"
            :key="item.id"
            class="article-item"
            @tap="goArticleDetail(item.id)"
          >
            <image
              v-if="item.cover"
              class="article-cover"
              :src="appStore.getImageUrl(item.cover)"
              mode="aspectFill"
            />
            <view class="article-info">
              <text class="article-title">{{ item.title }}</text>
              <view class="article-meta">
                <text v-if="item.category_name" class="article-category">{{ item.category_name }}</text>
                <text class="article-date">{{ formatDate(item.published_at || item.created_at) }}</text>
              </view>
            </view>
          </view>
        </view>
        <view v-else class="article-empty">
          <text class="article-empty-text">暂无文章</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { useAppStore } from '@/store/app.store'
import { announcementApi, type AnnouncementItem } from '@/api/announcement'
import { articleApi, type ArticleItem } from '@/api/article'

const appStore = useAppStore()

const latestNotice = ref<AnnouncementItem | null>(null)
const articles = ref<ArticleItem[]>([])

const bannerList = ref<{ image: string; url?: string }[]>([])

function formatDate(dateStr: string): string {
  if (!dateStr) return ''
  return dateStr.substring(0, 10)
}

function goPage(url: string) {
  uni.navigateTo({ url })
}

function goMessageTab() {
  uni.switchTab({ url: '/pages/message/index' })
}

function goDiscoverTab() {
  uni.switchTab({ url: '/pages/discover/index' })
}

function goNoticeDetail() {
  if (latestNotice.value) {
    uni.navigateTo({
      url: `/modules/announcement/pages/announcement-detail?id=${latestNotice.value.id}`,
    })
  }
}

function goArticleDetail(id: number) {
  uni.navigateTo({ url: `/modules/article/pages/article-detail?id=${id}` })
}

async function loadData() {
  // Ensure config is loaded
  await appStore.getConfig()

  // Load banner from config, fallback to article covers
  const config = appStore.config
  if (config?.banner_list && Array.isArray(config.banner_list) && config.banner_list.length > 0) {
    bannerList.value = config.banner_list
  }

  // Load latest announcement
  announcementApi
    .getList({ page_no: 1, page_size: 1 })
    .then((res) => {
      latestNotice.value = res.list.length > 0 ? res.list[0] : null
    })
    .catch(() => {})

  // Load latest articles
  articleApi
    .getList({ page_no: 1, page_size: 5 })
    .then((res) => {
      articles.value = res.list
      // 如果没有配置 banner，用带封面的文章作为轮播
      if (bannerList.value.length === 0) {
        bannerList.value = res.list
          .filter((a: ArticleItem) => a.cover)
          .slice(0, 4)
          .map((a: ArticleItem) => ({ image: a.cover, url: `/modules/article/pages/article-detail?id=${a.id}` }))
      }
    })
    .catch(() => {})
}

onShow(() => {
  loadData()
})
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.home-page {
  min-height: 100vh;
  background-color: $bg-color;
}

.header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  background: linear-gradient(135deg, #2979ff, #1e5fcc);

  &__content {
    display: flex;
    flex-direction: column;
  }

  &__info {
    padding: 20rpx 32rpx 28rpx;
  }

  &__title {
    display: block;
    font-size: 40rpx;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 6rpx;
  }

  &__subtitle {
    display: block;
    font-size: 24rpx;
    color: rgba(255, 255, 255, 0.8);
  }
}

.home-body {
  padding: 0 $page-padding $page-padding;
}

.banner-section {
  margin-bottom: 24rpx;
  margin-top: 24rpx;

  .banner-swiper {
    height: 300rpx;
    border-radius: 16rpx;
    overflow: hidden;
  }

  .banner-image {
    width: 100%;
    height: 100%;
    border-radius: 16rpx;
  }
}

.notice-section {
  margin-bottom: 24rpx;
  margin-top: 24rpx;
  border-radius: 16rpx;
  overflow: hidden;
}

.grid-section {
  margin-bottom: 24rpx;

  .grid-card {
    display: flex;
    background: #ffffff;
    border-radius: 16rpx;
    padding: 32rpx 16rpx;
    box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.04);
  }

  .grid-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .grid-icon {
    width: 88rpx;
    height: 88rpx;
    border-radius: 24rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16rpx;

    .iconfont {
      font-size: 40rpx;
      color: #ffffff;
    }
  }

  .icon-bg-blue { background: linear-gradient(135deg, #2979ff, #5b9bff); }
  .icon-bg-green { background: linear-gradient(135deg, #19be6b, #4dd893); }
  .icon-bg-orange { background: linear-gradient(135deg, #ff9900, #ffb74d); }
  .icon-bg-purple { background: linear-gradient(135deg, #7c4dff, #a98bff); }

  .grid-text {
    font-size: 24rpx;
    color: $text-color;
  }
}

.article-section {
  .section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20rpx;
  }

  .section-title {
    font-size: 32rpx;
    font-weight: 600;
    color: $text-color;
  }

  .section-more {
    font-size: 26rpx;
    color: $text-color-secondary;
  }
}

.article-list {
  .article-item {
    display: flex;
    align-items: center;
    background: #ffffff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 16rpx;
    box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.04);
  }

  .article-info {
    flex: 1;
    min-width: 0;
    margin-left: 20rpx;
  }

  .article-title {
    display: block;
    font-size: 28rpx;
    font-weight: 500;
    color: $text-color;
    margin-bottom: 16rpx;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.5;
  }

  .article-meta {
    display: flex;
    align-items: center;
    gap: 16rpx;
  }

  .article-category {
    font-size: 22rpx;
    color: $primary-color;
    background: rgba($primary-color, 0.1);
    padding: 4rpx 14rpx;
    border-radius: 8rpx;
  }

  .article-date {
    font-size: 22rpx;
    color: $text-color-secondary;
  }

  .article-cover {
    width: 160rpx;
    height: 110rpx;
    border-radius: 12rpx;
    flex-shrink: 0;
  }
}

.article-empty {
  text-align: center;
  padding: 60rpx 0;

  &-text {
    font-size: 26rpx;
    color: $text-color-secondary;
  }
}
</style>
