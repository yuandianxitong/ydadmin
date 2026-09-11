import { myRequest } from '@/utils/request'

/**
 * 移动端配置 API。
 *
 *   GET  /adminapi/mobile/config           当前租户的移动端配置
 *   PUT  /adminapi/mobile/config           保存
 *   GET  /adminapi/mobile/config/eligible  可作为首页 / tabBar 的插件清单
 */

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
    home_app_code: string
    home_page: string
    tabbar: MobileTabbarItem[]
    tabbar_style?: TabbarStyle
    wechat_appid?: string
    status?: number
}

export interface EligiblePluginEntry {
    code: string
    name: string
    kind: 'app' | 'plugin'
    subpackage: string
    pages: { path: string; title: string }[]
    default_home_path: string
}

export interface EligibilityResponse {
    homeOptions: EligiblePluginEntry[]
    tabBarOptions: EligiblePluginEntry[]
}

export const mobileConfigApi = {
    get() {
        return myRequest.get<MobileConfig>('/adminapi/mobile/config')
    },
    update(data: Partial<MobileConfig>) {
        return myRequest.put<MobileConfig>('/adminapi/mobile/config', data)
    },
    eligible() {
        return myRequest.get<EligibilityResponse>('/adminapi/mobile/config/eligible')
    },
}
