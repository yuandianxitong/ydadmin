import { myRequest } from '@/utils/request'

export interface DiyComponent {
    id: string
    type: string
    props: Record<string, any>
    hidden?: boolean
}

export interface DiyHome {
    components: DiyComponent[]
    page_settings: Record<string, any>
}

export interface DiyVersion {
    id: number
    version_no: number
    created_at: string
    note?: string
}

export interface DiyHomeSummary {
    title: string
    published: boolean
    component_count: number
    updated_at: string | null
}

export interface DiyPageListItem {
    id: number
    page_key: string
    title: string
    status: number
    updated_at: string
    component_count: number
    published: boolean
}

export interface PluginWidgetField {
    key: string
    label: string
    type:
        | 'number'
        | 'text'
        | 'select'
        | 'radio'
        | 'link'
        | 'image'
        | 'color'
        | 'switch'
        | 'api-select'
        | 'api-multi-select'
    options?: Array<{ label: string; value: any }>
    options_url?: string
    show_if?: { key: string; value: any }
}
export interface PluginWidgetMeta {
    type: string
    label: string
    render: string
    /** 插件 code（渲染器协议 v1：宿主按 plugin + renderer.tenant 解析插件自带预览渲染器） */
    plugin: string
    /** manifest diy_widgets[].renderer 声明（protocol + 各端裸组件名），未声明为 null */
    renderer?: { protocol?: number; tenant?: string; uniapp?: string; pc?: string } | null
    /** 面板图标 URL（widget 声明 → 插件 logo → '' 三级回退，'' 时前端用占位图标） */
    icon_url?: string
    default_props: Record<string, any>
    props_schema: PluginWidgetField[]
    pages?: string[]
}

export interface MemberStatOption {
    key: string
    label: string
    plugin?: string
}

export interface DiyWidgetCatalog {
    builtins: string[]
    plugins: PluginWidgetMeta[]
    member_stats: MemberStatOption[]
}

// home 走专用端点；member / 自定义页走 /pages/:key
function draftUrl(key: string) {
    return key === 'home' ? '/adminapi/diy/home' : `/adminapi/diy/pages/${encodeURIComponent(key)}/draft`
}
function publishUrl(key: string) {
    return key === 'home'
        ? '/adminapi/diy/home/publish'
        : `/adminapi/diy/pages/${encodeURIComponent(key)}/publish`
}
function versionsUrl(key: string) {
    return key === 'home'
        ? '/adminapi/diy/home/versions'
        : `/adminapi/diy/pages/${encodeURIComponent(key)}/versions`
}
function restoreUrl(key: string, id: number) {
    return key === 'home'
        ? `/adminapi/diy/home/versions/${id}/restore`
        : `/adminapi/diy/pages/${encodeURIComponent(key)}/versions/${id}/restore`
}

export interface CatalogLink {
    label: string
    path: string
    category: string
    source: 'builtin' | 'custom-page' | 'plugin' | 'plugin-link' | 'library'
    params_schema: Array<{ key: string; label: string; type: string; required?: boolean }>
    external: boolean
}

export interface DiyLinkItem {
    id: number
    label: string
    path: string
    category: string
    icon?: string | null
    sort: number
    status: number
}

export const diyApi = {
    getHome() {
        return myRequest.get<DiyHome>('/adminapi/diy/home')
    },
    /** 页面装修列表：home 状态摘要 */
    getHomeSummary() {
        return myRequest.get<DiyHomeSummary>('/adminapi/diy/home/summary')
    },
    /** 系统页状态摘要（home/member） */
    getPageSummary(key: string) {
        return myRequest.get<DiyHomeSummary>(`/adminapi/diy/pages/${key}/summary`)
    },
    saveHome(data: DiyHome) {
        return myRequest.put('/adminapi/diy/home', data)
    },
    publishHome() {
        return myRequest.post('/adminapi/diy/home/publish')
    },
    listVersions() {
        return myRequest.get<DiyVersion[]>('/adminapi/diy/home/versions')
    },
    restoreVersion(id: number) {
        return myRequest.post(`/adminapi/diy/home/versions/${id}/restore`)
    },
    // 按 key 的草稿/发布/版本（home 或自定义页通用）
    getPageDraft(key: string) {
        return myRequest.get<DiyHome>(draftUrl(key))
    },
    savePageDraft(key: string, data: DiyHome) {
        return myRequest.put(draftUrl(key), data)
    },
    publishPage(key: string) {
        return myRequest.post(publishUrl(key))
    },
    listPageVersions(key: string) {
        return myRequest.get<DiyVersion[]>(versionsUrl(key))
    },
    restorePageVersion(key: string, id: number) {
        return myRequest.post(restoreUrl(key, id))
    },
    // 页面管理
    listPages(params?: { page?: number; limit?: number; keyword?: string; published?: 0 | 1 | '' }) {
        return myRequest.get<{ list: DiyPageListItem[]; total: number }>('/adminapi/diy/pages', {
            params
        })
    },
    /** 复制自定义页（副本恒为未发布草稿，标识自动生成 <源>-copyN） */
    copyPage(id: number) {
        return myRequest.post<{ id: number }>(`/adminapi/diy/pages/${id}/copy`)
    },
    createPage(data: { title: string; page_key: string }) {
        return myRequest.post<{ id: number }>('/adminapi/diy/pages', data)
    },
    updatePage(id: number, data: { title?: string; page_key?: string; status?: number }) {
        return myRequest.put(`/adminapi/diy/pages/${id}`, data)
    },
    deletePage(id: number) {
        return myRequest.delete(`/adminapi/diy/pages/${id}`)
    },
    getWidgets() {
        return myRequest.get<DiyWidgetCatalog>('/adminapi/diy/widgets')
    },
    /** 编辑器画布预览注水：单组件跑后端 hydrator 返回真实数据 props（与 C 端下发同一份注水逻辑） */
    previewWidget(type: string, props: Record<string, any>) {
        return myRequest.post<{ props: Record<string, any> }>('/adminapi/diy/widget-preview', {
            type,
            props
        })
    },
    getLinkCatalog() {
        return myRequest.get<{ links: CatalogLink[] }>('/adminapi/diy/link-catalog')
    },
    listLinks() {
        return myRequest.get<DiyLinkItem[]>('/adminapi/diy/links')
    },
    createLink(data: Partial<DiyLinkItem>) {
        return myRequest.post<{ id: number }>('/adminapi/diy/links', data)
    },
    updateLink(id: number, data: Partial<DiyLinkItem>) {
        return myRequest.put(`/adminapi/diy/links/${id}`, data)
    },
    deleteLink(id: number) {
        return myRequest.delete(`/adminapi/diy/links/${id}`)
    }
}
