import { myRequest } from '@/utils/request'

/**
 * 实时通道 API（M4 spec §6）
 */
export const realtimeApi = {
    /** 换取一次性 WS 票据（30 秒有效，用一次即失效） */
    ticket() {
        return myRequest.post<{ ticket: string; expires_in: number }>('/adminapi/ws/ticket')
    }
}
