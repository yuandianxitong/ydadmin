import type { LoginLogInfo, OperationLogInfo, PageQuery, PageResult } from '@/types/api'
import { myRequest } from '@/utils/request'

export interface LoginLogQuery extends PageQuery {
    username?: string
    login_result?: number
    start_date?: string
    end_date?: string
}

export interface OperationLogQuery extends PageQuery {
    admin_name?: string
    module?: string
    action?: string
    start_date?: string
    end_date?: string
}

export const logApi = {
    getLoginLogList(params: LoginLogQuery) {
        return myRequest.get<PageResult<LoginLogInfo>>('/adminapi/system/log/login', { params })
    },

    getOperationLogList(params: OperationLogQuery) {
        return myRequest.get<PageResult<OperationLogInfo>>('/adminapi/system/log/operation', {
            params
        })
    },

    deleteLoginLog(id: number) {
        return myRequest.delete<void>(`/adminapi/system/log/login/${id}`)
    },

    deleteOperationLog(id: number) {
        return myRequest.delete<void>(`/adminapi/system/log/operation/${id}`)
    },

    clearLoginLog() {
        return myRequest.post<void>('/adminapi/system/log/login/clear')
    },

    clearOperationLog() {
        return myRequest.post<void>('/adminapi/system/log/operation/clear')
    }
}
