export function getPlatform(): string {
  const systemInfo = uni.getSystemInfoSync()
  // #ifdef H5
  return 'h5'
  // #endif
  // #ifdef MP-WEIXIN
  return 'mp-weixin'
  // #endif
  // #ifdef APP-PLUS
  return 'app'
  // #endif
  if (systemInfo.uniPlatform) {
    return systemInfo.uniPlatform
  }
  return 'unknown'
}

export function isH5(): boolean {
  return getPlatform() === 'h5' || getPlatform() === 'web'
}

export function isWeixin(): boolean {
  return getPlatform() === 'mp-weixin'
}

export function isApp(): boolean {
  return getPlatform() === 'app'
}
