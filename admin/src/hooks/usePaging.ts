import { reactive, toRaw } from 'vue'

import type { ApiResponse, PageResult } from '@/types/common'

/**
 * 分页钩子
 *
 * 后端分页接口统一返回 `{ code, message, data: { list, pagination: { current_page, per_page, total, last_page } } }`，
 * `myRequest` 拦截器将整体 response 作为 `ApiResponse<T>` 返回（保留 data 字段），因此本 hook
 * 从 `res.data.list` / `res.data.pagination.total` 取数据。
 *
 * 使用示例：
 * ```ts
 * const { pager, getLists, resetPage, resetParams } = usePaging({
 *   fetchFun: (params) => adminApi.getList(params),
 *   params: searchForm,
 * })
 * ```
 *
 * @param options
 * {
 *   page?: number,             // 初始页，默认为 1
 *   size?: number,             // 每页条数，默认为 15
 *   fetchFun: (_arg) => Promise<ApiResponse<PageResult<T>>>, // 必传：返回分页接口 Promise 的函数
 *   params?: Record<string, any>, // 动态请求参数（会与 fixedParams 合并）
 *   fixedParams?: Record<string, any>, // 固定请求参数
 *   firstLoading?: boolean    // 初始是否显示 loading，默认为 false
 * }
 */
interface Options<T> {
    page?: number
    size?: number
    fetchFun: (arg: Record<string, any>) => Promise<ApiResponse<PageResult<T>>>
    params?: Record<string, any>
    fixedParams?: Record<string, any>
    firstLoading?: boolean
}

export function usePaging<T = any>(options: Options<T>) {
    const {
        page = 1,
        size = 15,
        fetchFun,
        params = {},
        fixedParams = {},
        firstLoading = false
    } = options

    // 记录初始 params 值，用于 resetParams
    const paramsInit: Record<string, any> = Object.assign({}, toRaw(params))

    const pager = reactive({
        page,
        size,
        loading: firstLoading,
        total: 0,
        lastPage: 1,
        lists: [] as T[]
    })

    /** 请求分页数据 */
    const getLists = () => {
        pager.loading = true
        return fetchFun({
            page: pager.page,
            limit: pager.size,
            ...params,
            ...fixedParams
        })
            .then((res) => {
                const payload = res?.data
                if (payload) {
                    pager.lists = (payload.list ?? []) as any
                    pager.total = payload.pagination?.total ?? 0
                    pager.lastPage = payload.pagination?.last_page ?? 1
                }
                return Promise.resolve(res)
            })
            .catch((err: any) => {
                return Promise.reject(err)
            })
            .finally(() => {
                pager.loading = false
            })
    }

    /** 重置到第一页并重新拉取 */
    const resetPage = () => {
        pager.page = 1
        getLists()
    }

    /** 重置 params 为初始值并重新拉取（页码重置到 1） */
    const resetParams = () => {
        Object.keys(paramsInit).forEach((key) => {
            params[key] = paramsInit[key]
        })
        pager.page = 1
        getLists()
    }

    return {
        pager,
        getLists,
        resetParams,
        resetPage
    }
}
