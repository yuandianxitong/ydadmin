let cachedPlatform: string | null = null

export function getPlatform(): string {
  if (cachedPlatform) return cachedPlatform
  const systemInfo = uni.getSystemInfoSync()
  cachedPlatform = systemInfo.uniPlatform || 'unknown'
  return cachedPlatform
}

export function isH5(): boolean {
  const p = getPlatform()
  return p === 'h5' || p === 'web'
}

export function isWeixin(): boolean {
  return getPlatform() === 'mp-weixin'
}

/**
 * 判断是否在微信浏览器内（H5 环境）
 */
export function isWeixinBrowser(): boolean {
  if (!isH5()) return false
  if (typeof navigator !== 'undefined') {
    return /MicroMessenger/i.test(navigator.userAgent)
  }
  return false
}

export function isApp(): boolean {
  return getPlatform() === 'app'
}
