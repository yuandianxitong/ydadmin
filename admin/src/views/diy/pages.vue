<template>
  <div class="decor-page">
    <DecorPageHeader title="自定义页面" subtitle="管理移动端多张自定义装修页（按 slug 寻址）">
      <template #actions>
        <el-button type="primary" @click="openCreate">新建页面</el-button>
      </template>
    </DecorPageHeader>

    <DecorSection title="页面列表">
      <div class="dp-bar">
        <div class="dp-tabs">
          <span
            v-for="t in TABS"
            :key="t.value"
            class="dp-tab"
            :class="{ 'dp-tab--active': tab === t.value }"
            @click="onTab(t.value)"
          >{{ t.label }}</span>
        </div>
        <el-input
          v-model="search"
          placeholder="搜索页面名称"
          clearable
          style="width: 220px"
          @keyup.enter="onSearch"
          @clear="onSearch"
        />
      </div>

      <el-table v-loading="loading" :data="list" style="width: 100%">
        <el-table-column prop="title" label="页面名称" min-width="160" show-overflow-tooltip />
        <el-table-column prop="page_key" label="页面标识" min-width="140" show-overflow-tooltip />
        <el-table-column label="发布状态" width="100">
          <template #default="{ row }">
            <el-tag size="small" :type="row.published ? 'success' : 'warning'">
              {{ row.published ? '已发布' : '草稿' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag size="small" :type="row.status === 1 ? 'success' : 'info'">
              {{ row.status === 1 ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="component_count" label="组件数" width="90" />
        <el-table-column prop="updated_at" label="更新时间" width="170" />
        <el-table-column label="操作" width="280" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="goEditor(row as DiyPageListItem)">编辑装修</el-button>
            <el-button link @click="copy(row as DiyPageListItem)">复制</el-button>
            <el-button link @click="openEdit(row as DiyPageListItem)">改信息</el-button>
            <el-button link @click="toggleStatus(row as DiyPageListItem)">{{ row.status === 1 ? '禁用' : '启用' }}</el-button>
            <el-button link type="danger" @click="remove(row as DiyPageListItem)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="limit"
          :total="total"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next"
          @current-change="load"
          @size-change="onSizeChange"
        />
      </div>
    </DecorSection>

    <el-dialog v-model="dialogVisible" :title="editingId ? '编辑页面信息' : '新建页面'" class="dlg-sm">
      <el-form :model="form" label-width="96px">
        <el-form-item label="页面名称">
          <el-input v-model="form.title" maxlength="100" />
        </el-form-item>
        <el-form-item label="页面标识">
          <el-input v-model="form.page_key" placeholder="小写字母/数字/连字符，如 about-us" />
          <div class="hint">2-64 位，小写字母、数字、连字符；不可为 home</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ElMessage, ElMessageBox } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import { diyApi, type DiyPageListItem } from '@/api/diy'

import DecorPageHeader from './components/DecorPageHeader.vue'
import DecorSection from './components/DecorSection.vue'

const router = useRouter()
const SLUG_RE = /^[a-z0-9][a-z0-9-]{0,62}[a-z0-9]$/ // 首尾字母/数字，2-64位，禁首尾连字符（与后端一致）

const TABS = [
  { label: '全部', value: 'all' as const },
  { label: '已发布', value: 'published' as const },
  { label: '草稿', value: 'draft' as const },
]

const list = ref<DiyPageListItem[]>([])
const loading = ref(false)
const tab = ref<'all' | 'published' | 'draft'>('all')
const search = ref('')
const page = ref(1)
const limit = ref(10)
const total = ref(0)
const dialogVisible = ref(false)
const submitting = ref(false)
const editingId = ref<number | null>(null)
const form = reactive({ title: '', page_key: '' })

async function load() {
  loading.value = true
  try {
    const res = await diyApi.listPages({
      page: page.value,
      limit: limit.value,
      keyword: search.value.trim(),
      published: tab.value === 'all' ? '' : tab.value === 'published' ? 1 : 0
    })
    list.value = res.data?.list || []
    total.value = res.data?.total || 0
  } finally {
    loading.value = false
  }
}
onMounted(load)

function onTab(v: 'all' | 'published' | 'draft') {
  tab.value = v
  page.value = 1
  load()
}
function onSearch() {
  page.value = 1
  load()
}
function onSizeChange() {
  page.value = 1
  load()
}

async function copy(row: DiyPageListItem) {
  await ElMessageBox.confirm(
    `确定复制页面「${row.title}」？副本为未发布草稿，标识自动生成。`,
    '提示',
    { type: 'info' }
  )
  await diyApi.copyPage(row.id)
  ElMessage.success('复制成功')
  await load()
}

function openCreate() {
  editingId.value = null
  form.title = ''
  form.page_key = ''
  dialogVisible.value = true
}

function openEdit(row: DiyPageListItem) {
  editingId.value = row.id
  form.title = row.title
  form.page_key = row.page_key
  dialogVisible.value = true
}

async function submit() {
  if (form.page_key === 'home' || !SLUG_RE.test(form.page_key)) {
    ElMessage.error('页面标识不合法（小写字母/数字/连字符，2-64 位，不可为 home）')
    return
  }
  submitting.value = true
  try {
    if (editingId.value) {
      await diyApi.updatePage(editingId.value, { title: form.title, page_key: form.page_key })
    } else {
      await diyApi.createPage({ title: form.title, page_key: form.page_key })
    }
    ElMessage.success('已保存')
    dialogVisible.value = false
    await load()
  } finally {
    submitting.value = false
  }
}

async function toggleStatus(row: DiyPageListItem) {
  await diyApi.updatePage(row.id, { status: row.status === 1 ? 0 : 1 })
  await load()
}

async function remove(row: DiyPageListItem) {
  await ElMessageBox.confirm(`确定删除页面「${row.title}」？`, '提示', { type: 'warning' })
  await diyApi.deletePage(row.id)
  ElMessage.success('已删除')
  await load()
}

function goEditor(row: DiyPageListItem) {
  router.push({ path: '/diy/editor', query: { key: row.page_key, title: row.title } })
}
</script>

<style scoped lang="scss">
@import './components/decor.scss';

.dp-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}
.dp-tabs {
  display: flex;
  gap: 4px;
}
.dp-tab {
  padding: 6px 14px;
  font-size: 13px;
  color: var(--color-text-secondary);
  border-bottom: 2px solid transparent;
  cursor: pointer;
}
.dp-tab--active {
  color: var(--el-color-primary);
  font-weight: 600;
  border-bottom-color: var(--el-color-primary);
}

.hint {
  margin-top: 4px;
  font-size: 12px;
  color: var(--color-text-tertiary);
}
</style>
