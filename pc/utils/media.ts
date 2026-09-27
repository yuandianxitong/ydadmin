/** 演示封面若被装成 http://127.0.0.1/storage/...，在公网站点上改用当前域名。 */
export function mediaUrl(url: string): string {
  if (!url) return ''
  if (!url.startsWith('http://') && !url.startsWith('https://')) return url
  try {
    const parsed = new URL(url)
    const loopback = parsed.hostname === '127.0.0.1' || parsed.hostname === 'localhost'
    if (loopback && parsed.pathname.startsWith('/storage/') && typeof window !== 'undefined') {
      return `${window.location.origin}${parsed.pathname}${parsed.search}`
    }
  } catch {
    return url
  }

  return url
}
