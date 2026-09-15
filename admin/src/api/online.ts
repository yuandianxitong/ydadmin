import type { PageQuery, PageResult } from '@/types/common'
import type { OnlineAdminInfo } from '@/types/system'
import { myRequest } from '@/utils/request'

/**
 * 在线管理员API（M4）
 */
export const onlineApi = {
    /** 在线管理员列表（按管理员聚合，只含当前数据范围内可见的管理员） */
    getList(params: PageQuery) {
        return myRequest.get<PageResult<OnlineAdminInfo>>('/adminapi/system/online', { params })
    },

    /** 强制下线：该管理员全部会话立即失效，返回被断开的连接数 */
    logout(adminId: number) {
        return myRequest.post<{ kicked: number }>(`/adminapi/system/online/${adminId}/logout`)
    }
}
