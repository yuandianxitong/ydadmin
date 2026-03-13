// src/router/guards/auth.guard.ts

import type { Router } from 'vue-router'

import useAppStore from '@/store/modules/app.store'

// 第一次加载过后端配置，设置 favicon 等
export default function createInitGuard(router: Router): void {
    router.beforeEach(async (to, from, next) => {
        const appStore = useAppStore()

        // 只在非登录页面且没有配置且没有在加载中时才获取配置，避免循环
        if (
            to.path !== '/login' &&
            Object.keys(appStore.config).length === 0 &&
            !appStore.isLoadingConfig
        ) {
            try {
                const data: any = await appStore.getConfig()

                // 如果后端返回了 web_favicon，就更新页面 favicon
                if (data.web_favicon) {
                    const faviconUrl = appStore.getImageUrl(data.web_favicon)
                    let favicon = document.querySelector(
                        'link[rel="icon"]'
                    ) as HTMLLinkElement | null
                    if (favicon) {
                        favicon.href = faviconUrl
                    } else {
                        favicon = document.createElement('link')
                        favicon.rel = 'icon'
                        favicon.href = faviconUrl
                        document.head.appendChild(favicon)
                    }
                }
            } catch (error) {
                console.error('获取系统全局配置失败：', error)
                // 如果获取配置失败，可能是认证问题，直接跳过
            }
        }

        next()
    })
}
