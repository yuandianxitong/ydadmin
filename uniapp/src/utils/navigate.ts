export function navigateTo(url: string, params?: Record<string, any>) {
  const query = params
    ? '?' + Object.entries(params).map(([k, v]) => `${k}=${encodeURIComponent(v)}`).join('&')
    : ''
  uni.navigateTo({ url: url + query })
}

export function redirectTo(url: string) {
  uni.redirectTo({ url })
}

export function switchTab(url: string) {
  uni.switchTab({ url })
}

export function navigateBack(delta = 1) {
  uni.navigateBack({ delta })
}

export function reLaunch(url: string) {
  uni.reLaunch({ url })
}
