<template>
  <div class="decor-page">
    <DecorPageHeader title="主题风格" subtitle="设置移动端店铺主题色板，应用至底部导航、按钮、价格、角标等">
      <template #actions>
        <el-button @click="load">重置</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </DecorPageHeader>

    <DecorSection title="主题色">
      <div class="decor-grid-3">
        <div v-for="f in COLOR_FIELDS" :key="f.key" class="color-field">
          <span class="color-field__label">{{ f.label }}</span>
          <div class="color-field__row">
            <el-color-picker v-model="colors[f.key]" />
            <span class="color-field__hex mono">{{ colors[f.key] || '默认' }}</span>
          </div>
        </div>
      </div>
      <div class="decor-hint" style="margin-top: 14px">
        主色应用于导航/底部导航选中态与主要按钮；其余色板下发到端上（H5 为 CSS 变量），用于价格、角标、按钮文字、页面背景等。
      </div>
    </DecorSection>
  </div>
</template>

<script setup lang="ts">
import { ElMessage } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'

import { mobileConfigApi, type ThemeColors } from '@/api/mobile-config'

import DecorPageHeader from './components/DecorPageHeader.vue'
import DecorSection from './components/DecorSection.vue'

const DEFAULT_PRIMARY = '#2979ff'
const COLOR_FIELDS: { key: keyof ThemeColors; label: string }[] = [
  { key: 'primary', label: '主色' },
  { key: 'dark', label: '深色 / 渐变终点' },
  { key: 'price', label: '价格强调色' },
  { key: 'page_bg', label: '页面背景色' },
  { key: 'button_text', label: '按钮文字色' },
  { key: 'badge', label: '角标 / 警示色' },
]

const colors = reactive<ThemeColors>({ primary: DEFAULT_PRIMARY })
const saving = ref(false)

async function load() {
  const res = await mobileConfigApi.get()
  const c = res.data.theme_colors || {}
  for (const f of COLOR_FIELDS) {
    colors[f.key] = c[f.key] || (f.key === 'primary' ? res.data.theme_color || DEFAULT_PRIMARY : '')
  }
}

async function handleSave() {
  saving.value = true
  try {
    await mobileConfigApi.update({
      theme_color: colors.primary || DEFAULT_PRIMARY,
      theme_colors: { ...colors },
    })
    ElMessage.success('已保存')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<style scoped lang="scss">
@import './components/decor.scss';

.color-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.color-field__label {
  font-size: 11.5px;
  color: var(--color-text-secondary);
}
.color-field__row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.color-field__hex {
  font-size: 12px;
  color: var(--color-text-tertiary);
}
</style>
