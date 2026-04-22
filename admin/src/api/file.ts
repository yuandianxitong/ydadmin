import type { PageResult } from '@/types/common'
import type { FileInfo, FileQuery } from '@/types/system'
import { myRequest } from '@/utils/request'

/**
 * 文件管理API
 */
export const fileApi = {
    /** 获取文件列表 */
    getList(params: FileQuery) {
        return myRequest.get<PageResult<FileInfo>>('/adminapi/system/file', { params })
    },

    /** 获取分组列表 */
    getGroups() {
        return myRequest.get<string[]>('/adminapi/system/file/groups')
    },

    /** 移动到分组 */
    moveGroup(ids: number[], group: string) {
        return myRequest.post<void>('/adminapi/system/file/move-group', { ids, group })
    },

    /** 重命名 */
    rename(id: number, name: string) {
        return myRequest.put<void>(`/adminapi/system/file/${id}/rename`, { name })
    },

    /** 删除文件 */
    delete(id: number) {
        return myRequest.delete<void>(`/adminapi/system/file/${id}`)
    },

    /** 批量删除 */
    batchDelete(ids: number[]) {
        return myRequest.post<void>('/adminapi/system/file/batch-delete', { ids })
    }
}
