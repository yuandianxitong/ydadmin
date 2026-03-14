<template>
  <view class="d-empty">
    <image v-if="icon" :src="icon" class="d-empty__icon" mode="aspectFit" />
    <text class="d-empty__text">{{ text }}</text>
    <wd-button v-if="actionText" type="primary" size="small" @click="$emit('action')">
      {{ actionText }}
    </wd-button>
  </view>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  icon?: string
  text?: string
  actionText?: string
}>(), {
  text: '暂无数据',
})

defineEmits<{ action: [] }>()
</script>

<style lang="scss" scoped>
.d-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 100rpx 0;

  &__icon {
    width: 200rpx;
    height: 200rpx;
    margin-bottom: 30rpx;
  }

  &__text {
    font-size: 28rpx;
    color: #999;
    margin-bottom: 30rpx;
  }
}
</style>
