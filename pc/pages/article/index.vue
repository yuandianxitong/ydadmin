<template>
  <div class="mx-auto max-w-1200px px-6 py-10">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">文章列表</h1>

    <!-- 分类筛选 -->
    <div class="flex flex-wrap gap-2 mb-8">
      <button
        class="px-3 py-1 text-sm rounded-full transition-colors"
        :class="activeCategoryId === 0 ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
        @click="filterByCategory(0)"
      >
        全部
      </button>
      <button
        v-for="cat in categories"
        :key="cat.id"
        class="px-3 py-1 text-sm rounded-full transition-colors"
        :class="activeCategoryId === cat.id ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
        @click="filterByCategory(cat.id)"
      >
        {{ cat.name }}
      </button>
    </div>

    <!-- 文章列表 -->
    <div v-if="loading" class="text-center py-10 text-gray-400">加载中...</div>
    <div v-else-if="articles.length" class="flex flex-col gap-4">
      <ArticleCard v-for="item in articles" :key="item.id" :article="item" />
    </div>
    <div v-else class="text-center py-10 text-gray-400">暂无文章</div>

    <!-- 分页 -->
    <div v-if="totalPages > 1" class="flex justify-center gap-2 mt-10">
      <button
        class="px-3 py-1 text-sm rounded border"
        :class="currentPage <= 1 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-50'"
        :disabled="currentPage <= 1"
        @click="changePage(currentPage - 1)"
      >
        上一页
      </button>
      <span class="px-3 py-1 text-sm text-gray-500">{{ currentPage }} / {{ totalPages }}</span>
      <button
        class="px-3 py-1 text-sm rounded border"
        :class="currentPage >= totalPages ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-50'"
        :disabled="currentPage >= totalPages"
        @click="changePage(currentPage + 1)"
      >
        下一页
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { articleApi, type ArticleCategory, type ArticleItem } from '~/api/article'

const categories = ref<ArticleCategory[]>([])
const articles = ref<ArticleItem[]>([])
const loading = ref(true)
const activeCategoryId = ref(0)
const currentPage = ref(1)
const pageSize = 10
const total = ref(0)
const totalPages = computed(() => Math.ceil(total.value / pageSize))

async function fetchArticles() {
  loading.value = true
  try {
    const params: Record<string, any> = { page_no: currentPage.value, page_size: pageSize }
    if (activeCategoryId.value) params.category_id = activeCategoryId.value
    const res = await articleApi.getList(params)
    if (res.code === 1) {
      articles.value = res.data.list
      total.value = res.data.total
    }
  } finally {
    loading.value = false
  }
}

function filterByCategory(id: number) {
  activeCategoryId.value = id
  currentPage.value = 1
  fetchArticles()
}

function changePage(page: number) {
  currentPage.value = page
  fetchArticles()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

onMounted(async () => {
  const [catRes] = await Promise.all([
    articleApi.getCategoryList(),
    fetchArticles(),
  ])
  if (catRes.code === 1) categories.value = catRes.data
})
</script>
