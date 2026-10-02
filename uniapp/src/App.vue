<script setup lang="ts">
import { onLaunch, onShow } from '@dcloudio/uni-app'
import { useAppStore } from '@/store/app.store'
import { useMobileConfigStore } from '@/store/mobile-config.store'
import { isSwitchOff } from '@/utils/siteSwitch'

const MAINTENANCE = '/pages/maintenance/index'

function siteClosed(): boolean {
  return isSwitchOff(useAppStore().config.site_status)
}

function goMaintenance() {
  const pages = getCurrentPages()
  const route = pages.length > 0 ? (pages[pages.length - 1].route || '') : ''
  if (route.includes('pages/maintenance/index')) return
  uni.reLaunch({ url: MAINTENANCE })
}

onLaunch(async () => {
  const appStore = useAppStore()
  const mobileConfigStore = useMobileConfigStore()
  await Promise.all([
    appStore.getConfig().catch(() => {}),
    mobileConfigStore.load().catch(() => {}),
  ])

  if (siteClosed()) goMaintenance()

  ;(['navigateTo', 'redirectTo', 'switchTab', 'reLaunch'] as const).forEach((method) => {
    uni.addInterceptor(method, {
      invoke(args: { url?: string }) {
        if (!siteClosed()) return args
        if (String(args?.url || '').includes('pages/maintenance/index')) return args
        goMaintenance()
        return false
      },
    })
  })

  // #ifdef H5
  import('@/utils/wechat-oauth').then(({ initWechatOAuth }) => {
    initWechatOAuth()
  })
  // #endif
})

// 每次从后台切回前台时再刷一次导航栏主题（页面 onShow 也会刷）
onShow(() => {
  if (siteClosed()) goMaintenance()
  const mobileConfigStore = useMobileConfigStore()
  if (mobileConfigStore.loaded) {
    mobileConfigStore.applyNavigationBarTheme()
  }
})
</script>

<style lang="scss">
@import 'uview-plus/index.scss';
@import './styles/common.scss';
@import './static/fonts/iconfont.css';
</style>
