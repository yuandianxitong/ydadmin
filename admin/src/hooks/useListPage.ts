import { ElMessage, ElMessageBox } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'

import type { ApiResponse, PageResult } from '@/types/api'
import { t } from '@/utils/i18n'

/**
 * 列表页通用 composable
 *
 * 封装所有后台列表页面的通用模式：
 *   - 分页状态管理（对接后端 `page/limit` + `pagination.total`）
 *   - 搜索/重置/自动首次加载
 *   - 删除确认 + 成功回调
 *   - 批量删除确认 + 成功回调
 *   - 状态切换（带失败回滚）
 *
 * 列表页只需提供 API 函数和初始搜索表单即可，不再需要手写 20+ 行样板。
 *
 * 示例：
 * ```ts
 * const {
 *   list, loading, pagination, searchForm,
 *   getList, resetSearch, handleDelete, handleStatusChange,
 * } = useListPage({
 *   fetchFn: (params) => adminApi.getAdminList(params),
 *   deleteFn: (id) => adminApi.deleteAdmin(id),
 *   updateStatusFn: (id, status) => adminApi.updateAdminStatus(id, status),
 *   defaultSearchForm: { keyword: '', status: '' },
 * })
 * ```
 */
export interface UseListPageOptions<T, Q extends Record<string, any>> {
    /** 必需：获取列表的 API 函数 */
    fetchFn: (params: any) => Promise<ApiResponse<PageResult<T>>>
    /** 可选：删除单条的 API 函数 */
    deleteFn?: (id: number) => Promise<any>
    /** 可选：批量删除的 API 函数 */
    batchDeleteFn?: (ids: number[]) => Promise<any>
    /** 可选：更新状态的 API 函数 */
    updateStatusFn?: (id: number, status: number) => Promise<any>
    /** 初始搜索表单 */
    defaultSearchForm?: Q
    /** 每页大小，默认 20 */
    pageSize?: number
    /** 是否 mounted 后自动加载，默认 true */
    immediate?: boolean
}

export function useListPage<T = any, Q extends Record<string, any> = Record<string, any>>(
    options: UseListPageOptions<T, Q>
) {
    const {
        fetchFn,
        deleteFn,
        batchDeleteFn,
        updateStatusFn,
        defaultSearchForm,
        pageSize = 20,
        immediate = true
    } = options

    const list = ref<T[]>([])
    const loading = ref(false)
    const pagination = reactive({
        page: 1,
        limit: pageSize,
        total: 0
    })
    // 浅拷贝初始搜索表单，resetSearch 时用于重置
    const initialForm = { ...(defaultSearchForm || {}) } as Q
    const searchForm = reactive({ ...initialForm }) as Q

    /** 加载列表数据 */
    async function getList() {
        loading.value = true
        try {
            const params = {
                ...searchForm,
                page: pagination.page,
                limit: pagination.limit
            }
            const res = await fetchFn(params)
            const payload = res?.data
            if (payload) {
                list.value = (payload.list || []) as any
                pagination.total = payload.pagination?.total ?? 0
            }
        } finally {
            loading.value = false
        }
    }

    /** 重置搜索条件并回到第一页 */
    function resetSearch() {
        Object.assign(searchForm, initialForm)
        pagination.page = 1
        getList()
    }

    /** 搜索（回到第一页） */
    function handleSearch() {
        pagination.page = 1
        getList()
    }

    /** 分页页码变化 */
    function handlePageChange(page: number) {
        pagination.page = page
        getList()
    }

    /** 分页每页条数变化 */
    function handleSizeChange(size: number) {
        pagination.limit = size
        pagination.page = 1
        getList()
    }

    /** 删除单条（带确认弹窗） */
    async function handleDelete(id: number, name?: string) {
        if (!deleteFn) {
            console.warn('[useListPage] handleDelete called but deleteFn not provided')
            return
        }
        const confirmMessage = name
            ? t('message.deleteConfirmName', { name })
            : t('message.deleteConfirm')
        try {
            await ElMessageBox.confirm(confirmMessage, t('message.confirmDelete'), {
                confirmButtonText: t('common.confirm'),
                cancelButtonText: t('common.cancel'),
                type: 'warning'
            })
        } catch {
            return
        }
        try {
            await deleteFn(id)
            ElMessage.success(t('message.deleteSuccess'))
            getList()
        } catch {
            // request.ts 响应拦截器已显示 ElMessage 错误提示，此处静默吞掉错误。
            // 不能让错误继续冒泡到事件处理器，否则会触发 App.vue 的 ErrorBoundary 错误页面。
        }
    }

    /** 批量删除（带确认弹窗） */
    async function handleBatchDelete(ids: number[]) {
        if (!batchDeleteFn) {
            console.warn('[useListPage] handleBatchDelete called but batchDeleteFn not provided')
            return
        }
        if (ids.length === 0) {
            ElMessage.warning(t('message.pleaseSelect'))
            return
        }
        try {
            await ElMessageBox.confirm(
                t('message.batchDeleteConfirm'),
                t('message.confirmDelete'),
                {
                    confirmButtonText: t('common.confirm'),
                    cancelButtonText: t('common.cancel'),
                    type: 'warning'
                }
            )
        } catch {
            return
        }
        try {
            await batchDeleteFn(ids)
            ElMessage.success(t('message.deleteSuccess'))
            getList()
        } catch {
            // 同 handleDelete：静默吞掉防止 ErrorBoundary 触发
        }
    }

    /**
     * 状态切换（el-switch 的 @change 直接传 row 即可）
     *
     * 失败时自动回滚 row.status，避免 UI 与实际不一致。
     */
    async function handleStatusChange(row: any) {
        if (!updateStatusFn) {
            console.warn('[useListPage] handleStatusChange called but updateStatusFn not provided')
            return
        }
        try {
            await updateStatusFn(row.id, row.status)
            ElMessage.success(t('message.statusUpdateSuccess'))
        } catch {
            row.status = row.status === 1 ? 0 : 1
        }
    }

    if (immediate) {
        onMounted(() => {
            getList()
        })
    }

    return {
        list,
        loading,
        pagination,
        searchForm,
        getList,
        resetSearch,
        handleSearch,
        handlePageChange,
        handleSizeChange,
        handleDelete,
        handleBatchDelete,
        handleStatusChange
    }
}
