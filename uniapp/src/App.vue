<script setup lang="ts">
import { onLaunch } from '@dcloudio/uni-app'
import { useAppStore } from '@/store/app.store'

onLaunch(async () => {
  const appStore = useAppStore()
  await appStore.getConfig().catch(() => {})
})
</script>

<style lang="scss">
@import './styles/common.scss';
@import './static/fonts/iconfont.css';
</style>
