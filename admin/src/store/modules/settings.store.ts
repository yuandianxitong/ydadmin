// src/modules/setting/setting.store.ts

// eslint-disable-next-line vue/prefer-import-from-vue
import { isObject } from '@vue/shared'
import { defineStore } from 'pinia'

// 全局配置常量
import { defaultSetting } from '@/constants'
// 枚举：缓存键名称，请确保有该枚举文件
import { SETTING_KEY } from '@/constants/cache'
// 通用缓存工具
import cache from '@/utils/cache'
// 主题切换工具
import { applyDarkClass, applyDarkTheme, setTheme } from '@/utils/theme'

export interface SettingState {
    showDrawer: boolean
    // 以下字段源自 defaultSetting
    showCrumb: boolean
    showLogo: boolean
    isUniqueOpened: boolean
    sideWidth: number
    sideTheme: string
    sideDarkColor: string
    openMultipleTabs: boolean
    themeMode: 'system' | 'light' | 'dark'
    darkTheme: string
    theme: string
    successTheme: string
    warningTheme: string
    dangerTheme: string
    errorTheme: string
    infoTheme: string
    _mediaCleanup: (() => void) | null
}

export const useSettingStore = defineStore('setting', {
    state: (): SettingState => {
        // 从缓存中读取上次保存的设置
        const storageSetting = cache.get(SETTING_KEY)

        // 以 defaultSetting 中所有键作为初始值
        const initialState: any = {
            showDrawer: false,
            _mediaCleanup: null as (() => void) | null,
            ...defaultSetting
        }

        // 如果缓存中是一个对象，则把缓存值覆盖到 initialState
        if (isObject(storageSetting)) {
            Object.assign(initialState, storageSetting)
        }

        return initialState as SettingState
    },
    actions: {
        /** 修改单个配置项并同步到缓存 */
        setSetting(data: { key: keyof SettingState; value: any }): void {
            const { key, value } = data
            if (Object.prototype.hasOwnProperty.call(this, key)) {
                // @ts-ignore
                this[key] = value
            }
            // 把除了 showDrawer / _mediaCleanup 外的设置保存到缓存中
            const settings: Partial<SettingState> = { ...this.$state }
            delete (settings as any).showDrawer
            delete (settings as any)._mediaCleanup
            cache.set(SETTING_KEY, settings)
        },

        /** 切换深色/浅色主题 */
        setTheme(isDark: boolean): void {
            setTheme(
                {
                    primary: this.theme,
                    success: this.successTheme,
                    warning: this.warningTheme,
                    danger: this.dangerTheme,
                    error: this.errorTheme,
                    info: this.infoTheme
                },
                isDark
            )
        },

        /** 应用主题模式（system/light/dark），统一入口 */
        applyThemeMode(): void {
            // 清除之前的系统监听器
            if (this._mediaCleanup) {
                this._mediaCleanup()
                this._mediaCleanup = null
            }

            const apply = (isDark: boolean) => {
                applyDarkClass(isDark)
                if (isDark) {
                    applyDarkTheme(this.darkTheme)
                } else {
                    applyDarkTheme('')
                }
                this.setTheme(isDark)
            }

            if (this.themeMode === 'dark') {
                apply(true)
            } else if (this.themeMode === 'light') {
                apply(false)
            } else {
                // system mode
                const mq = window.matchMedia('(prefers-color-scheme: dark)')
                apply(mq.matches)
                const handler = (e: MediaQueryListEvent) => apply(e.matches)
                mq.addEventListener('change', handler)
                this._mediaCleanup = () => mq.removeEventListener('change', handler)
            }
        },

        /** 恢复默认主题（删除缓存并重置状态） */
        resetTheme(): void {
            // 把 defaultSetting 的所有键值覆盖回 this
            for (const key in defaultSetting) {
                // @ts-ignore
                this[key as keyof SettingState] = (defaultSetting as any)[key]
            }
            cache.remove(SETTING_KEY)
            this.applyThemeMode()
        }
    }
})

export default useSettingStore
