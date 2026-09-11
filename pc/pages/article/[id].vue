<template>
  <div class="mx-auto max-w-800px px-6 py-10">
    <div v-if="loading" class="text-center py-20 text-gray-400">加载中...</div>
    <template v-else-if="article">
      <h1 class="text-3xl font-bold text-gray-900">{{ article.title }}</h1>
      <div class="flex items-center gap-4 text-sm text-gray-400 mt-4">
        <span v-if="article.category_name" class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded-sm">{{ article.category_name }}</span>
        <span v-if="article.author">{{ article.author }}</span>
        <span class="inline-flex items-center gap-1"><i class="i-svg-calendar-days text-3.5" />{{ article.published_at }}</span>
        <span class="inline-flex items-center gap-1"><i class="i-svg-eye text-3.5" />{{ article.views }} 阅读</span>
      </div>
      <div v-if="article.tags && article.tags.length" class="flex items-center gap-2 mt-3">
        <i class="i-svg-tag text-3.5 text-gray-400" />
        <span v-for="tag in article.tags" :key="tag" class="px-2 py-0.5 text-xs bg-gray-100 text-gray-500 rounded-sm">{{ tag }}</span>
      </div>
      <div class="mt-8 prose max-w-none" v-html="sanitizedContent" />
      <div class="mt-12">
        <NuxtLink to="/article" class="text-blue-600 hover:text-blue-700 text-sm">&larr; 返回文章列表</NuxtLink>
      </div>
    </template>
    <div v-else class="text-center py-20 text-gray-400">文章不存在</div>
  </div>
</template>

<script setup lang="ts">
import DOMPurify from 'dompurify'
import { articleApi, type ArticleItem } from '~/api/article'

const route = useRoute()
const article = ref<ArticleItem | null>(null)
const loading = ref(true)
const sanitizedContent = computed(() => article.value ? DOMPurify.sanitize(article.value.content) : '')

onMounted(async () => {
  try {
    const res = await articleApi.getDetail(route.params.id as string)
    if (res.code === 200) article.value = res.data
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.prose :deep(img) {
  max-width: 100%;
  border-radius: 2px;
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
