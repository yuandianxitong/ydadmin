<template>
  <d-page :safe-area="true">
    <view class="feedback-page">
      <!-- 反馈类型 -->
      <view class="section-card">
        <view class="section-title">反馈类型</view>
        <view class="type-list">
          <view
            v-for="item in typeOptions"
            :key="item.value"
            class="type-tag"
            :class="{ active: form.type === item.value }"
            @tap="form.type = item.value"
          >
            {{ item.label }}
          </view>
        </view>
      </view>

      <!-- 反馈内容 -->
      <view class="section-card">
        <view class="section-title">反馈内容</view>
        <wd-textarea
          v-model="form.content"
          placeholder="请详细描述您遇到的问题或建议..."
          :maxlength="500"
          show-word-limit
          :rows="6"
          class="content-textarea"
        />
      </view>

      <!-- 图片上传 -->
      <view class="section-card">
        <view class="section-title">上传图片 <text class="section-hint">（最多3张）</text></view>
        <view class="image-upload">
          <view
            v-for="(img, index) in form.images"
            :key="index"
            class="image-item"
          >
            <image :src="appStore.getImageUrl(img)" mode="aspectFill" class="preview-image" @tap="previewImage(index)" />
            <view class="image-delete" @tap="removeImage(index)">
              <text class="iconfont icon-trash3" style="color: #fff; font-size: 24rpx" />
            </view>
          </view>
          <view
            v-if="form.images.length < 3"
            class="image-add"
            @tap="handleUpload"
          >
            <text class="iconfont icon-image" style="font-size: 56rpx; color: #cccccc" />
          </view>
        </view>
      </view>

      <!-- 联系方式 -->
      <view class="section-card">
        <view class="section-title">联系方式 <text class="section-hint">（选填）</text></view>
        <input
          v-model="form.contact"
          placeholder="手机号/邮箱，方便我们联系您"
          class="contact-input"
          placeholder-class="input-placeholder"
        />
      </view>

      <!-- 提交按钮 -->
      <wd-button
        block
        :loading="loading"
        :disabled="!canSubmit"
        class="submit-btn"
        @click="handleSubmit"
      >
        提交反馈
      </wd-button>
    </view>
  </d-page>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { feedbackApi } from '@/api/feedback'
import { useUpload } from '@/hooks/useUpload'
import { useAppStore } from '@/store/app.store'

const appStore = useAppStore()
const { chooseAndUpload } = useUpload()

const typeOptions = [
  { label: '功能建议', value: 'suggestion' },
  { label: '问题反馈', value: 'bug' },
  { label: '投诉', value: 'complaint' },
  { label: '其他', value: 'other' },
]

const loading = ref(false)

const form = reactive({
  type: 'suggestion',
  content: '',
  images: [] as string[],
  contact: '',
})

const canSubmit = computed(() => {
  return form.content.trim().length > 0 && !loading.value
})

async function handleUpload() {
  if (form.images.length >= 3) return
  try {
    const path = await chooseAndUpload()
    form.images.push(path)
  } catch {
    // user cancelled or upload failed
  }
}

function removeImage(index: number) {
  form.images.splice(index, 1)
}

function previewImage(index: number) {
  const urls = form.images.map((img) => appStore.getImageUrl(img))
  uni.previewImage({ urls, current: urls[index] })
}

async function handleSubmit() {
  if (!canSubmit.value) return

  loading.value = true
  try {
    await feedbackApi.submit({
      type: form.type,
      content: form.content.trim(),
      images: form.images.length > 0 ? form.images : undefined,
      contact: form.contact.trim() || undefined,
    })
    uni.showToast({ title: '提交成功', icon: 'success' })
    setTimeout(() => {
      uni.navigateBack()
    }, 1500)
  } catch {
    // error handled by request interceptor
  } finally {
    loading.value = false
  }
}
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.feedback-page {
  padding: 0;
}

.section-card {
  background: #ffffff;
  border-radius: 24rpx;
  overflow: hidden;
  margin-bottom: 24rpx;
  padding: 30rpx;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.05);
}

.section-title {
  font-size: 30rpx;
  font-weight: 600;
  color: $text-color;
  margin-bottom: 24rpx;
}

.section-hint {
  font-size: 24rpx;
  font-weight: 400;
  color: $text-color-secondary;
}

.type-list {
  display: flex;
  flex-wrap: wrap;
  gap: 20rpx;
}

.type-tag {
  padding: 16rpx 36rpx;
  border-radius: 40rpx;
  font-size: 28rpx;
  color: $text-color;
  background: $bg-color;
  transition: all 0.2s;

  &.active {
    color: #ffffff;
    background: $primary-color;
  }
}

.content-textarea {
  width: 100%;
}

.image-upload {
  display: flex;
  flex-wrap: wrap;
  gap: 20rpx;
}

.image-item {
  position: relative;
  width: 200rpx;
  height: 200rpx;
  border-radius: 16rpx;
  overflow: hidden;
}

.preview-image {
  width: 100%;
  height: 100%;
}

.image-delete {
  position: absolute;
  top: 0;
  right: 0;
  width: 44rpx;
  height: 44rpx;
  background: rgba(0, 0, 0, 0.5);
  border-radius: 0 0 0 16rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.image-add {
  width: 200rpx;
  height: 200rpx;
  border-radius: 16rpx;
  border: 2rpx dashed #cccccc;
  display: flex;
  align-items: center;
  justify-content: center;
  background: $bg-color;
}

.contact-input {
  width: 100%;
  height: 80rpx;
  font-size: 28rpx;
  color: $text-color;
}

.input-placeholder {
  color: #c0c4cc;
  font-size: 28rpx;
}

.submit-btn {
  border-radius: 16rpx !important;
  height: 96rpx !important;
  font-size: 32rpx !important;
  margin-top: 12rpx;
}
</style>
