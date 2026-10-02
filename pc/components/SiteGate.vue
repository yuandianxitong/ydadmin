<template>
  <div v-if="!loaded" class="min-h-screen bg-gray-50" />
  <div v-else-if="siteClosed" class="min-h-screen flex items-center justify-center bg-gray-50 px-6">
    <div class="max-w-md text-center">
      <h1 class="text-2xl font-bold text-gray-900 mb-3">网站维护中</h1>
      <p class="text-gray-600 leading-relaxed">{{ closeTip }}</p>
    </div>
  </div>
  <slot v-else />
</template>

<script setup lang="ts">
const { load, loaded, siteClosed, closeTip } = usePublicConfig()

onMounted(() => {
  load()
})
</script>
