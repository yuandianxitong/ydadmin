import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { mobileConfigApi, type MobileConfig, type ThemeColors } from '@/api/mobile-config'

const DEFAULT_PRIMARY = '#2979ff'
const DEFAULT_DARK = '#1e5bb8'
const DEFAULT_PRICE = '#fa3534'
const DEFAULT_PAGE_BG = '#f5f5f5'
const DEFAULT_BUTTON_TEXT = '#ffffff'
const DEFAULT_BADGE = '#fa3534'

const DEFAULT_CONFIG: MobileConfig = {
    app_name: '',
    app_logo: '',
    theme_color: DEFAULT_PRIMARY,
    theme_colors: {},
    tabbar: [],
    tabbar_style: {},
    home_decoration: null,
}

export type ThemeCssVars = Record<string, string>

/** #RGB / #RRGGBB → #RRGGBBAA（约 10% 透明度），用于选中底等 */
function withAlpha(hex: string, alphaByte = '1a'): string {
    const h = (hex || '').trim()
    if (!/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(h)) {
        return `${DEFAULT_PRIMARY}${alphaByte}`
    }
    if (h.length === 4) {
        const r = h[1], g = h[2], b = h[3]
        return `#${r}${r}${g}${g}${b}${b}${alphaByte}`
    }
    if (h.length === 9) return h.slice(0, 7) + alphaByte
    return h + alphaByte
}

function buildThemeCssVars(colors: ThemeColors, primaryFallback: string): ThemeCssVars {
    const primary = colors.primary || primaryFallback || DEFAULT_PRIMARY
    return {
        '--yd-color-primary': primary,
        '--yd-color-primary-dark': colors.dark || primary || DEFAULT_DARK,
        '--yd-color-primary-soft': withAlpha(primary),
        '--yd-color-price': colors.price || DEFAULT_PRICE,
        '--yd-color-page-bg': colors.page_bg || DEFAULT_PAGE_BG,
        '--yd-color-button-text': colors.button_text || DEFAULT_BUTTON_TEXT,
        '--yd-color-badge': colors.badge || DEFAULT_BADGE,
    }
}

function applyThemeToDocument(vars: ThemeCssVars) {
    // #ifdef H5
    try {
        const root = document.documentElement
        Object.entries(vars).forEach(([k, v]) => {
            if (v) root.style.setProperty(k, v)
        })
    } catch {
        /* ignore */
    }
    // #endif
}

function applyUviewPrimary(primary: string) {
    try {
        const u = (uni as any).$u
        if (u?.setConfig) {
            u.setConfig({ color: { primary } })
        } else if (u?.color) {
            u.color.primary = primary
        }
    } catch {
        /* uView 未就绪时忽略 */
    }
}

export const useMobileConfigStore = defineStore('mobile-config', () => {
    const config = ref<MobileConfig>({ ...DEFAULT_CONFIG })
    const loaded = ref(false)
    const themeCssVars = ref<ThemeCssVars>(buildThemeCssVars({}, DEFAULT_PRIMARY))
    let inflight: Promise<MobileConfig> | null = null

    function syncTheme() {
        const vars = buildThemeCssVars(config.value.theme_colors || {}, config.value.theme_color)
        themeCssVars.value = vars
        applyThemeToDocument(vars)
        applyUviewPrimary(vars['--yd-color-primary'] || DEFAULT_PRIMARY)
        applyNavigationBarTheme()
    }

    /** 同步原生导航栏背景为主色（自定义导航页可能失败，忽略即可） */
    function applyNavigationBarTheme() {
        const primary = themeCssVars.value['--yd-color-primary'] || themeColor.value
        try {
            uni.setNavigationBarColor({
                frontColor: '#ffffff',
                backgroundColor: primary,
                fail: () => {},
            })
        } catch {
            /* ignore */
        }
    }

    async function load(force = false): Promise<MobileConfig> {
        if (loaded.value && !force) {
            syncTheme()
            return config.value
        }
        if (inflight) return inflight
        inflight = mobileConfigApi
            .get()
            .then((data: MobileConfig) => {
                config.value = {
                    ...DEFAULT_CONFIG,
                    ...data,
                    tabbar: Array.isArray(data?.tabbar) ? data.tabbar : [],
                }
                loaded.value = true
                syncTheme()
                return config.value
            })
            .catch((err) => {
                console.warn('[mobile-config] load failed', err)
                loaded.value = true
                syncTheme()
                return config.value
            })
            .finally(() => {
                inflight = null
            })
        return inflight
    }

    function reset() {
        config.value = { ...DEFAULT_CONFIG }
        loaded.value = false
        inflight = null
        themeCssVars.value = buildThemeCssVars({}, DEFAULT_PRIMARY)
    }

    const themeColor = computed(
        () => config.value.theme_colors?.primary || config.value.theme_color || DEFAULT_PRIMARY,
    )
    const appName = computed(() => config.value.app_name)
    const appLogo = computed(() => config.value.app_logo)
    const tabbar = computed(() => config.value.tabbar)
    const themeColors = computed(() => config.value.theme_colors || {})
    const tabbarStyle = computed(() => config.value.tabbar_style || {})
    const shareTitle = computed(() => config.value.share_title || config.value.app_name || '')
    const shareImage = computed(() => config.value.share_image || config.value.app_logo || '')
    const serviceType = computed(() => config.value.service_type || '')
    const servicePhone = computed(() => config.value.service_phone || '')

    return {
        config,
        loaded,
        load,
        reset,
        themeCssVars,
        syncTheme,
        applyNavigationBarTheme,
        themeColor,
        appName,
        appLogo,
        tabbar,
        themeColors,
        tabbarStyle,
        shareTitle,
        shareImage,
        serviceType,
        servicePhone,
    }
})
