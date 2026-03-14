import { myRequest } from '@/utils/request'

export const articleApi = {
    getList(params?: any) {
        return myRequest.get('/adminapi/article/list', { params })
    },
    getDetail(id: number) {
        return myRequest.get(`/adminapi/article/detail/${id}`)
    },
    create(data: any) {
        return myRequest.post('/adminapi/article', data)
    },
    update(id: number, data: any) {
        return myRequest.put(`/adminapi/article/${id}`, data)
    },
    delete(id: number) {
        return myRequest.delete(`/adminapi/article/${id}`)
    },
    updateStatus(id: number, status: number) {
        return myRequest.put(`/adminapi/article/${id}/status`, { status })
    },
}
