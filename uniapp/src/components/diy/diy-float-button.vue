<template>
  <view class="diy-float" :class="props.props?.position === 'left-bottom' ? 'is-left' : 'is-right'">
    <template v-for="(it, i) in items" :key="i">
      <view v-if="it.link" @tap="diyNavigate(it.link)" class="diy-float__btn">
        <image v-if="it.icon" :src="it.icon" class="diy-float__icon" />
        <text v-if="it.text" class="diy-float__text">{{ it.text }}</text>
      </view>
      <view v-else class="diy-float__btn">
        <image v-if="it.icon" :src="it.icon" class="diy-float__icon" />
        <text v-if="it.text" class="diy-float__text">{{ it.text }}</text>
      </view>
    </template>
  </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { diyNavigate } from './navigate'
const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => props.props?.items || [])
</script>
<style scoped>
.diy-float { position:fixed; bottom:120rpx; z-index:90; display:flex; flex-direction:column; }
.diy-float.is-right { right:24rpx; }
.diy-float.is-left { left:24rpx; }
.diy-float__btn { width:96rpx; height:96rpx; margin-top:16rpx; border-radius:50%; background:rgba(0,0,0,.6); display:flex; flex-direction:column; align-items:center; justify-content:center; }
.diy-float__icon { width:40rpx; height:40rpx; }
.diy-float__text { color:#fff; font-size:18rpx; }
</style>
