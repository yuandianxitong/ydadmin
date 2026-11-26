import type { Component } from 'vue'

export interface PluginPageAsset {
    pluginCode: string
    url: string
    styles?: string[]
}

/** 已经挂上或正在加载的样式表，键是地址。 */
const styleLoads = new Map<string, Promise<void>>()

function loadStyle(url: string): Promise<void> {
    const known = styleLoads.get(url)
    if (known) return known

    const load = new Promise<void>((resolve) => {
        const link = document.createElement('link')
        link.rel = 'stylesheet'
        link.href = url
        link.dataset.pluginStyle = ''
        link.onload = () => resolve()
        // 样式表取不到时页面照常打开；摘掉这次的记录，下次进入页面再取一遍
        link.onerror = () => {
            styleLoads.delete(url)
            link.remove()
            resolve()
        }
        document.head.appendChild(link)
    })
    styleLoads.set(url, load)
    return load
}

/** 挂上插件的样式表并等它们加载完。同一个地址只挂一次。 */
export function ensurePluginStyles(urls: string[]): Promise<void> {
    return Promise.all(urls.map((url) => loadStyle(url))).then(() => undefined)
}

/** 补齐宿主这边的 Element Plus 组件样式；取不到时页面照常打开。 */
function ensureHostStyles(): Promise<void> {
    return import('./element-styles').then(
        () => undefined,
        () => undefined,
    )
}

/**
 * 先挂样式表，再用浏览器原生动态 import 取页面：
 * 插件产物的裸依赖由 index.html 里的 import map 解析到宿主，样式不在 js 里。
 * 组件库的规则由宿主补齐，页面自己的规则靠清单里的 styles，后者排在后面。
 */
export async function loadPluginModule(asset: PluginPageAsset): Promise<{ default: Component }> {
    await ensureHostStyles()
    await ensurePluginStyles(asset.styles ?? [])
    return import(/* @vite-ignore */ asset.url)
}
