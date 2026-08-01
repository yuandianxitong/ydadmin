<template>
  <view v-if="items.length" class="diy-sm">
    <u-cell-group>
      <u-cell
        v-for="(it, i) in items"
        :key="i"
        :title="it.text"
        isLink
        @click="diyNavigate(it.link)"
      >
        <template #icon>
          <view class="diy-sm__icon-wrap">
            <image v-if="it.icon" :src="img(it.icon)" mode="aspectFit" class="diy-sm__icon" />
            <u-badge v-if="badgeOf(it) > 0" :value="badgeOf(it)" class="diy-sm__badge" />
          </view>
        </template>
      </u-cell>
    </u-cell-group>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { diyNavigate } from './navigate'
import { useMemberStats } from '@/hooks/useMemberStats'
import { useAppStore } from '@/store/app.store'

const props = defineProps<{ props: Record<string, any> }>()
const appStore = useAppStore()

const items = computed(
  () => (props.props?.items as Array<{ icon?: string; text?: string; link?: string; badge_key?: string }>) || []
)
const stats = useMemberStats()
function badgeOf(it: { badge_key?: string }): number {
  return it.badge_key ? Number(stats.value[it.badge_key] ?? 0) : 0
}
function img(url?: string): string {
  void appStore.isConfigLoaded
  void appStore.config.site_url
  return url ? appStore.getImageUrl(url) : ''
}
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.diy-sm {
  background: #ffffff;
  overflow: hidden;
  // 与编辑器预览对齐：默认无外边距/圆角/阴影，间距圆角交由装修 componentStyle 控制
}

.diy-sm__icon-wrap {
  position: relative;
  display: inline-flex;
}

.diy-sm__icon {
  width: 40rpx;
  height: 40rpx;
  margin-right: 16rpx;
}

.diy-sm__badge {
  position: absolute;
  top: -8rpx;
  right: -8rpx;
}
</style>
