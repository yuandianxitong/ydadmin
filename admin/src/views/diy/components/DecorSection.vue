<template>
  <div class="decor-section">
    <div class="decor-section__title">
      <span class="decor-section__bar" />
      <span>{{ title }}</span>
      <span v-if="$slots.extra" class="decor-section__extra"><slot name="extra" /></span>
    </div>
    <div class="decor-section__body">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ title: string }>()
</script>

<style scoped>
.decor-section {
  background: var(--color-surface);
  border-radius: var(--el-card-border-radius);
  box-shadow: var(--shadow-hairline);
  padding: 16px 18px;
  margin-bottom: 14px;
}

.decor-section__title {
  display: flex;
  align-items: center;
  gap: 6px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--color-border);
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-primary);
}

.decor-section__bar {
  width: 3px;
  height: 11px;
  border-radius: 2px;
  background: var(--el-color-primary);
  flex: none;
}

.decor-section__extra {
  margin-left: auto;
  font-weight: 400;
}

.decor-section__body {
  padding-top: 14px;
}
</style>
