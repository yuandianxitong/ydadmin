<template>
  <view class="diy-nav-grid">
    <template v-for="(it, i) in items" :key="i">
      <view v-if="it.link" @tap="diyNavigate(it.link)" class="diy-nav-grid__item" :style="{ width: itemWidth }">
        <view class="diy-nav-grid__icon-wrap">
          <image :src="it.icon" mode="aspectFit" class="diy-nav-grid__icon" />
          <u-badge v-if="badgeOf(it) > 0" :value="badgeOf(it)" class="diy-nav-grid__badge" />
        </view>
        <text class="diy-nav-grid__text">{{ it.text }}</text>
      </view>
      <view v-else class="diy-nav-grid__item" :style="{ width: itemWidth }">
        <view class="diy-nav-grid__icon-wrap">
          <image :src="it.icon" mode="aspectFit" class="diy-nav-grid__icon" />
          <u-badge v-if="badgeOf(it) > 0" :value="badgeOf(it)" class="diy-nav-grid__badge" />
        </view>
        <text class="diy-nav-grid__text">{{ it.text }}</text>
      </view>
    </template>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { diyNavigate } from './navigate'
import { useMemberStats } from '@/hooks/useMemberStats'
const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => (props.props?.items as any[]) || [])
const cols = computed(() => props.props?.columns || 4)
const itemWidth = computed(() => `${100 / (cols.value || 4)}%`)
const stats = useMemberStats()
function badgeOf(it: { badge_key?: string }): number {
  return it.badge_key ? Number(stats.value[it.badge_key] ?? 0) : 0
}
</script>

<style scoped>
.diy-nav-grid { display: flex; flex-wrap: wrap; padding: 20rpx; box-sizing: border-box; }
.diy-nav-grid__item { display: flex; flex-direction: column; align-items: center; padding: 12rpx 0; box-sizing: border-box; }
.diy-nav-grid__icon-wrap { position: relative; }
.diy-nav-grid__icon { width: 80rpx; height: 80rpx; }
.diy-nav-grid__badge { position: absolute; top: -8rpx; right: -16rpx; }
.diy-nav-grid__text { font-size: 24rpx; color: #333; margin-top: 8rpx; }
</style>
