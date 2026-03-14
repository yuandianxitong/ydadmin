<template>
  <view class="d-price">
    <text class="d-price__symbol">¥</text>
    <text class="d-price__integer">{{ integer }}</text>
    <text class="d-price__decimal">.{{ decimal }}</text>
    <text v-if="originalPrice != null" class="d-price__original">
      ¥{{ originalPriceText }}
    </text>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  price: number
  originalPrice?: number
}>()

const priceText = computed(() => (props.price / 100).toFixed(2))
const integer = computed(() => priceText.value.split('.')[0])
const decimal = computed(() => priceText.value.split('.')[1])
const originalPriceText = computed(() =>
  props.originalPrice != null ? (props.originalPrice / 100).toFixed(2) : '',
)
</script>

<style lang="scss" scoped>
.d-price {
  display: inline-flex;
  align-items: baseline;
  color: #fa3534;

  &__symbol {
    font-size: 24rpx;
    font-weight: 600;
  }

  &__integer {
    font-size: 40rpx;
    font-weight: 700;
  }

  &__decimal {
    font-size: 24rpx;
    font-weight: 600;
  }

  &__original {
    margin-left: 12rpx;
    font-size: 24rpx;
    color: #999999;
    text-decoration: line-through;
  }
}
</style>
