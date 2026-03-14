<template>
    <div class="region-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('regionMgmt.regionName')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('regionMgmt.namePlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="getTreeData">
                        <el-icon><Search /></el-icon>
                        {{ $t('common.search') }}
                    </el-button>
                    <el-button @click="resetSearch">
                        <el-icon><Refresh /></el-icon>
                        {{ $t('common.reset') }}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 操作区域 -->
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">{{ $t('regionMgmt.title') }}</div>
                <div class="table-actions">
                    <el-button
                        v-has-perm="['region.create']"
                        type="primary"
                        @click="handleAdd"
                    >
                        <el-icon><Plus /></el-icon>
                        {{ $t('regionMgmt.addRegion') }}
                    </el-button>
                </div>
            </div>

            <el-table
                v-loading="loading"
                :data="filteredTreeData"
                row-key="id"
                :tree-props="{ children: 'children' }"
                border
                default-expand-all
            >
                <el-table-column :label="$t('regionMgmt.regionName')" prop="name" min-width="200" />

                <el-table-column :label="$t('regionMgmt.regionCode')" prop="code" width="150" />

                <el-table-column :label="$t('regionMgmt.level')" prop="level" width="120">
                    <template #default="{ row }">
                        <el-tag :type="levelTagMap[row.level] || 'info'" size="small">
                            {{ levelTextMap[row.level] || row.level }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.sort')" prop="sort" width="100" />

                <el-table-column :label="$t('common.status')" prop="status" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                            {{ row.status === 1 ? $t('common.enable') : $t('common.disable') }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.operation')" width="220" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-has-perm="['region.create']"
                            type="primary"
                            size="small"
                            text
                            @click="handleAddChild(row)"
                        >
                            {{ $t('regionMgmt.addChildRegion') }}
                        </el-button>
                        <el-button
                            v-has-perm="['region.update']"
                            type="primary"
                            size="small"
                            text
                            @click="handleEdit(row)"
                        >
                            {{ $t('common.edit') }}
                        </el-button>
                        <el-button
                            v-has-perm="['region.delete']"
                            type="danger"
                            size="small"
                            text
                            @click="handleDelete(row)"
                        >
                            {{ $t('common.delete') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 表单弹窗 -->
        <RegionForm v-model="formVisible" :form-data="formData" @success="getTreeData" />
    </div>
</template>

<script setup lang="ts" name="RegionList">
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { regionApi } from '@/api/region'

import RegionForm from './components/RegionForm.vue'

const { t } = useI18n()

const searchForm = reactive({ keyword: '' })
const treeData = ref<any[]>([])
const loading = ref(false)

const formVisible = ref(false)
const formData = ref<Record<string, any>>({})

const levelTextMap = computed(() => ({
    1: t('regionMgmt.levelOptions.province'),
    2: t('regionMgmt.levelOptions.city'),
    3: t('regionMgmt.levelOptions.district'),
    4: t('regionMgmt.levelOptions.street')
} as Record<number, string>))
const levelTagMap: Record<number, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
    1: 'primary',
    2: 'success',
    3: 'warning',
    4: 'info'
}

const filterTree = (nodes: any[], keyword: string): any[] => {
    if (!keyword) return nodes
    return nodes.reduce((acc: any[], node) => {
        const children = node.children ? filterTree(node.children, keyword) : []
        if (node.name.includes(keyword) || node.code?.includes(keyword) || children.length > 0) {
            acc.push({ ...node, children: children.length > 0 ? children : node.children })
        }
        return acc
    }, [])
}

const filteredTreeData = computed(() => {
    return filterTree(treeData.value, searchForm.keyword?.trim() || '')
})

const getTreeData = async () => {
    try {
        loading.value = true
        const res = await regionApi.getTree()
        treeData.value = res.data
    } catch {
        ElMessage.error(t('message.fetchFailed'))
    } finally {
        loading.value = false
    }
}

const resetSearch = () => {
    Object.assign(searchForm, { keyword: '' })
    getTreeData()
}

const handleAdd = () => {
    formData.value = { parent_id: 0, level: 1, sort: 0, status: 1 }
    formVisible.value = true
}

const handleAddChild = (row: any) => {
    formData.value = { parent_id: row.id, parent_name: row.name, level: (row.level || 0) + 1, sort: 0, status: 1 }
    formVisible.value = true
}

const handleEdit = (row: any) => {
    formData.value = { ...row }
    formVisible.value = true
}

const handleDelete = async (row: any) => {
    try {
        await ElMessageBox.confirm(t('message.deleteConfirmName', { name: row.name }), t('message.confirmDelete'), {
            confirmButtonText: t('common.confirm'),
            cancelButtonText: t('common.cancel'),
            type: 'warning'
        })
        await regionApi.delete(row.id)
        ElMessage.success(t('message.deleteSuccess'))
        getTreeData()
    } catch (error) {
        if (error !== 'cancel') ElMessage.error(t('common.error'))
    }
}

onMounted(() => {
    getTreeData()
})
</script>

<style lang="scss" scoped>
.region-container {
    .search-card {
        margin-bottom: 16px;

        .search-form {
            margin: 0;
        }
    }

    .table-card {
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;

            .table-title {
                font-size: 16px;
                font-weight: 600;
                color: var(--el-text-color-primary);
            }

            .table-actions {
                display: flex;
                gap: 8px;
            }
        }
    }
}
</style>
