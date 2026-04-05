<template>
  <view class="agreement" @tap="toggle">
    <view
      :class="modelValue ? 'i-ri-checkbox-circle-fill' : 'i-ri-checkbox-blank-circle-line'"
      :style="{ fontSize: '36rpx', color: modelValue ? '#2979ff' : '#ccc' }"
    />
    <text class="agreement-text">
      我已阅读并同意
      <text class="link" @tap.stop="openUrl(privacyUrl)">《隐私政策》</text>
      和
      <text class="link" @tap.stop="openUrl(termsUrl)">《用户协议》</text>
    </text>
  </view>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  modelValue: boolean
  termsUrl?: string
  privacyUrl?: string
}>(), {
  termsUrl: '/modules/agreement/pages/agreement?code=user_agreement',
  privacyUrl: '/modules/agreement/pages/agreement?code=privacy_policy',
})

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

function toggle() {
  emit('update:modelValue', !props.modelValue)
}

function openUrl(url?: string) {
  if (url) {
    uni.navigateTo({ url })
  }
}
</script>

<style lang="scss" scoped>
.agreement {
  display: flex;
  align-items: center;
  padding: 20rpx 0;
}
.agreement-text {
  font-size: 24rpx;
  color: #999;
  margin-left: 10rpx;
}
.link {
  color: #2979ff;
}
</style>
