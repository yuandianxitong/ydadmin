import type { ApiResponse, PageResult } from '@/types/common'
import { myRequest } from '@/utils/request'

/**
 * 创建一组标准 CRUD API
 *
 * 大部分管理后台模块的 API 文件都有完全相同的 `getList/getDetail/create/update/delete/updateStatus/batchDelete`
 * 结构，通过此工厂函数一行声明即可得到全部方法。
 *
 * 示例：
 * ```ts
 * // src/api/role.ts
 * import { createCrudApi } from '@/utils/createCrudApi'
 * import type { RoleInfo, RoleReq } from '@/types/system'
 *
 * export const roleApi = createCrudApi<RoleInfo, RoleReq>('/adminapi/system/role')
 * // 自动获得 getList, getDetail, create, update, delete, updateStatus, batchDelete
 * ```
 *
 * 若需要额外的非 CRUD 接口，在工厂返回对象上追加：
 * ```ts
 * export const roleApi = {
 *   ...createCrudApi<RoleInfo, RoleReq>('/adminapi/system/role'),
 *   getPermissions: (id: number) => myRequest.get(`/adminapi/system/role/${id}/permissions`),
 * }
 * ```
 *
 * @template T 列表项/详情类型
 * @template Req 创建/更新请求体类型
 * @param basePath 资源根路径（不含结尾斜杠），如 `/adminapi/system/role`
 */
export function createCrudApi<T = any, Req = Partial<T>>(basePath: string) {
    const base = basePath.replace(/\/$/, '')

    return {
        /** 列表查询 */
        getList(params?: Record<string, any>): Promise<ApiResponse<PageResult<T>>> {
            return myRequest.get<PageResult<T>>(base, { params })
        },

        /** 详情查询 */
        getDetail(id: number): Promise<ApiResponse<T>> {
            return myRequest.get<T>(`${base}/${id}`)
        },

        /** 创建 */
        create(data: Req): Promise<ApiResponse<T>> {
            return myRequest.post<T>(base, data)
        },

        /** 更新 */
        update(id: number, data: Req): Promise<ApiResponse<T>> {
            return myRequest.put<T>(`${base}/${id}`, data)
        },

        /** 删除单条 */
        delete(id: number): Promise<ApiResponse<void>> {
            return myRequest.delete<void>(`${base}/${id}`)
        },

        /** 更新状态（启用/禁用） */
        updateStatus(id: number, status: number): Promise<ApiResponse<void>> {
            return myRequest.put<void>(`${base}/${id}/status`, { status })
        },

        /** 批量删除 */
        batchDelete(ids: number[]): Promise<ApiResponse<void>> {
            return myRequest.post<void>(`${base}/batch-delete`, { ids })
        }
    }
}
