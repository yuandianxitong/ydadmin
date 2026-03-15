// src/modules/app/app.store.ts

import { defineStore } from 'pinia'
import { nextTick } from 'vue'

import { getConfig } from '@/api/app'

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
        /** 拼接图片完整地址，如果 URL 不是以 http 开头，则自动加上正确的域名前缀 */
        getImageUrl(url: string): string {
            if (!url) return ''

            // 如果已经是完整URL，直接返回
            if (url.startsWith('http')) {
                return url
            }

            // 开发环境：直接返回相对路径，由 Vite proxy 转发到后端
            if (import.meta.env.DEV) {
                return url
            }

            // 生产环境：按优先级拼接域名
            // 1. OSS 域名（后台配置）
            if (this.config.oss_domain) {
                return `${this.config.oss_domain}${url}`
            }
            // 2. 站点域名（后台配置）
            if (this.config.site_url) {
                return `${this.config.site_url}${url}`
            }
            // 3. 环境变量 API 域名（跨域部署时配置）
            const apiUrl = import.meta.env.VITE_APP_API_URL
            if (apiUrl) {
                return `${apiUrl}${url}`
            }
            // 4. 当前访问域名（同域部署）
            return `${window.location.origin}${url}`
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
                return data
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
