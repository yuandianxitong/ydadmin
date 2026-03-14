export function getPlatform(): string {
  // UniApp 条件编译在构建时生效，TypeScript 静态分析看不到
  // 使用 uni API 运行时判断平台
  const systemInfo = uni.getSystemInfoSync()
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
