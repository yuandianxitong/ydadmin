import type { DepartmentInfo, DepartmentReq, TreeOption } from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * 部门管理API
 */
export const departmentApi = {
    /** 获取部门树形列表 */
    getTree(params?: { keyword?: string; status?: number }) {
        return myRequest.get<DepartmentInfo[]>('/adminapi/system/department', { params })
    },

    /** 获取部门详情 */
    getDetail(id: number) {
        return myRequest.get<DepartmentInfo>(`/adminapi/system/department/${id}`)
    },

    /** 创建部门 */
    create(data: DepartmentReq) {
        return myRequest.post<void>('/adminapi/system/department', data)
    },

    /** 更新部门 */
    update(id: number, data: Partial<DepartmentReq>) {
        return myRequest.put<void>(`/adminapi/system/department/${id}`, data)
    },

    /** 删除部门 */
    delete(id: number) {
        return myRequest.delete<void>(`/adminapi/system/department/${id}`)
    },

    /** 更新状态 */
    updateStatus(id: number, status: number) {
        return myRequest.put<void>(`/adminapi/system/department/${id}/status`, { status })
    },

    /** 获取部门选项树 */
    getOptions() {
        return myRequest.get<TreeOption[]>('/adminapi/system/department/options')
    }
}
