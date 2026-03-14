<template>
  <view class="d-page" :class="{ 'safe-area-bottom': safeArea }">
    <slot />
  </view>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  safeArea?: boolean
}>(), {
  safeArea: true,
})
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';
.d-page {
  min-height: 100vh;
  background-color: $bg-color;
  padding: $page-padding;
  box-sizing: border-box;
}
</style>
