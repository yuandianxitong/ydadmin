import { reactive, toRaw } from 'vue'

import type { ApiResponse, PageResult } from '@/types/api'

/**
 * 分页钩子函数
 * @param options
 * {
 *   page?: number,             // 初始页，默认为 1
 *   size?: number,             // 每页条数，默认为 15
 *   fetchFun: (_arg: any) => Promise<ApiResponse<PageResult<T>>>, // 必传：返回分页接口 Promise 的函数
 *   params?: Record<string, any>, // 动态请求参数
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

    // 记录初始 params 值
    const paramsInit: Record<string, any> = Object.assign({}, toRaw(params))

    const pager = reactive({
        page,
        size,
        loading: firstLoading,
        count: 0,
        lists: [] as T[],
        extend: {} as Record<string, any>
    })

    /** 请求分页数据 */
    const getLists = () => {
        pager.loading = true
        return fetchFun({
            page_no: pager.page,
            page_size: pager.size,
            ...params,
            ...fixedParams
        })
            .then((res: any) => {
                pager.count = res?.count
                pager.lists = res?.lists
                pager.extend = res?.extend
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

    /** 重置 params 并重新拉取 */
    const resetParams = () => {
        Object.keys(paramsInit).forEach((key) => {
            params[key] = paramsInit[key]
        })
        getLists()
    }

    return {
        pager,
        getLists,
        resetParams,
        resetPage
    }
}
