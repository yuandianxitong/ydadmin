import type { PageResult } from '@/types/common'
import type { MessageLogInfo, MessageLogQuery, MessageTemplateInfo, MessageTemplateQuery, MessageTemplateReq, MessageTestSendReq } from '@/types/channel'
import { myRequest } from '@/utils/request'

// 消息模板
export const messageTemplateApi = {
    getList(params?: MessageTemplateQuery) {
        return myRequest.get<PageResult<MessageTemplateInfo>>('/adminapi/message/template', {
            params
        })
    },
    getDetail(id: number) {
        return myRequest.get<MessageTemplateInfo>(`/adminapi/message/template/${id}`)
    },
    create(data: MessageTemplateReq | Record<string, any>) {
        return myRequest.post<void>('/adminapi/message/template', data)
    },
    update(id: number, data: Partial<MessageTemplateReq> | Record<string, any>) {
        return myRequest.put<void>(`/adminapi/message/template/${id}`, data)
    },
    delete(id: number) {
        return myRequest.delete<void>(`/adminapi/message/template/${id}`)
    },
    testSend(data: MessageTestSendReq) {
        return myRequest.post<void>('/adminapi/message/template/test-send', data)
    }
}

// 消息发送记录
export const messageLogApi = {
    getList(params?: MessageLogQuery) {
        return myRequest.get<PageResult<MessageLogInfo>>('/adminapi/message/log', { params })
    }
}
