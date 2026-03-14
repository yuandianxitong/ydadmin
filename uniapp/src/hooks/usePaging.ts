import { ref, reactive } from 'vue'
import type { PageResult } from '@/types/api'

interface PagingOptions<T> {
  fetchFun: (params: any) => Promise<PageResult<T>>
  params?: Record<string, any>
  size?: number
}

export function usePaging<T = any>(options: PagingOptions<T>) {
  const { fetchFun, params = {}, size = 15 } = options

  const pager = reactive({
    page: 1,
    size,
    loading: false,
    finished: false,
    refreshing: false,
    list: [] as T[],
    total: 0,
  })

  async function getList() {
    if (pager.loading || pager.finished) return
    pager.loading = true

    try {
      const result = await fetchFun({
        page_no: pager.page,
        page_size: pager.size,
        ...params,
      })
      if (pager.page === 1) {
        pager.list = result.list
      } else {
        pager.list = [...pager.list, ...result.list]
      }
      pager.total = result.pagination.total
      pager.finished = pager.page >= result.pagination.last_page
      pager.page++
    } finally {
      pager.loading = false
      pager.refreshing = false
    }
  }

  function refresh() {
    pager.page = 1
    pager.finished = false
    pager.refreshing = true
    pager.list = []
    return getList()
  }

  return { pager, getList, refresh }
}
