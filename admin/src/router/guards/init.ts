// src/router/guards/auth.guard.ts

import type { Router } from 'vue-router'

import useAppStore from '@/store/modules/app.store'

export default function createInitGuard(router: Router) {
    router.beforeEach(async (to, from, next) => {
        const appStore = useAppStore()
        // 如果尚未拉取过配置，就去调用 getConfig 方法拿配置
        if (Object.keys(appStore.config).length === 0) {
            try {
                const data: any = await appStore.getConfig()

                // 如果后端返回了 web_favicon，则更新页面 favicon
                let favicon: HTMLLinkElement | null = document.querySelector('link[rel="icon"]')
                if (favicon) {
                    favicon.href = data.web_favicon
                } else {
                    favicon = document.createElement('link')
                    favicon.rel = 'icon'
                    favicon.href = data.web_favicon
                    document.head.appendChild(favicon)
                }
            } catch (err) {
                console.error('获取系统配置失败：', err)
            }
        }
        // 继续放行，下一个守卫或页面路由会执行
        next()
    })
}
