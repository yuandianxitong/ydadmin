<template>
  <view class="diy-search">
    <view v-if="props.props?.link" @tap="diyNavigate(props.props.link)" class="diy-search__box" :style="boxStyle">
      <text class="diy-search__ph">{{ props.props?.placeholder || '搜索' }}</text>
    </view>
    <view v-else class="diy-search__box" :style="boxStyle">
      <text class="diy-search__ph">{{ props.props?.placeholder || '搜索' }}</text>
    </view>
  </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { diyNavigate } from './navigate'
const props = defineProps<{ props: Record<string, any> }>()
const boxStyle = computed(() => ({ borderRadius: (props.props?.radius ?? 20) + 'rpx', backgroundColor: props.props?.bg_color || '#f5f5f5' }))
</script>
<style scoped>
.diy-search { padding:16rpx 20rpx; }
.diy-search__box { height:64rpx; display:flex; align-items:center; padding:0 24rpx; }
.diy-search__ph { color:#999; font-size:26rpx; }
</style>
