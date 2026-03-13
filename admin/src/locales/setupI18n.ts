import type { I18n } from 'vue-i18n'
import { createI18n } from 'vue-i18n'

import { LANG_KEY } from '@/constants/cache'
import cache from '@/utils/cache'

import enUS from './en-US'
import zhCN from './zh-CN'

export type LocaleType = 'zh-CN' | 'en-US'

export const localeOptions: { label: string; value: LocaleType }[] = [
    { label: '简体中文', value: 'zh-CN' },
    { label: 'English', value: 'en-US' }
]

let i18nInstance: I18n

export function setupI18n() {
    const savedLocale = cache.get(LANG_KEY) as LocaleType | null
    const locale = savedLocale || 'zh-CN'
    i18nInstance = createI18n({
        legacy: false,
        locale,
        messages: { 'zh-CN': zhCN, 'en-US': enUS }
    })
    // 同步后端语言cookie
    syncThinkLangCookie(locale)
    return i18nInstance
}

export function getI18n() {
    return i18nInstance
}

export function setLocale(locale: LocaleType) {
    const i18n = getI18n()
    if (i18n && i18n.global) {
        ;(i18n.global.locale as any).value = locale
        cache.set(LANG_KEY, locale)
        // 同步后端语言cookie
        syncThinkLangCookie(locale)
    }
}

/**
 * 同步ThinkPHP后端语言cookie
 * ThinkPHP的LoadLangPack中间件会读取think_lang cookie来确定语言
 */
function syncThinkLangCookie(locale: LocaleType) {
    const thinkLang = locale === 'zh-CN' ? 'zh-cn' : 'en'
    document.cookie = `think_lang=${thinkLang};path=/;max-age=${365 * 24 * 3600}`
}

export function getLocale(): LocaleType {
    const i18n = getI18n()
    if (i18n && i18n.global) {
        return (i18n.global.locale as any).value as LocaleType
    }
    return (cache.get(LANG_KEY) as LocaleType) || 'zh-CN'
}
