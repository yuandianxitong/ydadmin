<template>
  <view class="diy-page">
    <DiyRenderer
      v-if="page && page.components && page.components.length"
      :components="page.components"
      :page-settings="page.page_settings"
    />
    <view v-else-if="loaded" class="diy-empty">
      <text class="diy-empty-text">{{ errMsg || '页面暂无内容' }}</text>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import DiyRenderer from '@/components/diy/DiyRenderer.vue'
import { mobileConfigApi, type DiyPagePayload } from '@/api/mobile-config'
import { provideMemberStats } from '@/hooks/useMemberStats'

const page = ref<DiyPagePayload | null>(null)
const loaded = ref(false)
const errMsg = ref('')
const { refresh: refreshMemberStats } = provideMemberStats()

onLoad((query: Record<string, string> | undefined) => {
  const key = query?.key || ''
  if (!key) {
    loaded.value = true
    errMsg.value = '缺少页面标识'
    return
  }
  mobileConfigApi
    .getDiyPage(key)
    .then((res) => {
      page.value = res
      const title = res.title || (res.page_settings && (res.page_settings as any).title) || ''
      if (title) uni.setNavigationBarTitle({ title })
      // 自定义页装修树拿到之后再拉统计（角标/资产），此前该宿主未 provide，角标永远无数据
      refreshMemberStats(res.components)
    })
    .catch(() => {
      errMsg.value = '页面不存在或未发布'
    })
    .finally(() => {
      loaded.value = true
    })
})
</script>

<style lang="scss" scoped>
.diy-page { min-height: 100vh; background-color: #f5f5f5; }
.diy-empty { padding: 120rpx 0; text-align: center; }
.diy-empty-text { font-size: 26rpx; color: #999; }
</style>
