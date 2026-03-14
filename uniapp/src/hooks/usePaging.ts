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

  async function getList() {
    if (loading.value || finished.value) return
    loading.value = true

    try {
      const result = await fetchFun({
        page_no: page.value,
        page_size: pageSize.value,
        ...params,
      })
      if (page.value === 1) {
        list.value = result.list
      } else {
        list.value = [...list.value, ...result.list] as T[]
      }
      total.value = result.pagination.total
      finished.value = page.value >= result.pagination.last_page
      page.value++
    } finally {
      loading.value = false
      refreshing.value = false
    }
  }

  function refresh() {
    page.value = 1
    finished.value = false
    refreshing.value = true
    list.value = []
    return getList()
  }

  return { list, loading, finished, refreshing, total, getList, refresh }
}
