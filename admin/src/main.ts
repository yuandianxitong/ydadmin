// src/app/main.ts

import 'element-plus/dist/index.css'
import 'element-plus/theme-chalk/dark/css-vars.css'
import '@/assets/fonts/stylesheet.css'
import './theme/tokens.scss'
import './theme/overrides/element-plus.scss'
import './styles/index.scss'
import 'virtual:uno.css'

import { createPinia } from 'pinia'
import { createApp } from 'vue'

import App from './App.vue'
import { installDirectives } from './directives'
import { setupI18n } from './locales/setupI18n'
import router from './router'
import { installElementPlus } from './utils/elementPlus'
import { installHighlight } from './utils/highlight'

const app = createApp(App)

// ========== 全局错误处理 ==========

// Vue 组件内部错误（渲染、生命周期、事件处理器）
app.config.errorHandler = (err, instance, info) => {
    console.error('[Vue Error]', err)
    console.error('Component:', instance)
    console.error('Info:', info)
}

// Vue 渲染时的警告（仅开发模式下有效）
app.config.warnHandler = import.meta.env.DEV
    ? (msg, instance, trace) => {
          console.warn('[Vue Warn]', msg, trace)
      }
    : undefined

// 未捕获的 Promise 异常
window.addEventListener('unhandledrejection', (event) => {
    // 忽略已被拦截器处理过的请求错误（避免重复提示）
    if (event.reason?.isAxiosError || event.reason?.__handled) {
        event.preventDefault()
        return
    }
    console.error('[Unhandled Promise Rejection]', event.reason)
})

// 全局 JS 运行时错误
window.addEventListener('error', (event) => {
    // 忽略资源加载错误（图片、脚本等），这些由浏览器处理
    if (event.target && (event.target as HTMLElement).tagName) {
        return
    }
    console.error('[Global Error]', event.error || event.message)
})

// ========== 插件注册 ==========

const pinia = createPinia()
app.use(pinia)
app.use(router)

const i18n = setupI18n()
app.use(i18n)

installElementPlus?.(app)
installHighlight?.(app)
installDirectives?.(app)

import createInitGuard from '@/router/guards/auth.guard'
createInitGuard(router)

import createPermissionGuard from '@/router/guards/permission.guard'
createPermissionGuard(router)

// 挂载应用
app.mount('#app')
