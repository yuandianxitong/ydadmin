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
    if (url.startsWith('http://') || url.startsWith('https://')) return url
    const baseUrl = config.value.site_url || config.value.oss_domain || ''
    return baseUrl + url
  }

  return { config, isConfigLoaded, getConfig, getImageUrl }
})
