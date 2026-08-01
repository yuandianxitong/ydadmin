<script setup lang="ts">
import { onLaunch, onShow } from '@dcloudio/uni-app'
import { useAppStore } from '@/store/app.store'
import { useMobileConfigStore } from '@/store/mobile-config.store'

onLaunch(async () => {
  const appStore = useAppStore()
  const mobileConfigStore = useMobileConfigStore()
  await Promise.all([
    appStore.getConfig().catch(() => {}),
    mobileConfigStore.load().catch(() => {}),
  ])

  // #ifdef H5
  import('@/utils/wechat-oauth').then(({ initWechatOAuth }) => {
    initWechatOAuth()
  })
  // #endif
})

// 每次从后台切回前台时再刷一次导航栏主题（页面 onShow 也会刷）
onShow(() => {
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
