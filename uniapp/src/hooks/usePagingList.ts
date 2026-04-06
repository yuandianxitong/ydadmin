import { onPullDownRefresh, onShow } from '@dcloudio/uni-app'

import type { PageResult } from '@/types/api'
import { usePaging } from './usePaging'

/**
 * 更高层的列表页 composable
 *
 * 在 `usePaging` 基础上自动注册 `onShow` / `onPullDownRefresh` 生命周期钩子，
 * 替代所有列表页重复写的三件套：
 * ```ts
 * const { list, ... } = usePaging(...)
 * onShow(() => refresh())
 * onPullDownRefresh(async () => { await refresh(); uni.stopPullDownRefresh() })
 * ```
 *
 * 使用示例：
 * ```ts
 * const { list, loading, finished, getList } = usePagingList({
 *   fetchFun: (params) => announcementApi.getList(params),
 * })
 * ```
 *
 * @param options
 * @param options.fetchFun 必需的分页接口
 * @param options.params   固定查询参数
 * @param options.size     每页大小
 * @param options.refreshOnShow 是否在 onShow 时 refresh，默认 true
 * @param options.enablePullDown 是否启用下拉刷新注册，默认 true
 */
interface PagingListOptions<T> {
  fetchFun: (params: any) => Promise<PageResult<T>>
  params?: Record<string, any>
  size?: number
  refreshOnShow?: boolean
  enablePullDown?: boolean
}

export function usePagingList<T = any>(options: PagingListOptions<T>) {
  const { refreshOnShow = true, enablePullDown = true, ...pagingOptions } = options

  const paging = usePaging<T>(pagingOptions)

  if (refreshOnShow) {
    onShow(() => {
      paging.refresh()
    })
  }

  if (enablePullDown) {
    onPullDownRefresh(async () => {
      await paging.refresh()
      uni.stopPullDownRefresh()
    })
  }

  return paging
}
