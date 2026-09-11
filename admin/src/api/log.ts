import type { PageResult } from '@/types/common'
import type { LoginLogInfo, LoginLogQuery, OperationLogInfo, OperationLogQuery } from '@/types/system'
import { myRequest } from '@/utils/request'

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
