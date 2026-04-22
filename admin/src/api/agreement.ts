import type { PageResult } from '@/types/common'
import type { AgreementInfo, AgreementQuery, AgreementReq } from '@/types/content'
import { myRequest } from '@/utils/request'

/**
 * 协议管理API
 */
export const agreementApi = {
    /** 获取协议列表 */
    getList(params?: AgreementQuery) {
        return myRequest.get<PageResult<AgreementInfo>>('/adminapi/agreement/list', {
            params
        })
    },

    /** 获取协议详情 */
    getDetail(id: number) {
        return myRequest.get<AgreementInfo>(`/adminapi/agreement/detail/${id}`)
    },

    /** 创建协议 */
    create(data: AgreementReq) {
        return myRequest.post<void>('/adminapi/agreement', data)
    },

    /** 更新协议 */
    update(id: number, data: Partial<AgreementReq>) {
        return myRequest.put<void>(`/adminapi/agreement/${id}`, data)
    },

    /** 删除协议 */
    delete(id: number) {
        return myRequest.delete<void>(`/adminapi/agreement/${id}`)
    }
}
