<template>
  <view class="d-pay-result">
    <view class="d-pay-result__icon" :class="success ? 'd-pay-result__icon--success' : 'd-pay-result__icon--fail'">
      <view
        :class="success ? 'i-ri-checkbox-circle-fill' : 'i-ri-close-circle-fill'"
        :style="{ fontSize: '120rpx', color: success ? '#19be6b' : '#fa3534' }"
      />
    </view>
    <text class="d-pay-result__message">{{ message }}</text>
    <view class="d-pay-result__extra">
      <slot />
    </view>
  </view>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  success: boolean
  message?: string
}>(), {
  message: '',
})
</script>

<style lang="scss" scoped>
.d-pay-result {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 80rpx 0 60rpx;

  &__icon {
    margin-bottom: 32rpx;
  }

  &__message {
    font-size: 34rpx;
    font-weight: 600;
    color: #333333;
    margin-bottom: 40rpx;
  }

  &__extra {
    width: 100%;
    padding: 0 32rpx;
    box-sizing: border-box;
  }
}
</style>
