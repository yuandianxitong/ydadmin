<template>
  <div class="mx-auto max-w-800px px-6 py-10">
    <div v-if="loading" class="text-center py-20 text-gray-400">加载中...</div>
    <template v-else-if="article">
      <h1 class="text-3xl font-bold text-gray-900">{{ article.title }}</h1>
      <div class="flex items-center gap-4 text-sm text-gray-400 mt-4">
        <span v-if="article.category_name">{{ article.category_name }}</span>
        <span v-if="article.author">{{ article.author }}</span>
        <span>{{ article.published_at }}</span>
        <span>{{ article.views }} 阅读</span>
      </div>
      <div class="mt-8 prose max-w-none" v-html="article.content" />
      <div class="mt-12">
        <NuxtLink to="/article" class="text-blue-600 hover:text-blue-700 text-sm">&larr; 返回文章列表</NuxtLink>
      </div>
    </template>
    <div v-else class="text-center py-20 text-gray-400">文章不存在</div>
  </div>
</template>

<script setup lang="ts">
import { articleApi, type ArticleItem } from '~/api/article'

const route = useRoute()
const article = ref<ArticleItem | null>(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const res = await articleApi.getDetail(route.params.id as string)
    if (res.code === 1) article.value = res.data
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.prose :deep(img) {
  max-width: 100%;
  border-radius: 8px;
}
.prose :deep(p) {
  margin-bottom: 1em;
  line-height: 1.8;
}
.prose :deep(h2) {
  font-size: 1.5em;
  font-weight: 600;
  margin-top: 1.5em;
  margin-bottom: 0.5em;
}
.prose :deep(h3) {
  font-size: 1.25em;
  font-weight: 600;
  margin-top: 1.25em;
  margin-bottom: 0.5em;
}
</style>
