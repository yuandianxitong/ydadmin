<template>
  <view
    class="d-image-preview"
    :style="{ gap: gap }"
  >
    <view
      v-for="(src, index) in images"
      :key="index"
      class="d-image-preview__item"
      :style="itemStyle"
    >
      <image
        class="d-image-preview__img"
        :src="src"
        mode="aspectFill"
        @tap="handlePreview(index)"
        @error="handleError(index)"
      />
      <view
        v-if="deletable"
        class="d-image-preview__delete"
        @tap.stop="handleDelete(index)"
      >
        <wd-icon name="close" color="#ffffff" size="24rpx" />
      </view>
      <image
        v-if="errorIndexes.has(index)"
        class="d-image-preview__img d-image-preview__img--error"
        src="/static/image-error.png"
        mode="aspectFit"
        @tap="handlePreview(index)"
      />
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed, reactive } from 'vue'

const props = withDefaults(defineProps<{
  images: string[]
  columns?: number
  gap?: string
  previewable?: boolean
  deletable?: boolean
}>(), {
  columns: 3,
  gap: '16rpx',
  previewable: true,
  deletable: false,
})

const emit = defineEmits<{
  delete: [index: number]
}>()

const errorIndexes = reactive(new Set<number>())

const itemStyle = computed(() => {
  const gapValue = parseInt(props.gap) || 16
  const totalGap = gapValue * (props.columns - 1)
  const width = `calc((100% - ${totalGap}rpx) / ${props.columns})`
  return {
    width,
    height: width,
  }
})

function handlePreview(index: number) {
  if (!props.previewable) return
  uni.previewImage({
    current: index,
    urls: props.images,
  })
}

function handleDelete(index: number) {
  emit('delete', index)
}

function handleError(index: number) {
  errorIndexes.add(index)
}
</script>

<style lang="scss" scoped>
.d-image-preview {
  display: flex;
  flex-wrap: wrap;

  &__item {
    position: relative;
    border-radius: 12rpx;
    overflow: hidden;
    background: #f5f5f5;
  }

  &__img {
    width: 100%;
    height: 100%;

    &--error {
      position: absolute;
      top: 0;
      left: 0;
      background: #f5f5f5;
      padding: 20rpx;
    }
  }

  &__delete {
    position: absolute;
    top: 0;
    right: 0;
    width: 40rpx;
    height: 40rpx;
    background: rgba(0, 0, 0, 0.5);
    border-radius: 0 0 0 12rpx;
    display: flex;
    align-items: center;
    justify-content: center;
  }
}
</style>
