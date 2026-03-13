import type { PageQuery, PageResult, PermissionInfo, TreeOption } from '@/types/api'
import { myRequest } from '@/utils/request'

export interface PermissionReq {
    name: string
    title: string
    group: string
    description?: string
    guard_name?: string
}

export const permissionApi = {
    getPermissionList(params: PageQuery) {
        return myRequest.get<PageResult<PermissionInfo>>('/adminapi/system/permission', { params })
    },

    getPermissionTree() {
        return myRequest.get<TreeOption[]>('/adminapi/system/permission/tree')
    },

    createPermission(data: PermissionReq) {
        return myRequest.post<void>('/adminapi/system/permission', data)
    },

    updatePermission(id: number, data: Partial<PermissionReq>) {
        return myRequest.put<void>(`/adminapi/system/permission/${id}`, data)
    },

    deletePermission(id: number) {
        return myRequest.delete<void>(`/adminapi/system/permission/${id}`)
    }
}
