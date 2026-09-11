<template>
  <u-popup
    :show="visible"
    mode="bottom"
    :safeAreaInsetBottom="true"
    :customStyle="{ borderRadius: '24rpx 24rpx 0 0' }"
    @close="visible = false"
  >
    <view class="d-payment-popup">
      <view class="d-payment-popup__header">
        <text class="d-payment-popup__title">选择支付方式</text>
        <view class="d-payment-popup__amount">
          <text class="d-payment-popup__symbol">¥</text>
          <text class="d-payment-popup__price">{{ amountText }}</text>
        </view>
      </view>

      <view class="d-payment-popup__methods">
        <view
          class="d-payment-popup__method"
          :class="{ 'is-active': selected === 'wechat' }"
          @tap="selected = 'wechat'"
        >
          <view class="d-payment-popup__method-icon d-payment-popup__method-icon--wechat">
            <text class="iconfont icon-wechat" style="font-size: 40rpx; color: #ffffff" />
          </view>
          <text class="d-payment-popup__method-name">微信支付</text>
          <view
            :class="selected === 'wechat' ? 'i-ri-checkbox-circle-fill' : 'i-ri-checkbox-blank-circle-line'"
            :style="{ fontSize: '40rpx', color: selected === 'wechat' ? '#07c160' : '#cccccc' }"
          />
        </view>

        <view
          class="d-payment-popup__method"
          :class="{ 'is-active': selected === 'alipay' }"
          @tap="selected = 'alipay'"
        >
          <view class="d-payment-popup__method-icon d-payment-popup__method-icon--alipay">
            <text class="iconfont icon-alipay" style="font-size: 40rpx; color: #ffffff" />
          </view>
          <text class="d-payment-popup__method-name">支付宝支付</text>
          <view
            :class="selected === 'alipay' ? 'i-ri-checkbox-circle-fill' : 'i-ri-checkbox-blank-circle-line'"
            :style="{ fontSize: '40rpx', color: selected === 'alipay' ? '#1677ff' : '#cccccc' }"
          />
        </view>
      </view>

      <view class="d-payment-popup__footer">
        <u-button
          type="primary"
          block
          :loading="loading"
          :disabled="loading"
          @click="handlePay"
        >
          确认支付
        </u-button>
      </view>
    </view>
  </u-popup>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { PayChannel } from '@/api/payment'

const props = withDefaults(defineProps<{
  modelValue: boolean
  amount: number
  loading?: boolean
}>(), {
  loading: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  pay: [channel: PayChannel]
}>()

const selected = ref<PayChannel>('wechat')

const visible = computed({
  get: () => props.modelValue,
  set: (val: boolean) => emit('update:modelValue', val),
})

const amountText = computed(() => {
  return (props.amount / 100).toFixed(2)
})

function handlePay() {
  emit('pay', selected.value)
}
</script>

<style lang="scss" scoped>
.d-payment-popup {
  padding: 40rpx 32rpx;

  &__header {
    text-align: center;
    margin-bottom: 48rpx;
  }

  &__title {
    display: block;
    font-size: 32rpx;
    font-weight: 600;
    color: #333333;
    margin-bottom: 20rpx;
  }

  &__symbol {
    font-size: 32rpx;
    font-weight: 600;
    color: #fa3534;
  }

  &__price {
    font-size: 56rpx;
    font-weight: 700;
    color: #fa3534;
  }

  &__methods {
    margin-bottom: 48rpx;
  }

  &__method {
    display: flex;
    align-items: center;
    padding: 28rpx 24rpx;
    background: #f8f8f8;
    border-radius: 16rpx;
    margin-bottom: 20rpx;
    border: 2rpx solid transparent;
    transition: all 0.2s;

    &.is-active {
      background: #ffffff;
      border-color: var(--yd-color-primary, #2979ff);
      box-shadow: 0 4rpx 16rpx rgba(41, 121, 255, 0.1);
    }
  }

  &__method-icon {
    width: 72rpx;
    height: 72rpx;
    border-radius: 16rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20rpx;

    &--wechat {
      background: #07c160;
    }

    &--alipay {
      background: #1677ff;
    }
  }

  &__method-name {
    flex: 1;
    font-size: 30rpx;
    color: #333333;
    font-weight: 500;
  }

  &__footer {
    padding-bottom: 20rpx;
  }
}
</style>
