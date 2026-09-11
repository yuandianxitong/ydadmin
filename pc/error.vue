<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="text-center px-6">
      <div class="text-6xl font-bold text-gray-300 mb-4">{{ error?.statusCode || 500 }}</div>
      <h1 class="text-xl font-semibold text-gray-700 mb-2">
        {{ error?.statusCode === 404 ? '页面不存在' : '出错了' }}
      </h1>
      <p class="text-gray-400 mb-8">{{ error?.message || '服务器内部错误，请稍后再试' }}</p>
      <button class="btn-primary" @click="handleError">返回首页</button>
    </div>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{ error: { statusCode: number; message: string } }>()

function handleError() {
  clearError({ redirect: '/' })
}
</script>
