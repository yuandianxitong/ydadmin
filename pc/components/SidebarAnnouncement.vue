<template>
  <div class="bg-white rounded-sm p-4">
    <div class="section-title">
      <h3>公告通知</h3>
    </div>
    <ul class="flex flex-col gap-3">
      <li v-for="item in list" :key="item.id">
        <a href="javascript:;" class="text-sm text-gray-700 line-clamp-1 hover:text-[var(--color-primary)] transition-colors">{{ item.title }}</a>
      </li>
      <li v-if="!list.length" class="text-sm text-gray-400 text-center py-4">暂无公告</li>
    </ul>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  list: { id: number; title: string }[]
}>()
</script>
