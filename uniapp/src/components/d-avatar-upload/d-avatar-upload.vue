<template>
  <view class="avatar-upload" @tap="handleUpload">
    <view class="avatar-wrap">
      <image
        v-if="displayUrl"
        class="avatar"
        :src="displayUrl"
        mode="aspectFill"
      />
      <view v-else class="default-avatar">
        <text class="iconfont icon-person-circle" />
      </view>
      <view class="camera-overlay">
        <text class="iconfont icon-image" style="color: #ffffff; font-size: 36rpx" />
      </view>
    </view>
    <text class="hint">点击更换头像</text>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useUpload } from '@/hooks/useUpload'
import { useAppStore } from '@/store/app.store'

const props = defineProps<{
  modelValue: string
}>()

const appStore = useAppStore()
const displayUrl = computed(() => appStore.getImageUrl(props.modelValue))

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const { chooseAndUpload } = useUpload({ maxSize: 5 })

async function handleUpload() {
  try {
    const url = await chooseAndUpload()
    emit('update:modelValue', url)
  } catch {
    // user cancelled or upload error
  }
}
</script>

<style lang="scss" scoped>
.avatar-upload {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 20rpx 0;
}

.avatar-wrap {
  position: relative;
  width: 160rpx;
  height: 160rpx;
  border-radius: 50%;
  overflow: hidden;
  border: 4rpx solid #f0f4ff;

  .avatar {
    width: 100%;
    height: 100%;
  }

  .default-avatar {
    width: 100%;
    height: 100%;
    background: #e8e8e8;
    display: flex;
    align-items: center;
    justify-content: center;

    .iconfont {
      font-size: 80rpx;
      color: #c0c4cc;
    }
  }

  .camera-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 56rpx;
    background: rgba(0, 0, 0, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
  }
}

.hint {
  margin-top: 16rpx;
  font-size: 24rpx;
  color: #999;
}
</style>
