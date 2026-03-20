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

  function getImageUrl(url: string): string {
    if (!url) return ''
    const baseUrl = config.value.site_url || config.value.oss_domain || ''
    // 已是完整 URL：如果 site_url 已配置且 URL 以其他域名开头，替换为 site_url
    if (url.startsWith('http://') || url.startsWith('https://')) {
      if (baseUrl) {
        // 提取路径部分（从第一个 /storage/ 或 /uploads/ 开始）
        const pathMatch = url.match(/(\/storage\/.*)/)
        if (pathMatch) {
          return baseUrl + pathMatch[1]
        }
      }
      return url
    }
    return baseUrl + url
  }

  return { config, isConfigLoaded, getConfig, getImageUrl }
})
