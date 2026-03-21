<template>
  <view class="discover-page">
    <!-- Category Tabs -->
    <wd-tabs v-model="activeTab" @change="handleTabChange">
      <wd-tab :name="0" title="全部" />
      <wd-tab
        v-for="cat in categories"
        :key="cat.id"
        :name="cat.id"
        :title="cat.name"
      />
    </wd-tabs>

    <!-- Article List -->
    <view class="article-list">
      <view
        v-for="(item, index) in list"
        :key="item.id"
        class="article-card"
        :class="{ 'article-card--large': index === 0 && item.cover }"
        @tap="goDetail(item.id)"
      >
        <!-- First item: large cover card -->
        <template v-if="index === 0 && item.cover">
          <image
            class="article-card__cover-large"
            :src="appStore.getImageUrl(item.cover)"
            mode="aspectFill"
          />
          <view class="article-card__body">
            <text class="article-card__title">{{ item.title }}</text>
            <view class="article-card__meta">
              <text v-if="item.category_name" class="article-card__tag">{{ item.category_name }}</text>
              <text class="article-card__date">{{ formatDate(item.published_at || item.created_at) }}</text>
            </view>
          </view>
        </template>

        <!-- Other items: horizontal card (cover left, text right) -->
        <template v-else>
          <image
            v-if="item.cover"
            class="article-card__thumb"
            :src="appStore.getImageUrl(item.cover)"
            mode="aspectFill"
          />
          <view class="article-card__content">
            <text class="article-card__title">{{ item.title }}</text>
            <text v-if="item.summary" class="article-card__summary">{{ item.summary }}</text>
            <view class="article-card__meta">
              <text v-if="item.category_name" class="article-card__tag">{{ item.category_name }}</text>
              <text class="article-card__date">{{ formatDate(item.published_at || item.created_at) }}</text>
            </view>
          </view>
        </template>
      </view>
    </view>

    <d-list-loader
      :loading="loading"
      :finished="finished"
      :total="total"
      empty-text="暂无文章"
    />
  </view>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { useAppStore } from '@/store/app.store'
import { articleApi, type ArticleItem, type ArticleCategory } from '@/api/article'
import { usePaging } from '@/hooks/usePaging'

const appStore = useAppStore()
const activeTab = ref(0)
const categories = ref<ArticleCategory[]>([])

const filterParams = reactive<{ category_id?: number }>({})

const { list, loading, finished, refreshing, total, getList, refresh } = usePaging<ArticleItem>({
  fetchFun: (params) =>
    articleApi.getList({
      ...params,
      ...(filterParams.category_id ? { category_id: filterParams.category_id } : {}),
    }),
})

function formatDate(dateStr: string): string {
  if (!dateStr) return ''
  return dateStr.substring(0, 10)
}

function handleTabChange({ value }: { value: number }) {
  if (value === 0) {
    filterParams.category_id = undefined
  } else {
    filterParams.category_id = value
  }
  refresh()
}

function handleRefresh() {
  refresh()
}

function goDetail(id: number) {
  uni.navigateTo({ url: `/modules/article/pages/article-detail?id=${id}` })
}

// Load categories
function loadCategories() {
  articleApi
    .getCategoryList()
    .then((res) => {
      categories.value = res
    })
    .catch(() => {})
}

onShow(() => {
  if (categories.value.length === 0) {
    loadCategories()
  }
  if (list.value.length === 0) {
    getList()
  }
})
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.discover-page {
  min-height: 100vh;
  background-color: $bg-color;
}

.article-list {
  padding: 20rpx $page-padding;
  padding-bottom: env(safe-area-inset-bottom);
}

.article-card {
  background: #ffffff;
  border-radius: 16rpx;
  margin-bottom: 20rpx;
  overflow: hidden;
  box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.04);

  &--large {
    .article-card__cover-large {
      width: 100%;
      height: 340rpx;
    }

    .article-card__body {
      padding: 24rpx;
    }
  }

  &:not(&--large) {
    display: flex;
    align-items: center;
    padding: 24rpx;
  }

  &__content {
    flex: 1;
    min-width: 0;
    margin-left: 20rpx;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }

  &__title {
    display: block;
    font-size: 28rpx;
    font-weight: 500;
    color: $text-color;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 8rpx;
  }

  &__summary {
    display: block;
    font-size: 24rpx;
    color: $text-color-secondary;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 8rpx;
  }

  &__meta {
    display: flex;
    align-items: center;
    gap: 16rpx;
  }

  &__tag {
    font-size: 22rpx;
    color: $primary-color;
    background: rgba($primary-color, 0.1);
    padding: 4rpx 14rpx;
    border-radius: 8rpx;
  }

  &__date {
    font-size: 22rpx;
    color: $text-color-secondary;
  }

  &__thumb {
    width: 180rpx;
    height: 120rpx;
    border-radius: 12rpx;
    flex-shrink: 0;
  }
}
</style>
