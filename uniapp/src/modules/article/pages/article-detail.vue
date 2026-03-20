<template>
  <d-page>
    <view v-if="article" class="article-detail">
      <!-- Title -->
      <text class="article-detail__title">{{ article.title }}</text>

      <!-- Meta -->
      <view class="article-detail__meta">
        <text v-if="article.author" class="meta-item">{{ article.author }}</text>
        <text v-if="article.category_name" class="meta-item">{{ article.category_name }}</text>
        <text class="meta-item">{{ formatDate(article.published_at || article.created_at) }}</text>
        <text class="meta-item">{{ article.views || 0 }} 阅读</text>
      </view>

      <!-- Tags -->
      <view v-if="tagList.length > 0" class="article-detail__tags">
        <wd-tag
          v-for="(tag, index) in tagList"
          :key="index"
          size="small"
          :type="tagColors[index % tagColors.length]"
          plain
          class="tag-item"
        >
          {{ tag }}
        </wd-tag>
      </view>

      <!-- Divider -->
      <view class="article-detail__divider" />

      <!-- Content -->
      <view class="article-detail__content">
        <rich-text :nodes="article.content" />
      </view>
    </view>

    <!-- Loading -->
    <view v-else-if="loading" class="loading-wrap">
      <wd-loading size="60rpx" />
    </view>

    <d-empty v-else text="文章不存在" />
  </d-page>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { articleApi, type ArticleItem } from '@/api/article'

const article = ref<ArticleItem | null>(null)
const loading = ref(true)

const tagColors = ['primary', 'success', 'warning', 'danger'] as const

const tagList = computed(() => {
  if (!article.value?.tags) return []
  const tags = article.value.tags
  // 后端 tags 字段为 JSON 类型，返回数组；兼容字符串格式
  if (Array.isArray(tags)) return tags.filter(Boolean)
  if (typeof tags === 'string') return tags.split(',').map((t) => t.trim()).filter(Boolean)
  return []
})

function formatDate(dateStr: string): string {
  if (!dateStr) return ''
  return dateStr.substring(0, 10)
}

onLoad((options) => {
  const id = Number(options?.id)
  if (!id) {
    loading.value = false
    return
  }
  articleApi
    .getDetail(id)
    .then((res) => {
      article.value = res
    })
    .catch(() => {
      article.value = null
    })
    .finally(() => {
      loading.value = false
    })
})
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.article-detail {
  background: #ffffff;
  border-radius: 16rpx;
  padding: 40rpx 32rpx;
  box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.04);

  &__title {
    display: block;
    font-size: 40rpx;
    font-weight: 700;
    color: $text-color;
    line-height: 1.5;
    margin-bottom: 24rpx;
  }

  &__meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12rpx;
    margin-bottom: 20rpx;

    .meta-item {
      font-size: 24rpx;
      color: $text-color-secondary;

      &:not(:last-child)::after {
        content: ' · ';
        margin-left: 12rpx;
      }
    }
  }

  &__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 12rpx;
    margin-bottom: 24rpx;

    .tag-item {
      margin: 0;
    }
  }

  &__divider {
    height: 1rpx;
    background: $border-color;
    margin-bottom: 32rpx;
  }

  &__content {
    font-size: 30rpx;
    color: $text-color;
    line-height: 1.8;
    word-break: break-all;

    :deep(img) {
      max-width: 100% !important;
      height: auto !important;
      border-radius: 8rpx;
      margin: 16rpx 0;
    }

    :deep(p) {
      margin-bottom: 20rpx;
    }

    :deep(h1),
    :deep(h2),
    :deep(h3) {
      font-weight: 600;
      margin-bottom: 16rpx;
    }
  }
}

.loading-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 200rpx 0;
}
</style>
