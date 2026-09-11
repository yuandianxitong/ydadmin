import { getI18n } from '@/locales/setupI18n'

/**
 * 全局翻译函数（可在非组件中使用，如 request.ts、feedback.ts）
 *
 * @param key 翻译 key
 * @param params 可选的插值参数
 */
export function t(key: string, params?: Record<string, any>): string {
    const i18n = getI18n()
    if (!i18n) return key
    return (i18n.global as any).t(key, params ?? {})
}

/**
 * 翻译路由/菜单标题
 * 优先使用 i18n 翻译（key = route.{routeName}），找不到则 fallback 到原始 title
 */
export function translateRouteTitle(title?: string, routeName?: string | symbol): string {
    if (!routeName || typeof routeName === 'symbol') {
        return title || ''
    }
    const i18n = getI18n()
    if (!i18n) return title || ''

    const key = `route.${routeName}`
    const { t, te } = i18n.global as any
    if (te(key)) {
        return t(key)
    }
    return title || ''
}
