import { myRequest } from '@/utils/request'

const slow = { timeout: 90000 }

export interface PluginCategoryItem {
    key: string
    label: string
    sort: number
}

export interface PluginCard {
    code: string
    name: string
    version: string
    description: string
    icon: string
    kind: string
    category: string
    status: number
    last_error: string
}

export interface PluginCatalog {
    categories: PluginCategoryItem[]
    list: PluginCard[]
}

export const pluginApi = {
    catalog() {
        return myRequest.get<PluginCatalog>('/adminapi/plugin/list')
    },

    install(code: string) {
        return myRequest.post('/adminapi/plugin/install', { code }, slow)
    },

    installZip(file: File) {
        const form = new FormData()
        form.append('file', file)
        return myRequest.post('/adminapi/plugin/install', form, {
            timeout: 90000,
            headers: { 'Content-Type': 'multipart/form-data' }
        })
    },

    enable(code: string) {
        return myRequest.post('/adminapi/plugin/enable', { code }, slow)
    },

    disable(code: string) {
        return myRequest.post('/adminapi/plugin/disable', { code }, slow)
    },

    upgrade(code: string, file: File) {
        const form = new FormData()
        form.append('code', code)
        form.append('file', file)
        return myRequest.post('/adminapi/plugin/upgrade', form, {
            timeout: 90000,
            headers: { 'Content-Type': 'multipart/form-data' }
        })
    },

    uninstall(code: string) {
        return myRequest.post('/adminapi/plugin/uninstall', { code }, slow)
    },

    purge(code: string) {
        return myRequest.post('/adminapi/plugin/purge', { code }, slow)
    }
}
