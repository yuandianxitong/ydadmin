import { ref } from 'vue'
import type { PageResult } from '@/types/api'

interface PagingOptions<T> {
  fetchFun: (params: any) => Promise<PageResult<T>>
  params?: Record<string, any>
  size?: number
}

export function usePaging<T = any>(options: PagingOptions<T>) {
  const { fetchFun, params = {}, size = 15 } = options

  const page = ref(1)
  const pageSize = ref(size)
  const loading = ref(false)
  const finished = ref(false)
  const refreshing = ref(false)
  const list = ref<T[]>([])
  const total = ref(0)
  /**
   * 标记是否完成过至少一次加载（成功/失败均算）。
   *
   * 用途：替代之前"loading 初始为 true"的方案——那个方案会让 `getList()` 的早期 return
   * 永久阻塞，导致页面卡住、接口永不调用。
   *
   * d-list-loader 等空状态组件可以根据 `hasLoaded === false` 抑制初始的"暂无数据"闪烁。
   */
  const hasLoaded = ref(false)

  async function getList() {
    if (loading.value || finished.value) return
    loading.value = true

    try {
      const currentPage = page.value
      const result = await fetchFun({
        page_no: currentPage,
        page_size: pageSize.value,
        ...params,
      })
      if (currentPage === 1) {
        list.value = result.list
      } else {
        list.value = [...list.value, ...result.list] as T[]
      }
      total.value = result.pagination.total
      finished.value = currentPage >= result.pagination.last_page
      page.value = currentPage + 1
    } finally {
      loading.value = false
      refreshing.value = false
      hasLoaded.value = true
    }
  }

  function refresh() {
    page.value = 1
    finished.value = false
    refreshing.value = true
    list.value = []
    return getList()
  }

  return { list, loading, finished, refreshing, total, hasLoaded, getList, refresh }
}
