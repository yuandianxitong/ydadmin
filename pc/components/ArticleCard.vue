<template>
  <NuxtLink :to="`/article/${article.id}`" class="flex gap-4 py-5 border-b border-gray-100 hover:bg-gray-50/50 transition-colors -mx-4 px-4">
    <img
      v-if="article.cover"
      :src="article.cover"
      :alt="article.title"
      class="w-44 h-28 object-cover rounded flex-shrink-0"
    />
    <div class="flex flex-col justify-between flex-1 min-w-0 py-0.5">
      <div>
        <h3 class="text-base font-semibold text-gray-900 leading-snug line-clamp-1 hover:text-[var(--color-primary)] transition-colors">{{ article.title }}</h3>
        <p class="text-sm text-gray-500 mt-2 line-clamp-2 leading-relaxed">{{ article.summary }}</p>
      </div>
      <div class="flex items-center gap-4 text-xs text-gray-400">
        <span v-if="article.author" class="text-[var(--color-primary)]">{{ article.author }}</span>
        <span>{{ article.published_at }}</span>
        <span v-if="article.category_name" class="px-1.5 py-0.5 bg-gray-100 rounded text-gray-500">{{ article.category_name }}</span>
        <span class="ml-auto">{{ article.views }} 阅读</span>
      </div>
    </div>
  </NuxtLink>
</template>

<script setup lang="ts">
import type { ArticleItem } from '~/api/article'

defineProps<{
  article: ArticleItem
}>()
</script>
