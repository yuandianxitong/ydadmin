// src/constants/appSetting.ts

export const defaultSetting = {
    showCrumb: true, // 是否显示面包屑
    showLogo: true, // 是否显示 logo
    isUniqueOpened: false, // 只展开一个一级菜单
    sideWidth: 200, // 侧边栏宽度
    sideTheme: 'light', // 侧边栏主题
    openMultipleTabs: true, // 是否开启多标签 tab 栏
    layoutMode: 'classic' as 'classic' | 'sidebar',  // 布局模式
    themeMode: 'light' as 'system' | 'light' | 'dark', // 主题模式，默认亮色
    darkTheme: 'cinder', // 暗色主题配色名
    theme: '#1f4fff', // 主题色
    successTheme: '#67c23a',
    warningTheme: '#e6a23c',
    dangerTheme: '#f56c6c',
    errorTheme: '#f56c6c',
    infoTheme: '#909399' // 信息主题色
}

export const appConfig = {
    terminal: 1, // 终端标识
    title: '元点Admin', // 网站默认标题（运行时会被 i18n 覆盖）
    language: 'zh-cn', // 默认语言
    version: '1.0.0', // 版本号
    baseUrl: import.meta.env.VITE_APP_API_URL || '', // 后端 API 地址（留空 = 当前域名，跨域部署时填写后端域名）
    urlPrefix: 'adminapi', // 接口默认前缀
    timeout: 10 * 1000 // 请求超时时长（单位：毫秒）
}
