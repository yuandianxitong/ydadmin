<template>
  <view class="diy-notice">
    <image v-if="props.props?.icon" :src="props.props.icon" class="diy-notice__icon" />
    <swiper v-if="items.length" vertical :autoplay="items.length > 1" :interval="props.props?.speed || 3000" :circular="items.length > 2" class="diy-notice__sw">
      <swiper-item v-for="(it, i) in items" :key="i">
        <view v-if="it.link" @tap="diyNavigate(it.link)" class="diy-notice__text">{{ it.text }}</view>
        <text v-else class="diy-notice__text">{{ it.text }}</text>
      </swiper-item>
    </swiper>
    <text v-else class="diy-notice__text diy-notice__ph">公告</text>
  </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { diyNavigate } from './navigate'
const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => props.props?.items || [])
</script>
<style scoped>
.diy-notice { display:flex; align-items:center; padding:12rpx 20rpx; background:#fffbe6; }
.diy-notice__icon { width:32rpx; height:32rpx; margin-right:12rpx; }
.diy-notice__sw { height:40rpx; flex:1; }
.diy-notice__text { font-size:24rpx; color:#8a6d3b; line-height:40rpx; }
.diy-notice__ph { color:#c0a16b; }
</style>
