<template>
    <div class="diy-links">
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">链接管理</div>
                <div class="table-actions">
                    <el-button type="primary" @click="openCreate">新增链接</el-button>
                </div>
            </div>
            <el-table v-loading="loading" :data="list" style="width: 100%">
                <el-table-column prop="label" label="名称" min-width="140" />
                <el-table-column prop="path" label="链接" min-width="260" show-overflow-tooltip />
                <el-table-column prop="category" label="分类" width="120" />
                <el-table-column prop="sort" label="排序" width="80" />
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'info'">{{
                            row.status === 1 ? '启用' : '停用'
                        }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="140">
                    <template #default="{ row }">
                        <el-button link type="primary" @click="openEdit(row as DiyLinkItem)"
                            >编辑</el-button
                        >
                        <el-button link type="danger" @click="remove(row as DiyLinkItem)"
                            >删除</el-button
                        >
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <el-dialog v-model="dialogVisible" :title="form.id ? '编辑链接' : '新增链接'" class="dlg-md">
            <el-form :model="form" label-width="72px">
                <el-form-item label="名称"><el-input v-model="form.label" /></el-form-item>
                <el-form-item label="链接"
                    ><el-input v-model="form.path" placeholder="/pages/... 或 https://..."
                /></el-form-item>
                <el-form-item label="分类"><el-input v-model="form.category" /></el-form-item>
                <el-form-item label="排序"
                    ><el-input-number v-model="form.sort" :min="0"
                /></el-form-item>
                <el-form-item label="状态">
                    <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="submit">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ElMessage, ElMessageBox } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'

import { diyApi, type DiyLinkItem } from '@/api/diy'

const list = ref<DiyLinkItem[]>([])
const loading = ref(false)
const dialogVisible = ref(false)
const form = reactive<Partial<DiyLinkItem>>({
    label: '',
    path: '',
    category: '我的链接',
    sort: 0,
    status: 1
})

async function load() {
    loading.value = true
    try {
        const res = await diyApi.listLinks()
        list.value = res.data || []
    } finally {
        loading.value = false
    }
}

function openCreate() {
    Object.assign(form, {
        id: undefined,
        label: '',
        path: '',
        category: '我的链接',
        sort: 0,
        status: 1
    })
    dialogVisible.value = true
}
function openEdit(row: DiyLinkItem) {
    Object.assign(form, row)
    dialogVisible.value = true
}
async function submit() {
    if (!form.label || !form.path) {
        ElMessage.error('名称和链接必填')
        return
    }
    if (form.id) {
        await diyApi.updateLink(form.id, form)
    } else {
        await diyApi.createLink(form)
    }
    ElMessage.success('保存成功')
    dialogVisible.value = false
    load()
}
async function remove(row: DiyLinkItem) {
    await ElMessageBox.confirm(`确认删除「${row.label}」？`, '提示', { type: 'warning' })
    await diyApi.deleteLink(row.id)
    ElMessage.success('已删除')
    load()
}

onMounted(load)
</script>
