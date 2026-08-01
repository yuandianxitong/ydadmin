<template>
  <div class="decor-page">
    <DecorPageHeader title="底部导航" subtitle="设置移动端店铺底部 tabBar 的菜单项、图标与整体样式（最多 5 项）">
      <template #actions>
        <el-button @click="loadAll">重置</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </DecorPageHeader>

    <el-row :gutter="14">
      <!-- 左：菜单项列表 -->
      <el-col :span="9">
        <DecorSection title="菜单项">
          <template #extra>
            <span class="tb-count">{{ tabbarList.length }}/5</span>
          </template>
          <div v-if="tabbarList.length === 0" class="decor-empty">暂无菜单项，点击下方新增。</div>
          <div
            v-for="(item, idx) in tabbarList"
            :key="idx"
            class="tb-item"
            :class="{ 'tb-item--active': idx === selIdx }"
            @click="selIdx = idx"
          >
            <div class="tb-item__icon">
              <img v-if="item.icon" :src="item.icon" alt="" />
            </div>
            <div class="tb-item__main">
              <div class="tb-item__label">{{ item.text || '未命名' }}</div>
              <div class="tb-item__sub">跳转：{{ item.path || '—' }}</div>
            </div>
          </div>
          <div
            v-if="tabbarList.length < 5"
            class="tb-add"
            @click="addTabbarItem"
          >
            <el-icon><Plus /></el-icon> 新增菜单
          </div>
        </DecorSection>
      </el-col>

      <!-- 右：选中项设置 + 整体样式 -->
      <el-col :span="15">
        <DecorSection :title="sel ? `菜单项设置 · ${sel.text || '未命名'}` : '菜单项设置'">
          <template v-if="sel" #extra>
            <el-button link type="danger" @click="removeTabbarItem(selIdx)">删除</el-button>
          </template>
          <div v-if="!sel" class="decor-empty">从左侧选择或新增一个菜单项进行编辑。</div>
          <el-form v-else label-position="top" class="decor-form">
            <el-form-item label="跳转链接" required>
              <el-input
                v-model="sel.path"
                placeholder="如 pages/index/index 或 /pages/discover/index"
                @change="onPathChange(sel)"
              />
              <div class="tb-hint">填写 UniApp 页面路径；tab 页可用 switchTab，其它页会自动 navigateTo</div>
            </el-form-item>
            <div class="decor-grid-2">
              <el-form-item label="标题">
                <el-input v-model="sel.text" placeholder="底部文字" />
              </el-form-item>
              <el-form-item label="选中标题">
                <el-input v-model="sel.sel_label" placeholder="选中时文字，可空" />
              </el-form-item>
            </div>
            <div class="decor-grid-2">
              <el-form-item label="未选图标">
                <ImageSelect :model-value="sel.icon || ''" @update:model-value="(v: string | string[]) => { if (sel) sel.icon = v as string }" />
              </el-form-item>
              <el-form-item label="选中图标">
                <ImageSelect :model-value="sel.selected_icon || ''" @update:model-value="(v: string | string[]) => { if (sel) sel.selected_icon = v as string }" />
              </el-form-item>
            </div>
          </el-form>
        </DecorSection>

        <DecorSection title="整体样式">
          <div class="decor-grid-3">
            <div class="color-field">
              <span class="color-field__label">文字颜色</span>
              <div class="color-field__row">
                <el-color-picker v-model="tabbarStyle.text_color" />
                <span class="color-field__hex mono">{{ tabbarStyle.text_color || '默认' }}</span>
              </div>
            </div>
            <div class="color-field">
              <span class="color-field__label">选中文字颜色</span>
              <div class="color-field__row">
                <el-color-picker v-model="tabbarStyle.active_color" />
                <span class="color-field__hex mono">{{ tabbarStyle.active_color || '默认' }}</span>
              </div>
            </div>
            <div class="color-field">
              <span class="color-field__label">背景颜色</span>
              <div class="color-field__row">
                <el-color-picker v-model="tabbarStyle.bg_color" />
                <span class="color-field__hex mono">{{ tabbarStyle.bg_color || '默认' }}</span>
              </div>
            </div>
          </div>
        </DecorSection>
      </el-col>
    </el-row>
  </div>
</template>

<script setup lang="ts">
import { Plus } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'

import { mobileConfigApi, type MobileTabbarItem, type TabbarStyle } from '@/api/mobile-config'

import DecorPageHeader from './components/DecorPageHeader.vue'
import DecorSection from './components/DecorSection.vue'

const BUILTIN_BY_PATH: Record<string, string> = {
  'pages/index/index': '__home__',
  'pages/discover/index': '__discover__',
  'pages/message/index': '__message__',
  'pages/my/index': '__my__',
}

const form = reactive({
  tabbar: [] as MobileTabbarItem[],
})

const tabbarStyle = reactive<TabbarStyle>({})

const saving = ref(false)
const selIdx = ref(0)

const tabbarList = computed(() => (Array.isArray(form.tabbar) ? form.tabbar : []))
const sel = computed<MobileTabbarItem | null>(() => tabbarList.value[selIdx.value] ?? null)

function normalizePath(path: string): string {
  return (path || '').trim().replace(/^\//, '')
}

function codeForPath(path: string, fallback?: string): string {
  const p = normalizePath(path)
  if (BUILTIN_BY_PATH[p]) return BUILTIN_BY_PATH[p]
  if (fallback && !fallback.startsWith('__home__') && !Object.values(BUILTIN_BY_PATH).includes(fallback)) {
    return fallback
  }
  return `__custom_${p.replace(/[^a-zA-Z0-9]+/g, '_').replace(/^_|_$/g, '').slice(0, 32) || Date.now()}__`
}

function applyConfig(payload: Partial<{
  tabbar: MobileTabbarItem[]
  tabbar_style: TabbarStyle
}> | null | undefined) {
  const data = payload || {}
  form.tabbar = Array.isArray(data.tabbar) ? data.tabbar.map((t) => ({ ...t })) : []
  Object.assign(tabbarStyle, data.tabbar_style || {})
}

async function loadAll() {
  try {
    const cfg = await mobileConfigApi.get()
    applyConfig(cfg?.data)
    selIdx.value = 0
  } catch (err) {
    console.error('[DiyTabbar] load failed', err)
    form.tabbar = Array.isArray(form.tabbar) ? form.tabbar : []
    ElMessage.error('加载底部导航配置失败')
  }
}

function addTabbarItem() {
  if (tabbarList.value.length >= 5) return
  form.tabbar.push({
    code: codeForPath(''),
    path: '',
    text: '',
    icon: '',
    selected_icon: '',
    sel_label: '',
    badge: '',
  })
  selIdx.value = form.tabbar.length - 1
}

function removeTabbarItem(idx: number) {
  form.tabbar.splice(idx, 1)
  if (selIdx.value >= form.tabbar.length) selIdx.value = Math.max(0, form.tabbar.length - 1)
}

function onPathChange(item: MobileTabbarItem) {
  item.path = normalizePath(item.path)
  item.code = codeForPath(item.path, item.code)
}

async function handleSave() {
  for (let i = 0; i < tabbarList.value.length; i++) {
    const t = tabbarList.value[i]
    if (!normalizePath(t.path)) {
      ElMessage.warning(`第 ${i + 1} 项请填写跳转链接`)
      selIdx.value = i
      return
    }
    if (!String(t.text || '').trim()) {
      ElMessage.warning(`第 ${i + 1} 项请填写标题`)
      selIdx.value = i
      return
    }
  }
  saving.value = true
  try {
    const res = await mobileConfigApi.update({
      home_app_code: '',
      home_page: '',
      tabbar: tabbarList.value.map((t) => {
        const path = normalizePath(t.path)
        return {
          ...t,
          path,
          code: codeForPath(path, t.code),
          text: String(t.text || '').trim(),
        }
      }),
      tabbar_style: { ...tabbarStyle },
    })
    applyConfig(res?.data)
    if (selIdx.value >= form.tabbar.length) selIdx.value = Math.max(0, form.tabbar.length - 1)
    ElMessage.success('已保存')
  } catch (err) {
    console.error('[DiyTabbar] save failed', err)
  } finally {
    saving.value = false
  }
}

onMounted(loadAll)
</script>

<style scoped lang="scss">
@import './components/decor.scss';

.tb-count {
  font-size: 11.5px;
  color: var(--color-text-tertiary);
}

.tb-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  margin-bottom: 8px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
}
.tb-item--active {
  border-color: var(--el-color-primary);
  background: var(--el-color-primary-light-9);
}
.tb-item__icon {
  width: 32px;
  height: 32px;
  flex-shrink: 0;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  background: var(--color-surface-sunken);
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}
.tb-item__icon img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.tb-item__main {
  flex: 1;
  min-width: 0;
}
.tb-item__label {
  font-size: 13px;
  font-weight: 500;
  color: var(--color-text-primary);
}
.tb-item__sub {
  margin-top: 2px;
  font-size: 11px;
  color: var(--color-text-tertiary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.tb-add {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 12px;
  border: 1px dashed var(--color-border-strong);
  border-radius: var(--radius-md);
  font-size: 12.5px;
  color: var(--color-text-secondary);
  cursor: pointer;
}
.tb-hint {
  margin-top: 6px;
  font-size: 12px;
  color: var(--color-text-tertiary);
  line-height: 1.4;
}

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
