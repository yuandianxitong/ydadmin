import type { PageResult, SelectOption } from '@/types/common'
import type { DictionaryInfo, DictionaryItemInfo, DictionaryItemReq, DictionaryQuery, DictionaryReq } from '@/types/system'
import { myRequest } from '@/utils/request'

/**
 * 数据字典API
 */
export const dictionaryApi = {
    // ========== 字典 ==========

    /** 获取字典列表 */
    getList(params: DictionaryQuery) {
        return myRequest.get<PageResult<DictionaryInfo>>('/adminapi/system/dictionary', { params })
    },

    /** 获取字典详情（含字典项） */
    getDetail(id: number) {
        return myRequest.get<DictionaryInfo & { items: DictionaryItemInfo[] }>(
            `/adminapi/system/dictionary/${id}`
        )
    },

    /** 创建字典 */
    create(data: DictionaryReq) {
        return myRequest.post<void>('/adminapi/system/dictionary', data)
    },

    /** 更新字典 */
    update(id: number, data: Partial<DictionaryReq>) {
        return myRequest.put<void>(`/adminapi/system/dictionary/${id}`, data)
    },

    /** 删除字典 */
    delete(id: number) {
        return myRequest.delete<void>(`/adminapi/system/dictionary/${id}`)
    },

    /** 批量删除字典 */
    batchDelete(ids: number[]) {
        return myRequest.post<void>('/adminapi/system/dictionary/batch-delete', { ids })
    },

    /** 根据 code 获取字典选项 */
    getOptions(code: string) {
        return myRequest.get<SelectOption[]>('/adminapi/system/dictionary/options', {
            params: { code }
        })
    },

    /** 批量获取字典选项 */
    getBatchOptions(codes: string[]) {
        return myRequest.get<Record<string, SelectOption[]>>(
            '/adminapi/system/dictionary/batch-options',
            { params: { codes: codes.join(',') } }
        )
    },

    // ========== 字典项 ==========

    /** 获取字典项列表 */
    getItems(dictionaryId: number) {
        return myRequest.get<DictionaryItemInfo[] | { list: DictionaryItemInfo[] }>(
            `/adminapi/system/dictionary/${dictionaryId}/items`
        )
    },

    /** 创建字典项 */
    createItem(data: DictionaryItemReq) {
        return myRequest.post<void>('/adminapi/system/dictionary/item', data)
    },

    /** 更新字典项 */
    updateItem(id: number, data: Partial<Omit<DictionaryItemReq, 'dictionary_id'>>) {
        return myRequest.put<void>(`/adminapi/system/dictionary/item/${id}`, data)
    },

    /** 删除字典项 */
    deleteItem(id: number) {
        return myRequest.delete<void>(`/adminapi/system/dictionary/item/${id}`)
    }
}
