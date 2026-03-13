import type {
    BatchDeleteReq,
    MenuInfo,
    MenuQuery,
    MenuReq,
    StatusReq,
    TreeOption
} from '@/types/api'
import { myRequest } from '@/utils/request'

export interface MenuSortItem {
    id: number
    parent_id: number
    sort: number
}

/**
 * 菜单管理API
 */
export const menuApi = {
    /**
     * 获取菜单列表（树形结构）
     */
    getMenuList(params?: MenuQuery) {
        return myRequest.get<MenuInfo[]>('/adminapi/system/menu', { params })
    },

    /**
     * 获取菜单详情
     */
    getMenuDetail(id: number) {
        return myRequest.get<MenuInfo>(`/adminapi/system/menu/${id}`)
    },

    /**
     * 创建菜单
     */
    createMenu(data: MenuReq) {
        return myRequest.post<void>('/adminapi/system/menu', data)
    },

    /**
     * 更新菜单
     */
    updateMenu(id: number, data: Partial<MenuReq>) {
        return myRequest.put<void>(`/adminapi/system/menu/${id}`, data)
    },

    /**
     * 删除菜单
     */
    deleteMenu(id: number) {
        return myRequest.delete<void>(`/adminapi/system/menu/${id}`)
    },

    /**
     * 批量删除菜单
     */
    batchDeleteMenu(data: BatchDeleteReq) {
        return myRequest.post<void>('/adminapi/system/menu/batch-delete', data)
    },

    /**
     * 更新菜单状态
     */
    updateMenuStatus(id: number, data: StatusReq) {
        return myRequest.put<void>(`/adminapi/system/menu/${id}/status`, data)
    },

    /**
     * 获取菜单选项（用于父级菜单选择）
     */
    getMenuOptions(excludeId?: number) {
        const params = excludeId ? { exclude_id: excludeId } : undefined
        return myRequest.get<TreeOption[]>('/adminapi/system/menu/options', { params })
    },

    /**
     * 获取当前管理员的菜单路由
     */
    getAdminRoutes() {
        return myRequest.get<MenuInfo[]>('/adminapi/system/menu/routes')
    },

    /**
     * 批量更新排序
     */
    batchUpdateSort(items: MenuSortItem[]) {
        return myRequest.post<void>('/adminapi/system/menu/batch-sort', { items })
    }
}
