import { myRequest } from '@/utils/request'

export const articleCategoryApi = {
    getList(params?: any) {
        return myRequest.get('/adminapi/article-category/list', { params })
    },
    getOptions(excludeId?: number) {
        return myRequest.get('/adminapi/article-category/options', {
            params: { exclude_id: excludeId }
        })
    },
    create(data: any) {
        return myRequest.post('/adminapi/article-category', data)
    },
    update(id: number, data: any) {
        return myRequest.put(`/adminapi/article-category/${id}`, data)
    },
    delete(id: number) {
        return myRequest.delete(`/adminapi/article-category/${id}`)
    },
    updateStatus(id: number, status: number) {
        return myRequest.put(`/adminapi/article-category/${id}/status`, { status })
    }
}
