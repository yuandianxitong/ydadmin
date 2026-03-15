import type {
    AssignPermissionsReq,
    BatchDeleteReq,
    MenuInfo,
    PageResult,
    RoleInfo,
    RoleOption,
    RoleQuery,
    RoleReq,
    StatusReq
} from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * 角色管理API
 */
export const roleApi = {
    /**
     * 获取角色列表
     */
    getRoleList(params: RoleQuery) {
        return myRequest.get<PageResult<RoleInfo>>('/adminapi/system/role', { params })
    },

    /**
     * 获取角色详情
     */
    getRoleDetail(id: number) {
        return myRequest.get<RoleInfo>(`/adminapi/system/role/${id}`)
    },

    /**
     * 创建角色
     */
    createRole(data: RoleReq) {
        return myRequest.post<void>('/adminapi/system/role', data)
    },

    /**
     * 更新角色
     */
    updateRole(id: number, data: Partial<RoleReq>) {
        return myRequest.put<void>(`/adminapi/system/role/${id}`, data)
    },

    /**
     * 删除角色
     */
    deleteRole(id: number) {
        return myRequest.delete<void>(`/adminapi/system/role/${id}`)
    },

    /**
     * 更新角色状态
     */
    updateRoleStatus(id: number, data: StatusReq) {
        return myRequest.put<void>(`/adminapi/system/role/${id}/status`, data)
    },

    /**
     * 角色授权 - 分配权限
     */
    assignPermissions(id: number, data: AssignPermissionsReq) {
        return myRequest.put<void>(`/adminapi/system/role/${id}/assign-permissions`, data)
    },

    /**
     * 获取菜单权限树
     */
    getMenuTree() {
        return myRequest.get<MenuInfo[]>('/adminapi/system/role/menu/tree')
    },

    /**
     * 获取角色选项（用于下拉选择）
     */
    getRoleOptions() {
        return myRequest.get<RoleOption[]>('/adminapi/system/role/options')
    },

    /**
     * 获取角色已分配的权限
     */
    getRolePermissions(id: number) {
        return myRequest.get<{ menu_ids: number[] }>(`/adminapi/system/role/${id}/permissions`)
    },

    /**
     * 批量删除角色
     */
    batchDeleteRole(data: BatchDeleteReq) {
        return myRequest.post<void>('/adminapi/system/role/batch-delete', data)
    }
}
