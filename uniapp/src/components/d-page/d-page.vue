<template>
  <view class="d-page" :class="{ 'safe-area-bottom': safeArea }" :style="cssVars">
    <slot />
  </view>
</template>

<script setup lang="ts">
import { useTheme } from '@/hooks/useTheme'

withDefaults(defineProps<{
  safeArea?: boolean
}>(), {
  safeArea: true,
})

const { cssVars } = useTheme()
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';
.d-page {
  min-height: 100vh;
  background-color: var(--yd-color-page-bg, #f5f5f5);
  padding: $page-padding;
  box-sizing: border-box;
}
</style>
