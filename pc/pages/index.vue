<template>
  <div>
    <!-- Hero -->
    <section class="bg-white">
      <div class="mx-auto max-w-1200px px-6 py-16 text-center">
        <h1 class="text-4xl font-bold text-gray-900">欢迎来到元点Admin</h1>
        <p class="mt-4 text-lg text-gray-500">基于 ThinkPHP 8 + Vue 3 的开源通用管理系统</p>
        <div class="mt-8 flex justify-center gap-4">
          <NuxtLink to="/article" class="btn-primary">浏览文章</NuxtLink>
          <NuxtLink to="/login" class="btn-outline">立即登录</NuxtLink>
        </div>
      </div>
    </section>

    <!-- 公告 -->
    <section v-if="announcements.length" class="mx-auto max-w-1200px px-6 mt-10">
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-start gap-3">
          <span class="text-blue-600 font-semibold text-sm flex-shrink-0">公告</span>
          <div class="text-sm text-blue-800">
            <p v-for="item in announcements" :key="item.id" class="mb-1 last:mb-0">{{ item.title }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- 最新文章 -->
    <section class="mx-auto max-w-1200px px-6 mt-10 pb-16">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">最新文章</h2>
      <div v-if="loading" class="text-center py-10 text-gray-400">加载中...</div>
      <div v-else-if="articles.length" class="flex flex-col gap-4">
        <ArticleCard v-for="item in articles" :key="item.id" :article="item" />
      </div>
      <div v-else class="text-center py-10 text-gray-400">暂无文章</div>
      <div v-if="articles.length" class="mt-8 text-center">
        <NuxtLink to="/article" class="text-blue-600 hover:text-blue-700 text-sm">查看全部文章 &rarr;</NuxtLink>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { articleApi, type ArticleItem } from '~/api/article'
import { get } from '~/composables/useRequest'

const articles = ref<ArticleItem[]>([])
const announcements = ref<{ id: number; title: string }[]>([])
const loading = ref(true)

onMounted(async () => {
  try {
    const [articleRes, announcementRes] = await Promise.all([
      articleApi.getList({ page_no: 1, page_size: 10 }),
      get<{ list: { id: number; title: string }[] }>('/api/announcement/list', { page_no: 1, page_size: 5 }),
    ])
    if (articleRes.code === 1) articles.value = articleRes.data.list
    if (announcementRes.code === 1) announcements.value = announcementRes.data.list
  } finally {
    loading.value = false
  }
})
</script>
