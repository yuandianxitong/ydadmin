<template>
  <div v-if="props.props?.src" class="pv-video" :style="{ height: h }">
    <img v-if="props.props?.poster" :src="props.props.poster" style="width:100%;height:100%;object-fit:cover" />
    <div class="pv-video__play">▶</div>
  </div>
  <!-- 空态对齐 uniapp diy-video：无 src 灰底文字占位（200rpx=100px）；容器无圆角（交 componentStyle） -->
  <div v-else class="pv-video__empty">视频</div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ props: Record<string, any> }>()
const h = computed(() => (props.props?.height || 200) / 2 + 'px')
</script>
<style scoped>
.pv-video { position:relative; background:#222; }
.pv-video__play { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:#fff; font-size:24px; }
.pv-video__empty { height:100px; display:flex; align-items:center; justify-content:center; background:#eef1f6; color:#9aa4b2; font-size:12px; }
</style>
