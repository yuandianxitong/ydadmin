import { ref } from 'vue'
import { versionApi, type VersionCheckResult } from '@/api/version'

export function useVersionCheck() {
  const checking = ref(false)
  const updateInfo = ref<VersionCheckResult | null>(null)

  function getPlatform(): string {
    const systemInfo = uni.getSystemInfoSync()
    const os = systemInfo.osName?.toLowerCase() || systemInfo.platform?.toLowerCase() || ''
    if (os.includes('android')) return 'android'
    if (os.includes('ios')) return 'ios'
    if (os.includes('harmony')) return 'harmony'
    return 'android'
  }

  function getCurrentVersionCode(): number {
    // Get version code from manifest or app config
    // In production, this would come from app manifest
    const appBaseInfo = uni.getAppBaseInfo?.() || {}
    return parseInt(String((appBaseInfo as any).appVersionCode || '1'), 10)
  }

  async function checkUpdate(silent = false): Promise<VersionCheckResult> {
    checking.value = true
    try {
      const platform = getPlatform()
      const versionCode = getCurrentVersionCode()
      const result = await versionApi.checkUpdate(platform, versionCode)
      updateInfo.value = result

      if (result.need_update && !silent) {
        showUpdateDialog(result)
      } else if (!result.need_update && !silent) {
        uni.showToast({ title: '已是最新版本', icon: 'success' })
      }

      return result
    } catch {
      if (!silent) {
        uni.showToast({ title: '检查更新失败', icon: 'none' })
      }
      return { need_update: false }
    } finally {
      checking.value = false
    }
  }

  function showUpdateDialog(info: VersionCheckResult) {
    uni.showModal({
      title: `发现新版本 ${info.version}`,
      content: info.description || '有新版本可用，是否立即更新？',
      showCancel: !info.force_update,
      confirmText: '立即更新',
      cancelText: '暂不更新',
      success: (res) => {
        if (res.confirm && info.download_url) {
          // #ifdef APP-PLUS
          plus.runtime.openURL(info.download_url)
          // #endif
          // #ifndef APP-PLUS
          uni.showToast({ title: '请到应用商店更新', icon: 'none' })
          // #endif
        }
        if (info.force_update && res.cancel) {
          // Force update: close app
          // #ifdef APP-PLUS
          plus.runtime.quit()
          // #endif
        }
      },
    })
  }

  return { checking, updateInfo, checkUpdate, getPlatform, getCurrentVersionCode }
}
