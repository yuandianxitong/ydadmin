import http from '@/utils/request'

export interface MobileTabbarItem {
    code: string
    path: string
    text: string
    icon?: string
    selected_icon?: string
    sel_label?: string
    badge?: string
}

export interface ThemeColors {
    primary?: string
    dark?: string
    price?: string
    page_bg?: string
    button_text?: string
    badge?: string
}

export interface TabbarStyle {
    text_color?: string
    active_color?: string
    bg_color?: string
}

export interface MobileConfig {
    app_name: string
    app_logo: string
    app_intro?: string
    theme_color: string
    theme_colors?: ThemeColors
    service_type?: '' | 'online' | 'wechat' | 'phone'
    service_phone?: string
    share_title?: string
    share_image?: string
    tabbar: MobileTabbarItem[]
    tabbar_style?: TabbarStyle
    home_decoration?: {
        components: Array<{ id: string; type: string; props: Record<string, any> }>
        page_settings?: Record<string, any>
    } | null
}

export interface DiyPagePayload {
    title?: string
    components: Array<{ id: string; type: string; props: Record<string, any> }>
    page_settings?: Record<string, any>
}

export const mobileConfigApi = {
    get: () => http.get<MobileConfig>('/api/mobile/config'),
    getDiyPage: (key: string) => http.get<DiyPagePayload>('/api/mobile/diy-page', { key }),
}
