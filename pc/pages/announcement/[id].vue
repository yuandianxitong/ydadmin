<template>
  <div class="mx-auto max-w-800px px-6 py-10">
    <div v-if="loading" class="text-center py-20 text-gray-400">加载中...</div>
    <article v-else-if="announcement">
      <h1 class="text-3xl font-bold text-gray-900">{{ announcement.title }}</h1>
      <p v-if="announcement.publish_at" class="text-sm text-gray-400 mt-4">{{ announcement.publish_at }}</p>
      <div class="announcement-content mt-8" v-html="sanitizedContent" />
    </article>
    <div v-else class="text-center py-20 text-gray-400">{{ errorMessage || '公告不存在或已下架' }}</div>
    <div class="mt-12">
      <NuxtLink to="/" class="text-sm text-[var(--color-primary)]">&larr; 返回首页</NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import DOMPurify from 'dompurify'
import { get } from '~/composables/useRequest'

interface Announcement {
  id: number
  title: string
  content: string
  publish_at?: string
}

const route = useRoute()
const announcement = ref<Announcement | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const sanitizedContent = computed(() => DOMPurify.sanitize(announcement.value?.content || ''))
let requestSequence = 0

watch(() => route.params.id, async (id) => {
  const sequence = ++requestSequence
  loading.value = true
  announcement.value = null
  errorMessage.value = ''
  try {
    const res = await get<Announcement>(`/api/announcement/detail/${encodeURIComponent(String(id))}`, undefined, false)
    if (sequence === requestSequence && res.code === 200) announcement.value = res.data
  } catch {
    if (sequence === requestSequence) errorMessage.value = '公告加载失败，请稍后重试'
  } finally {
    if (sequence === requestSequence) loading.value = false
  }
}, { immediate: true })
</script>

<style scoped>
.announcement-content { line-height: 1.8; overflow-wrap: anywhere; }
.announcement-content :deep(img) { max-width: 100%; height: auto; }
.announcement-content :deep(p) { margin-bottom: 1em; }
</style>
