<template>
  <view class="diy-title-bar" :style="{ justifyContent: (props.props?.align === 'center') ? 'center' : 'flex-start' }">
    <text class="diy-title-bar__title">{{ props.props?.title }}</text>
    <text v-if="props.props?.subtitle" class="diy-title-bar__sub">{{ props.props?.subtitle }}</text>
    <view v-if="props.props?.more_link" @tap="diyNavigate(props.props?.more_link)" class="diy-title-bar__more">{{ props.props?.more_text || '更多' }}</view>
  </view>
</template>

<script setup lang="ts">
import { diyNavigate } from './navigate'
const props = defineProps<{ props: Record<string, any> }>()
</script>

<style lang="scss" scoped>
.diy-title-bar {
  padding: 20rpx;
  display: flex;
  align-items: baseline;

  &__title {
    font-size: 32rpx;
    font-weight: 600;
    color: #222;
  }

  &__sub {
    font-size: 24rpx;
    color: #999;
    margin-left: 12rpx;
  }

  &__more {
    font-size: 24rpx;
    color: #999;
    margin-left: auto;
  }
}
</style>
