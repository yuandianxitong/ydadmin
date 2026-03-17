<template>
  <div class="mx-auto max-w-1200px px-4 mt-5 pb-10">
    <div class="flex gap-5">
      <!-- 左栏 -->
      <div class="flex-1 min-w-0">
        <!-- 分类筛选 -->
        <div class="bg-white rounded-lg px-4 pt-3">
          <div class="flex items-center gap-1 border-b border-gray-100">
            <button
              class="px-4 py-2.5 text-sm transition-colors relative"
              :class="activeCategoryId === 0
                ? 'text-[var(--color-primary)] font-medium'
                : 'text-gray-500 hover:text-gray-700'"
              @click="filterByCategory(0)"
            >
              全部
              <span
                v-if="activeCategoryId === 0"
                class="absolute bottom-0 left-1/2 -translate-x-1/2 w-6 h-0.5 bg-[var(--color-primary)] rounded-full"
              />
            </button>
            <button
              v-for="cat in categories"
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
                class="absolute bottom-0 left-1/2 -translate-x-1/2 w-6 h-0.5 bg-[var(--color-primary)] rounded-full"
              />
            </button>
          </div>
        </div>

        <!-- 文章列表 -->
        <div class="bg-white rounded-lg mt-3 px-4">
          <div v-if="loading" class="text-center py-16 text-gray-400">加载中...</div>
          <template v-else>
            <ArticleCard v-for="item in articles" :key="item.id" :article="item" />
            <div v-if="!articles.length" class="text-center py-16 text-gray-400">暂无文章</div>
          </template>

          <!-- 分页 -->
          <div v-if="totalPages > 1" class="flex justify-center items-center gap-2 py-5">
            <button
              class="px-3 py-1.5 text-sm rounded border transition-colors"
              :class="currentPage <= 1 ? 'text-gray-300 border-gray-200 cursor-not-allowed' : 'text-gray-600 border-gray-300 hover:text-[var(--color-primary)] hover:border-[var(--color-primary)]'"
              :disabled="currentPage <= 1"
              @click="changePage(currentPage - 1)"
            >
              上一页
            </button>
            <button
              v-for="p in visiblePages"
              :key="p"
              class="w-8 h-8 text-sm rounded transition-colors"
              :class="currentPage === p
                ? 'bg-[var(--color-primary)] text-white'
                : 'text-gray-600 hover:text-[var(--color-primary)]'"
              @click="changePage(p)"
            >
              {{ p }}
            </button>
            <button
              class="px-3 py-1.5 text-sm rounded border transition-colors"
              :class="currentPage >= totalPages ? 'text-gray-300 border-gray-200 cursor-not-allowed' : 'text-gray-600 border-gray-300 hover:text-[var(--color-primary)] hover:border-[var(--color-primary)]'"
              :disabled="currentPage >= totalPages"
              @click="changePage(currentPage + 1)"
            >
              下一页
            </button>
          </div>
        </div>
      </div>

      <!-- 右栏 -->
      <div class="w-72 flex-shrink-0 flex flex-col gap-4">
        <SidebarHot :list="hotArticles" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { articleApi, type ArticleCategory, type ArticleItem } from '~/api/article'

const categories = ref<ArticleCategory[]>([])
const articles = ref<ArticleItem[]>([])
const hotArticles = ref<ArticleItem[]>([])
const loading = ref(true)
const activeCategoryId = ref(0)
const currentPage = ref(1)
const pageSize = 10
const total = ref(0)
const totalPages = computed(() => Math.ceil(total.value / pageSize))

const visiblePages = computed(() => {
  const pages: number[] = []
  const start = Math.max(1, currentPage.value - 2)
  const end = Math.min(totalPages.value, start + 4)
  for (let i = start; i <= end; i++) pages.push(i)
  return pages
})

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
  const [catRes, hotRes] = await Promise.all([
    articleApi.getCategoryList(),
    fetchArticles(),
  ])
  if (catRes.code === 1) categories.value = catRes.data

  // Hot articles (sorted by views)
  const allRes = await articleApi.getList({ page_no: 1, page_size: 10 })
  if (allRes.code === 1) {
    hotArticles.value = [...allRes.data.list].sort((a, b) => b.views - a.views).slice(0, 10)
  }
})
</script>
