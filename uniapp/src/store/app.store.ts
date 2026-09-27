import { defineStore } from 'pinia'
import { ref } from 'vue'
import { configApi } from '@/api/config'

export const useAppStore = defineStore('app', () => {
  const config = ref<Record<string, any>>({})
  const isConfigLoaded = ref(false)
  let configPromise: Promise<Record<string, any>> | null = null

  async function getConfig() {
    if (isConfigLoaded.value) return config.value
    if (configPromise) return configPromise
    configPromise = configApi.getGlobalConfig().then((result) => {
      config.value = result
      isConfigLoaded.value = true
      return result
    }).finally(() => {
      configPromise = null
    })
    return configPromise
  }

  /** 静态资源域名：优先后台 site_url / oss_domain，否则回退 VITE_APP_API_URL */
  function getMediaBaseUrl(): string {
    const fromConfig = String(config.value.site_url || config.value.oss_domain || '').replace(/\/+$/, '')
    if (fromConfig) return fromConfig
    // 后台未配 site_url 时（常见于本地/小程序），用构建期 API 域名拼绝对地址
    return String(import.meta.env.VITE_APP_API_URL || '').replace(/\/+$/, '')
  }

  function getImageUrl(url: string): string {
    if (!url) return ''
    if (url.startsWith('data:')) return url

    const baseUrl = getMediaBaseUrl()

    // 已是完整 URL。/storage/ 且落在 127.0.0.1、localhost 时，公网页面改用当前域名。
    if (url.startsWith('http://') || url.startsWith('https://')) {
      const pathMatch = url.match(/(\/storage\/.*)/)
      if (pathMatch) {
        const origin = publicPageOrigin()
        if (origin) return origin + pathMatch[1]
        if (baseUrl && !isLoopbackBase(baseUrl)) return baseUrl + pathMatch[1]
      }
      return url
    }

    const path = url.startsWith('/') ? url : `/${url}`
    return baseUrl ? `${baseUrl}${path}` : path
  }

  function publicPageOrigin(): string {
    if (typeof window === 'undefined') return ''
    const host = window.location.hostname
    if (host === '' || host === '127.0.0.1' || host === 'localhost') return ''
    return window.location.origin
  }

  function isLoopbackBase(value: string): boolean {
    try {
      const host = new URL(value).hostname
      return host === '127.0.0.1' || host === 'localhost'
    } catch {
      return false
    }
  }

  /** 清理内存中的全局配置，下次调用 getConfig 时会重新拉取 */
  function resetConfig() {
    config.value = {}
    isConfigLoaded.value = false
    configPromise = null
  }

  return { config, isConfigLoaded, getConfig, getImageUrl, resetConfig }
})
