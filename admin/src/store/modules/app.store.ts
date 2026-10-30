// src/modules/app/app.store.ts

import { defineStore } from 'pinia'
import { nextTick } from 'vue'

import { getConfig } from '@/api/app'
import { resolveImageUrl } from '@/utils/image-url'

/**
 * AppStore 用于管理全局配置、移动端标识、侧边栏折叠状态等。
 */
export interface AppState {
    config: Record<string, any>
    isMobile: boolean
    isCollapsed: boolean
    isRouteShow: boolean
    isLoadingConfig: boolean
}

export const useAppStore = defineStore('app', {
    state: (): AppState => ({
        config: {},
        isMobile: true,
        isCollapsed: false,
        isRouteShow: true,
        isLoadingConfig: false
    }),
    actions: {
        /** 统一解析服务器素材、本地上传及云存储图片的显示地址。 */
        getImageUrl(url: string): string {
            return resolveImageUrl(url, {
                development: import.meta.env.DEV,
                origin: window.location.origin,
                apiBaseUrl: import.meta.env.VITE_APP_API_URL,
                storageDomain: this.config.storage_oss_domain,
                siteUrl: this.config.site_url
            })
        },

        /** 从后端拉取配置，将结果赋给 this.config */
        async getConfig(): Promise<Record<string, any>> {
            // 如果正在加载配置，则等待
            if (this.isLoadingConfig) {
                return new Promise((resolve) => {
                    const checkLoading = () => {
                        if (!this.isLoadingConfig) {
                            resolve(this.config)
                        } else {
                            setTimeout(checkLoading, 100)
                        }
                    }
                    checkLoading()
                })
            }

            // 如果已经有配置，直接返回
            if (Object.keys(this.config).length > 0) {
                return this.config
            }

            try {
                this.isLoadingConfig = true
                const data = await getConfig()
                this.config = data.data
                return this.config
            } catch (error) {
                return Promise.reject(error)
            } finally {
                this.isLoadingConfig = false
            }
        },

        /** 设置是否是移动端 */
        setMobile(value: boolean): void {
            this.isMobile = value
        },

        /** 折叠/展开侧边栏，如果传入 toggle 参数则按该值，否则切换当前 state */
        toggleCollapsed(toggle?: boolean): void {
            this.isCollapsed = toggle !== undefined ? toggle : !this.isCollapsed
        },

        /** 重新渲染路由视图，常用于刷新当前页面 */
        refreshView(): void {
            this.isRouteShow = false
            nextTick(() => {
                this.isRouteShow = true
            })
        }
    }
})

export default useAppStore
