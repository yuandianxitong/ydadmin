<template>
    <el-drawer v-model="visible" title="历史版本" size="380px" @open="load">
        <el-alert
            title="载入版本只会替换草稿，确认后需重新发布才会对外生效。"
            type="info"
            :closable="false"
            class="mb-4"
        />
        <el-table
            v-loading="loading"
            :data="versions"
            empty-text="暂无历史版本；首次保存修改前会保留初始快照"
        >
            <el-table-column prop="version_no" label="版本" width="70" />
            <el-table-column prop="created_at" label="保存时间" />
            <el-table-column prop="note" label="说明" />
            <el-table-column label="操作" width="110">
                <template #default="{ row }">
                    <el-button
                        size="small"
                        :loading="restoring === row.id"
                        :disabled="restoring !== null"
                        @click="doRestore(row.id)"
                        >载入草稿</el-button
                    >
                </template>
            </el-table-column>
        </el-table>
    </el-drawer>
</template>
<script setup lang="ts">
import { ElMessage, ElMessageBox } from 'element-plus'
import { ref } from 'vue'

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
        await ElMessageBox.confirm(
            '将替换当前草稿中的未发布修改，载入后需重新发布才会生效。',
            '载入历史版本',
            { type: 'warning' }
        )
        await diyApi.restorePageVersion(props.pageKey, id)
        ElMessage.success('已载入草稿，请确认后点击发布')
        visible.value = false
        emit('restored')
    } catch {
        // 错误已由响应拦截器提示
    } finally {
        restoring.value = null
    }
}
</script>
