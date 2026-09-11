<template>
  <view class="diy-image-cube">
    <template v-for="(it, i) in items" :key="i">
      <view v-if="it.link" @tap="diyNavigate(it.link)" class="diy-image-cube__item" :style="itemStyle">
        <image :src="it.image" mode="aspectFill" class="diy-image-cube__img" />
      </view>
      <view v-else class="diy-image-cube__item" :style="itemStyle">
        <image :src="it.image" mode="aspectFill" class="diy-image-cube__img" />
      </view>
    </template>
  </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { diyNavigate } from './navigate'
const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => props.props?.items || [])
const cols = computed(() => props.props?.cols || 2)
const itemStyle = computed(() => {
  const g = (props.props?.gap || 0) / 2
  return { width: `${100 / (cols.value || 2)}%`, paddingLeft: `${g}rpx`, paddingRight: `${g}rpx` }
})
</script>
<style scoped>
.diy-image-cube { display:flex; flex-wrap:wrap; }
.diy-image-cube__item { box-sizing:border-box; }
.diy-image-cube__img { width:100%; height:200rpx; display:block; border-radius:8rpx; }
</style>
