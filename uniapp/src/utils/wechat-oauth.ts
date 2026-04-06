/**
 * 微信 H5（公众号）OAuth 静默授权工具
 *
 * 存储层统一使用 uni.getStorageSync / setStorageSync，兼容多端；
 * 业务流程 initWechatOAuth 涉及 window/navigator，只在 H5 编译。
 */
const OA_OPENID_KEY = 'wechat_oa_openid'
const OA_BINDPENDING_KEY = 'wechat_oa_bind_pending'

export function getOaOpenid(): string | null {
  const v = uni.getStorageSync(OA_OPENID_KEY)
  return v ? String(v) : null
}

export function setOaOpenid(openid: string) {
  uni.setStorageSync(OA_OPENID_KEY, openid)
}

/** 标记 oa_openid 待绑定（登录后自动关联到用户） */
export function setBindPending(pending: boolean) {
  if (pending) {
    uni.setStorageSync(OA_BINDPENDING_KEY, '1')
  } else {
    uni.removeStorageSync(OA_BINDPENDING_KEY)
  }
}

export function isBindPending(): boolean {
  return uni.getStorageSync(OA_BINDPENDING_KEY) === '1'
}

/**
 * 登录成功后调用：如果有待绑定的 oa_openid，关联到当前用户
 */
export async function bindOaOpenidAfterLogin() {
  if (!isBindPending()) return
  const openid = getOaOpenid()
  if (!openid) return

  try {
    const { default: http } = await import('@/utils/request')
    await http.post('/api/user/bind-oa-openid', { oa_openid: openid })
    setBindPending(false)
  } catch (e) {
    console.error('绑定 oa_openid 失败', e)
  }
}

/**
 * H5 微信浏览器内检测并触发 OAuth 静默授权
 *
 * 流程：
 * 1. 首次进入 → 无 oa_openid → 跳转微信授权
 * 2. 微信回调 → URL 带 code → 调后端登录接口 → 已有用户自动登录 / 新用户跳转登录页
 * 3. 后续访问 → storage 中有 oa_openid → 跳过
 *
 * 注意：此函数使用 window/navigator 浏览器 API，仅在 H5 平台编译。
 */
export async function initWechatOAuth() {
  // 仅 H5 平台执行具体逻辑，其他平台直接返回
  // #ifdef H5
  if (typeof navigator === 'undefined') return
  const ua = navigator.userAgent.toLowerCase()
  if (!ua.includes('micromessenger')) return

  const url = new URL(window.location.href)

  // Step 2: 微信授权回调后，URL 中有 code 参数
  const code = url.searchParams.get('code')
  if (code) {
    // 清理 URL 参数（避免 code 过期后重复使用）
    url.searchParams.delete('code')
    url.searchParams.delete('state')
    window.history.replaceState({}, '', url.toString())

    try {
      const { default: http } = await import('@/utils/request')
      const { setToken, getToken } = await import('@/utils/auth')

      // 调用 H5 微信登录接口
      const result = await http.post<{
        status: string
        openid: string
        unionid?: string
        token?: string
        temp_token?: string
      }>('/api/auth/wechat-h5-login', { code })

      // 存储 openid
      if (result.openid) {
        setOaOpenid(result.openid)
      }

      if (result.status === 'logged_in' && result.token) {
        // 已有用户，自动登录
        setToken(result.token)
      } else if (result.status === 'need_login') {
        // 新用户：标记待绑定，跳转登录页
        setBindPending(true)
        if (!getToken()) {
          uni.reLaunch({ url: '/modules/login/pages/login' })
        }
      }
    } catch (e) {
      console.error('微信 H5 登录失败', e)
    }
    return
  }

  // 已有 openid，不需要再授权
  if (getOaOpenid()) return

  // Step 1: 发起 OAuth 静默授权
  try {
    const { default: http } = await import('@/utils/request')
    const result = await http.get<{ url: string }>('/api/wechat/oauth-url', {
      redirect_url: window.location.href.split('?')[0],
      scope: 'snsapi_base',
    })
    if (result && result.url) {
      window.location.href = result.url
    }
  } catch (e) {
    console.error('获取 OAuth URL 失败', e)
  }
  // #endif
}
