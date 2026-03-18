import type { PageResult } from '@/types/api'
import { myRequest } from '@/utils/request'

export interface UserItem {
    id: number
    nickname: string
    avatar: string
    mobile: string
    balance: string
    points: number
    status: number
    last_login_ip: string
    last_login_time: string
    login_count: number
    created_at: string
}

export interface BalanceLogItem {
    id: number
    user_id: number
    user_nickname: string
    amount: string
    before_balance: string
    after_balance: string
    type: number
    type_text: string
    source: string
    remark: string
    operator_id: number | null
    operator_name: string | null
    created_at: string
}

export interface PointsLogItem {
    id: number
    user_id: number
    user_nickname: string
    points: number
    before_points: number
    after_points: number
    type: number
    type_text: string
    source: string
    remark: string
    operator_id: number | null
    operator_name: string | null
    created_at: string
}

/**
 * 用户管理API
 */
export const userManageApi = {
    /**
     * 获取用户列表
     */
    getUserList(params: any) {
        return myRequest.get<PageResult<UserItem>>('/adminapi/user/list', { params })
    },

    /**
     * 获取用户详情
     */
    getUserDetail(id: number) {
        return myRequest.get<UserItem>(`/adminapi/user/detail/${id}`)
    },

    /**
     * 调整余额
     */
    adjustBalance(data: { user_id: number; amount: number; remark: string }) {
        return myRequest.post<void>('/adminapi/user/adjust-balance', data)
    },

    /**
     * 调整积分
     */
    adjustPoints(data: { user_id: number; points: number; remark: string }) {
        return myRequest.post<void>('/adminapi/user/adjust-points', data)
    },

    /**
     * 更新用户状态
     */
    updateStatus(id: number, data: { status: number }) {
        return myRequest.put<void>(`/adminapi/user/${id}/status`, data)
    },

    /**
     * 获取余额记录
     */
    getBalanceLogs(params: any) {
        return myRequest.get<PageResult<BalanceLogItem>>('/adminapi/user/balance-logs', { params })
    },

    /**
     * 获取积分记录
     */
    getPointsLogs(params: any) {
        return myRequest.get<PageResult<PointsLogItem>>('/adminapi/user/points-logs', { params })
    }
}
