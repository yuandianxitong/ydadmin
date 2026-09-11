<template>
  <div class="decor-header">
    <div class="decor-header__main">
      <div class="decor-header__title">{{ title }}</div>
      <div v-if="subtitle" class="decor-header__sub">{{ subtitle }}</div>
    </div>
    <div v-if="$slots.actions" class="decor-header__actions">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ title: string; subtitle?: string }>()
</script>

<style scoped>
.decor-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 18px;
  background: var(--color-surface);
  border-radius: var(--el-card-border-radius);
  box-shadow: var(--shadow-hairline);
  margin-bottom: 14px;
}

.decor-header__title {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-text-primary);
}

.decor-header__sub {
  margin-top: 4px;
  font-size: 12.5px;
  color: var(--color-text-tertiary);
}

.decor-header__actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: none;
}
</style>
