<template>
  <div class="mx-auto max-w-1200px px-4">
    <!-- Banner 区域 -->
    <div class="mt-5 flex gap-4">
      <!-- 左侧大 Swiper Banner -->
      <div class="flex-1 rounded-sm overflow-hidden h-72">
        <Swiper
          :modules="[SwiperAutoplay, SwiperPagination]"
          :autoplay="{ delay: 4000, disableOnInteraction: false }"
          :pagination="{ clickable: true }"
          :loop="true"
          class="w-full h-full"
        >
          <SwiperSlide v-for="(item, idx) in bannerArticles" :key="item.id">
            <NuxtLink :to="`/article/${item.id}`" class="block w-full h-full relative">
              <img
                :src="item.cover || defaultBanners[idx % defaultBanners.length]"
                :alt="item.title"
                class="w-full h-full object-cover"
              />
              <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
              <div class="absolute bottom-0 left-0 right-0 p-5">
                <h2 class="text-white text-xl font-bold line-clamp-2 leading-snug">{{ item.title }}</h2>
              </div>
            </NuxtLink>
          </SwiperSlide>
          <!-- Empty state -->
          <SwiperSlide v-if="!bannerArticles.length">
            <div class="w-full h-full bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center">
              <div class="text-center text-white">
                <h2 class="text-2xl font-bold">欢迎来到元点Admin</h2>
                <p class="mt-2 text-blue-200">基于 ThinkPHP 8 + Vue 3 的开源通用管理系统</p>
              </div>
            </div>
          </SwiperSlide>
        </Swiper>
      </div>
      <!-- 右侧 2x2 小卡片 -->
      <div class="w-72 flex-shrink-0 grid grid-cols-2 grid-rows-2 gap-3">
        <NuxtLink
          v-for="item in sideCards"
          :key="item.id"
          :to="`/article/${item.id}`"
          class="relative rounded-sm overflow-hidden group"
        >
          <img
            :src="item.cover || '/pc/placeholder.svg'"
            :alt="item.title"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          />
          <div class="absolute inset-0 bg-black/30 group-hover:bg-black/40 transition-colors" />
          <span class="absolute bottom-2 left-2 right-2 text-white text-xs line-clamp-2 leading-snug font-medium">{{ item.title }}</span>
        </NuxtLink>
        <!-- Empty placeholders -->
        <div v-for="i in Math.max(0, 4 - sideCards.length)" :key="`empty-${i}`" class="rounded-sm bg-gray-200 flex items-center justify-center">
          <span class="text-xs text-gray-400">暂无</span>
        </div>
      </div>
    </div>

    <!-- 主体内容：左右两栏 -->
    <div class="mt-6 flex gap-5 pb-10">
      <!-- 左栏：分类 Tab + 文章列表 -->
      <div class="flex-1 min-w-0">
        <!-- 分类 Tab -->
        <div class="bg-white rounded-sm px-4 pt-3">
          <div class="flex items-center gap-1 border-b border-gray-100">
            <button
              v-for="cat in categoryTabs"
              :key="cat.id"
              class="px-4 py-2.5 text-sm transition-colors relative"
              :class="activeCategoryId === cat.id
                ? 'text-[var(--color-primary)] font-medium'
                : 'text-gray-500 hover:text-gray-700'"
              @click="filterByCategory(cat.id)"
            >
              {{ cat.name }}
              <span
                v-if="activeCategoryId === cat.id"
                class="absolute bottom-0 left-0 right-0 mx-auto w-6 h-0.5 bg-[var(--color-primary)]"
              />
            </button>
          </div>
        </div>

        <!-- 文章列表 -->
        <div class="bg-white rounded-sm mt-3 px-4">
          <div v-if="loading" class="text-center py-16 text-gray-400">加载中...</div>
          <template v-else>
            <ArticleCard v-for="item in articles" :key="item.id" :article="item" />
            <div v-if="!articles.length" class="text-center py-16 text-gray-400">暂无文章</div>
          </template>

          <!-- 加载更多 -->
          <div v-if="hasMore && articles.length" class="py-4 text-center">
            <button
              class="btn-outline px-8"
              :disabled="loadingMore"
              @click="loadMore"
            >
              {{ loadingMore ? '加载中...' : '点击加载更多' }}
            </button>
          </div>
        </div>
      </div>

      <!-- 右栏：侧边栏 -->
      <div class="w-72 flex-shrink-0 flex flex-col gap-4">
        <SidebarNews :list="newsArticles" />
        <SidebarAnnouncement :list="announcements" />
        <SidebarHot :list="hotArticles" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Autoplay as SwiperAutoplay, Pagination as SwiperPagination } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/pagination'

import { articleApi, type ArticleCategory, type ArticleItem } from '~/api/article'
import { get } from '~/composables/useRequest'

const defaultBanners = [
  'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="800" height="400"><rect fill="%232563eb" width="800" height="400"/></svg>',
]

// Data
const articles = ref<ArticleItem[]>([])
const bannerArticles = ref<ArticleItem[]>([])
const sideCards = ref<ArticleItem[]>([])
const newsArticles = ref<ArticleItem[]>([])
const hotArticles = ref<ArticleItem[]>([])
const announcements = ref<{ id: number; title: string }[]>([])
const categories = ref<ArticleCategory[]>([])

const loading = ref(true)
const loadingMore = ref(false)
const activeCategoryId = ref(0)
const currentPage = ref(1)
const pageSize = 10
const total = ref(0)
const hasMore = computed(() => articles.value.length < total.value)

const categoryTabs = computed(() => [
  { id: 0, name: '推荐' },
  ...categories.value.slice(0, 6),
])

async function fetchArticles(append = false) {
  if (append) {
    loadingMore.value = true
  } else {
    loading.value = true
  }
  try {
    const params: Record<string, any> = { page_no: currentPage.value, page_size: pageSize }
    if (activeCategoryId.value) params.category_id = activeCategoryId.value
    const res = await articleApi.getList(params)
    if (res.code === 1) {
      if (append) {
        articles.value.push(...res.data.list)
      } else {
        articles.value = res.data.list
      }
      total.value = res.data.total
    }
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

function filterByCategory(id: number) {
  activeCategoryId.value = id
  currentPage.value = 1
  fetchArticles()
}

function loadMore() {
  currentPage.value++
  fetchArticles(true)
}

onMounted(async () => {
  const [catRes, , announcementRes] = await Promise.all([
    articleApi.getCategoryList(),
    fetchArticles(),
    get<{ list: { id: number; title: string }[] }>('/api/announcement/list', { page_no: 1, page_size: 8 }),
  ])

  if (catRes.code === 1) categories.value = catRes.data
  if (announcementRes.code === 1) announcements.value = announcementRes.data.list

  // Use first few articles for banner/side cards/news/hot
  const allArticles = articles.value
  bannerArticles.value = allArticles.filter(a => a.is_top || a.cover).slice(0, 5)
  if (!bannerArticles.value.length) bannerArticles.value = allArticles.slice(0, 3)
  sideCards.value = allArticles.filter(a => a.cover).slice(0, 4)
  newsArticles.value = allArticles.slice(0, 8)
  hotArticles.value = [...allArticles].sort((a, b) => b.views - a.views).slice(0, 10)
})
</script>

<style scoped>
:deep(.swiper-pagination-bullet) {
  background: #fff;
  opacity: 0.6;
}
:deep(.swiper-pagination-bullet-active) {
  background: #fff;
  opacity: 1;
}
</style>
