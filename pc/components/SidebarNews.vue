<template>
  <div class="bg-white rounded-lg p-4">
    <div class="section-title">
      <h3>新闻快讯</h3>
    </div>
    <ul class="flex flex-col gap-3">
      <li v-for="item in list" :key="item.id">
        <NuxtLink :to="`/article/${item.id}`" class="flex flex-col gap-1 group">
          <span class="text-sm text-gray-700 line-clamp-1 group-hover:text-[var(--color-primary)] transition-colors">{{ item.title }}</span>
          <span class="text-xs text-gray-400">{{ item.published_at }}</span>
        </NuxtLink>
      </li>
      <li v-if="!list.length" class="text-sm text-gray-400 text-center py-4">暂无快讯</li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import type { ArticleItem } from '~/api/article'

defineProps<{
  list: ArticleItem[]
}>()
</script>
