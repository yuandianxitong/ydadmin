import { myRequest } from '@/utils/request'

export interface PluginInfo {
    name: string
    title: string
    version: string
    author: string
    description: string
    installed: boolean
    enabled: boolean
    installed_at: string | null
}

/**
 * 插件管理API
 */
export const pluginApi = {
    /** 获取插件列表 */
    getList() {
        return myRequest.get<PluginInfo[]>('/adminapi/system/plugin')
    },

    /** 安装插件 */
    install(name: string) {
        return myRequest.post<void>(`/adminapi/system/plugin/${name}/install`)
    },

    /** 卸载插件 */
    uninstall(name: string) {
        return myRequest.post<void>(`/adminapi/system/plugin/${name}/uninstall`)
    },

    /** 启用插件 */
    enable(name: string) {
        return myRequest.post<void>(`/adminapi/system/plugin/${name}/enable`)
    },

    /** 禁用插件 */
    disable(name: string) {
        return myRequest.post<void>(`/adminapi/system/plugin/${name}/disable`)
    },

    /** 上传插件 */
    upload(file: File) {
        const formData = new FormData()
        formData.append('file', file)
        return myRequest.post<PluginInfo>('/adminapi/system/plugin/upload', formData)
    },

    /** 删除插件 */
    delete(name: string) {
        return myRequest.delete<void>(`/adminapi/system/plugin/${name}`)
    }
}
