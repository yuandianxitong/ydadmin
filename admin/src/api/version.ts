import type { PageResult } from '@/types/common'
import type { AppVersionInfo, AppVersionQuery, AppVersionReq } from '@/types/user'
import { myRequest } from '@/utils/request'

/**
 * 版本管理API
 */
export const versionApi = {
    /** 获取版本列表 */
    getList(params?: AppVersionQuery) {
        return myRequest.get<PageResult<AppVersionInfo>>('/adminapi/version/list', { params })
    },

    /** 获取版本详情 */
    getDetail(id: number) {
        return myRequest.get<AppVersionInfo>(`/adminapi/version/detail/${id}`)
    },

    /** 新增版本 */
    create(data: AppVersionReq) {
        return myRequest.post<void>('/adminapi/version', data)
    },

    /** 更新版本 */
    update(id: number, data: Partial<AppVersionReq>) {
        return myRequest.put<void>(`/adminapi/version/${id}`, data)
    },

    /** 删除版本 */
    delete(id: number) {
        return myRequest.delete<void>(`/adminapi/version/${id}`)
    }
}
