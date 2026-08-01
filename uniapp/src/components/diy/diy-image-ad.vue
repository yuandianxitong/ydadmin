<template>
  <view class="diy-image-ad">
    <template v-for="(it, i) in items" :key="i">
      <view v-if="it.link" @tap="diyNavigate(it.link)" class="diy-image-ad__item" :style="itemStyle">
        <image :src="it.image" mode="widthFix" class="diy-image-ad__img" />
      </view>
      <view v-else class="diy-image-ad__item" :style="itemStyle">
        <image :src="it.image" mode="widthFix" class="diy-image-ad__img" />
      </view>
    </template>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { diyNavigate } from './navigate'
const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => (props.props?.items as any[]) || [])
const layout = computed(() => props.props?.layout || 'single')
const cols = computed(() => props.props?.columns || 2)
const itemStyle = computed(() => layout.value === 'grid' ? { width: `${100 / (cols.value || 2)}%` } : { width: '100%' })
</script>

<style scoped>
.diy-image-ad { display: flex; flex-wrap: wrap; }
.diy-image-ad__item { box-sizing: border-box; }
.diy-image-ad__img { width: 100%; display: block; }
</style>
