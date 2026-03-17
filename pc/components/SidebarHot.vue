<template>
  <div class="bg-white rounded-lg p-4">
    <div class="section-title">
      <h3>热门文章</h3>
    </div>
    <ul class="flex flex-col">
      <li v-for="(item, idx) in list" :key="item.id" class="flex gap-3 py-2.5 border-b border-gray-50 last:border-0">
        <span
          class="flex-shrink-0 w-5 h-5 rounded text-xs font-bold flex items-center justify-center"
          :class="idx < 3 ? 'bg-[var(--color-primary)] text-white' : 'bg-gray-100 text-gray-500'"
        >
          {{ idx + 1 }}
        </span>
        <NuxtLink :to="`/article/${item.id}`" class="text-sm text-gray-700 line-clamp-1 hover:text-[var(--color-primary)] transition-colors flex-1">
          {{ item.title }}
        </NuxtLink>
      </li>
      <li v-if="!list.length" class="text-sm text-gray-400 text-center py-4">暂无数据</li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import type { ArticleItem } from '~/api/article'

defineProps<{
  list: ArticleItem[]
}>()
</script>
