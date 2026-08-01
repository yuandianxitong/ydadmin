<template>
  <el-drawer v-model="visible" title="历史版本" size="380px" @open="load">
    <el-table :data="versions" v-loading="loading" empty-text="暂无历史版本">
      <el-table-column prop="version_no" label="版本" width="70" />
      <el-table-column prop="created_at" label="发布时间" />
      <el-table-column label="操作" width="90">
        <template #default="{ row }">
          <el-button size="small" :loading="restoring === row.id" :disabled="restoring !== null" @click="doRestore(row.id)">回滚</el-button>
        </template>
      </el-table-column>
    </el-table>
  </el-drawer>
</template>
<script setup lang="ts">
import { ref } from 'vue'
import { ElMessage } from 'element-plus'
import { diyApi, type DiyVersion } from '@/api/diy'
const visible = defineModel<boolean>({ required: true })
const props = defineProps<{ pageKey: string }>()
const emit = defineEmits<{ (e: 'restored'): void }>()
const versions = ref<DiyVersion[]>([])
const loading = ref(false)
const restoring = ref<number | null>(null)
async function load() {
  loading.value = true
  try {
    const res = await diyApi.listPageVersions(props.pageKey)
    versions.value = res.data || []
  } catch {
    versions.value = []
  } finally {
    loading.value = false
  }
}
async function doRestore(id: number) {
  if (restoring.value !== null) return
  restoring.value = id
  try {
    await diyApi.restorePageVersion(props.pageKey, id)
    ElMessage.success('已回滚到草稿')
    visible.value = false
    emit('restored')
  } catch {
    // 错误已由响应拦截器提示
  } finally {
    restoring.value = null
  }
}
</script>
