<template>
  <NuxtLink :to="`/article/${article.id}`" class="card p-4 block hover:shadow-md transition-shadow">
    <div class="flex gap-4">
      <img
        v-if="article.cover"
        :src="article.cover"
        :alt="article.title"
        class="w-48 h-32 object-cover rounded flex-shrink-0"
      />
      <div class="flex flex-col justify-between flex-1 min-w-0">
        <div>
          <h3 class="text-lg font-semibold text-gray-900 truncate">{{ article.title }}</h3>
          <p class="text-sm text-gray-500 mt-2 line-clamp-2">{{ article.summary }}</p>
        </div>
        <div class="flex items-center gap-4 text-xs text-gray-400 mt-3">
          <span v-if="article.category_name">{{ article.category_name }}</span>
          <span>{{ article.published_at }}</span>
          <span>{{ article.views }} 阅读</span>
        </div>
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
