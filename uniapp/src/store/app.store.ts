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

    // 已是完整 URL：如果 base 已配置且 URL 含 /storage/，统一到站点域名
    if (url.startsWith('http://') || url.startsWith('https://')) {
      if (baseUrl) {
        const pathMatch = url.match(/(\/storage\/.*)/)
        if (pathMatch) {
          return baseUrl + pathMatch[1]
        }
      }
      return url
    }

    const path = url.startsWith('/') ? url : `/${url}`
    return baseUrl ? `${baseUrl}${path}` : path
  }

  /** 清理内存中的全局配置，下次调用 getConfig 时会重新拉取 */
  function resetConfig() {
    config.value = {}
    isConfigLoaded.value = false
    configPromise = null
  }

  return { config, isConfigLoaded, getConfig, getImageUrl, resetConfig }
})
