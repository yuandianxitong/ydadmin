<template>
  <view class="diy-video">
    <video v-if="props.props?.src" :src="props.props.src" :poster="props.props?.poster || ''"
           :autoplay="!!props.props?.autoplay" :loop="!!props.props?.loop"
           :style="{ width: '100%', height: (props.props?.height || 200) + 'rpx' }" class="diy-video__v" />
    <view v-else class="diy-video__empty">视频</view>
  </view>
</template>
<script setup lang="ts">
const props = defineProps<{ props: Record<string, any> }>()
</script>
<style scoped>
.diy-video { width:100%; }
.diy-video__v { width:100%; }
.diy-video__empty { height:200rpx; display:flex; align-items:center; justify-content:center; background:#eef1f6; color:#9aa4b2; font-size:24rpx; }
</style>
