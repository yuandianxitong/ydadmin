/** 通用 API 响应 */
export interface ApiResponse<T = any> {
  code: number
  message: string
  data: T
  timestamp: number
}

/** 分页结果 */
export interface PageResult<T = any> {
  list: T[]
  pagination: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}

/** 用户信息 */
export interface UserInfo {
  id: number
  nickname: string
  avatar: string
  mobile: string
  gender: number
  birthday: string
}

/** 登录响应 */
export interface LoginResult {
  token: string
  user: UserInfo
}
