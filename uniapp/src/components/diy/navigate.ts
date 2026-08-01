/** 装修链接统一跳转：外链→webview 承载页；内部页→navigateTo，tabBar 页失败兜底 switchTab。 */
export function diyNavigate(link?: string): void {
  if (!link) return
  if (/^https?:\/\//i.test(link)) {
    uni.navigateTo({ url: '/modules/webview/pages/webview?url=' + encodeURIComponent(link) })
    return
  }
  uni.navigateTo({
    url: link,
    fail: () => {
      // tabBar 页 navigateTo 会失败，去掉 query 后用 switchTab
      uni.switchTab({ url: link.split('?')[0] })
    }
  })
}
