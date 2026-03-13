import type { AdminInfo, CaptchaRes, ChangePasswordReq, LoginReq, LoginRes } from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * 认证相关API
 */
export const authApi = {
    /**
     * 获取验证码
     */
    getCaptcha() {
        return myRequest.get<CaptchaRes>('/adminapi/auth/captcha')
    },

    /**
     * 管理员登录
     */
    login(data: LoginReq) {
        return myRequest.post<LoginRes>('/adminapi/auth/login', data)
    },

    /**
     * 获取当前管理员信息
     */
    getAdminInfo() {
        return myRequest.get<AdminInfo>('/adminapi/auth/info')
    },

    /**
     * 刷新Token
     */
    refreshToken() {
        return myRequest.post<{ token: string }>('/adminapi/auth/refresh')
    },

    /**
     * 退出登录
     */
    logout() {
        return myRequest.post('/adminapi/auth/logout')
    },

    /**
     * 修改密码
     */
    changePassword(data: ChangePasswordReq) {
        return myRequest.put('/adminapi/system/admin/change-password', data)
    }
}
