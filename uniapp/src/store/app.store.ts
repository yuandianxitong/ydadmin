import { defineStore } from 'pinia'
import { ref } from 'vue'
import { configApi } from '@/api/config'

export const useAppStore = defineStore('app', () => {
  const config = ref<Record<string, any>>({})
  const isConfigLoaded = ref(false)

  async function getConfig() {
    if (isConfigLoaded.value) return config.value
    const result = await configApi.getGlobalConfig()
    config.value = result
    isConfigLoaded.value = true
    return result
  }

  function getImageUrl(url: string): string {
    if (!url) return ''
    if (url.startsWith('http://') || url.startsWith('https://')) return url
    const baseUrl = config.value.site_url || config.value.oss_domain || ''
    return baseUrl + url
  }

  return { config, isConfigLoaded, getConfig, getImageUrl }
})
