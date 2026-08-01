<template>
  <div class="video-select">
    <!-- 未上传：上传框 -->
    <el-upload
      v-if="!modelValue"
      class="vs-box"
      :action="action"
      :headers="headers"
      :show-file-list="false"
      :accept="ACCEPT"
      :before-upload="beforeUpload"
      :on-success="(res: any) => onSuccess(res)"
      :on-error="onError"
    >
      <div class="vs-add">
        <el-icon><Plus /></el-icon>
        <span class="vs-add__t">上传视频</span>
      </div>
    </el-upload>

    <!-- 已上传：预览 + 替换/删除 -->
    <div v-else class="vs-loaded">
      <video :src="modelValue" class="vs-video" preload="metadata" muted playsinline />
      <div class="vs-ops">
        <el-upload
          :action="action"
          :headers="headers"
          :show-file-list="false"
          :accept="ACCEPT"
          :before-upload="beforeUpload"
          :on-success="(res: any) => onSuccess(res)"
          :on-error="onError"
        >
          <el-button link type="primary" size="small">替换</el-button>
        </el-upload>
        <el-button link type="danger" size="small" @click="handleRemove">删除</el-button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Plus } from '@element-plus/icons-vue'
import { computed } from 'vue'

import { appConfig } from '@/constants/appSetting'
import { RequestCodeEnum } from '@/constants/request'
import useUserStore from '@/store/modules/user.store'
import feedback from '@/utils/feedback'

const props = defineProps<{ modelValue: string }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>()

const userStore = useUserStore()
const action = `${appConfig.baseUrl}/${appConfig.urlPrefix}/upload/video`
const headers = computed(() => ({ Authorization: `Bearer ${userStore.token}` }))

const ACCEPT = '.mp4,.webm,.mov,.m4v,.ogg,.avi,.flv,.mkv'
const MAX_MB = 100
function beforeUpload(file: File): boolean {
  if (!file.type.startsWith('video/')) {
    feedback.msgError('只能上传视频文件')
    return false
  }
  if (file.size / 1024 / 1024 > MAX_MB) {
    feedback.msgError(`视频不能超过 ${MAX_MB}MB`)
    return false
  }
  return true
}

function onSuccess(res: any) {
  if (res?.code === RequestCodeEnum.SUCCESS) {
    const url = res.data?.url || res.data?.path || ''
    if (url) emit('update:modelValue', url)
  } else {
    feedback.msgError(res?.message || '上传失败')
  }
}

function onError() {
  feedback.msgError('上传失败，请重试')
}

function handleRemove() {
  emit('update:modelValue', '')
}
</script>

<style scoped lang="scss">
.vs-box {
  :deep(.el-upload) {
    width: 160px;
    height: 90px;
    border: 1px dashed var(--color-border);
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: border-color 0.2s;

    &:hover {
      border-color: var(--el-color-primary);
    }
  }
}

.vs-add {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  color: var(--color-text-tertiary);
  font-size: var(--font-size-caption);

  .el-icon {
    font-size: 22px;
  }
}

.vs-loaded {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.vs-video {
  width: 160px;
  height: 90px;
  border-radius: var(--radius-lg);
  object-fit: cover;
  background: #000;
}

.vs-ops {
  display: flex;
  align-items: center;
  gap: 12px;
}
</style>
