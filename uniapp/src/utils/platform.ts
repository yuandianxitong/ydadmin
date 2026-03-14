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

export function isApp(): boolean {
  return getPlatform() === 'app'
}
