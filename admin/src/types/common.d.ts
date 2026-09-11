// API响应通用类型
export interface ApiResponse<T = any> {
    code: number
    message: string
    data: T
    timestamp: number
}

// 分页请求参数
export interface PageQuery {
    page?: number
    limit?: number
    keyword?: string
}

// 分页响应数据
export interface PageResult<T> {
    list: T[]
    pagination: {
        current_page: number
        per_page: number
        total: number
        last_page: number
    }
}

// 修改密码
export interface ChangePasswordReq {
    old_password: string
    new_password: string
}

// 重置密码
export interface ResetPasswordReq {
    password: string
}

// 状态更新
export interface StatusReq {
    status: number
}

// 批量删除
export interface BatchDeleteReq {
    ids: number[]
}

// 通用选项类型
export interface SelectOption {
    label: string
    value: string | number
}

export interface TreeOption {
    id: number
    title: string
    children?: TreeOption[]
}
