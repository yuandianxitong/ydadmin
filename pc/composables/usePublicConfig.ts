import { commonApi } from '~/api/common'

/** 与服务端 SystemConfigService::isSecuritySwitchOn 同一套关闭值。 */
export function isSwitchOff(value: unknown): boolean {
  if (value === false || value === 0) return true
  const text = String(value ?? '').trim().toLowerCase()
  return text === '0' || text === 'false' || text === 'no' || text === 'off'
}

export function usePublicConfig() {
  const config = useState<Record<string, any> | null>('yd-public-config', () => null)
  const loaded = useState('yd-public-config-loaded', () => false)

  async function load() {
    if (loaded.value) return
    try {
      const res = await commonApi.getConfig()
      if (res.code === 200 && res.data) config.value = res.data
    } catch {
      config.value = null
    }
    loaded.value = true
  }

  const siteClosed = computed(() => loaded.value && isSwitchOff(config.value?.site_status))
  const registrationOpen = computed(() => loaded.value && !isSwitchOff(config.value?.user_register))
  const closeTip = computed(() => {
    const tip = String(config.value?.site_close_tip || '').trim()
    return tip || '网站维护中，请稍后再试...'
  })

  return { load, loaded, siteClosed, registrationOpen, closeTip }
}
