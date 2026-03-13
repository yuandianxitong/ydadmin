<template>
    <div class="dept-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('department.deptName')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('department.searchPlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('department.statusPlaceholder')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('common.normal')" :value="1" />
                        <el-option :label="$t('common.disable')" :value="0" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="getDeptTree">
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
                <div class="table-title">{{ $t('department.title') }}</div>
                <div class="table-actions">
                    <el-button @click="expandAll">
                        {{ isExpandAll ? $t('common.collapseAll') : $t('common.expandAll') }}
                    </el-button>
                    <el-button
                        v-has-perm="['system.department.create']"
                        type="primary"
                        @click="handleAdd()"
                    >
                        <el-icon><Plus /></el-icon>
                        {{ $t('department.addDept') }}
                    </el-button>
                </div>
            </div>

            <el-table
                :key="tableKey"
                v-loading="loading"
                :data="deptTree"
                row-key="id"
                :tree-props="{ children: 'children', hasChildren: 'hasChildren' }"
                :default-expand-all="isExpandAll"
            >
                <el-table-column :label="$t('department.deptName')" prop="name" min-width="220">
                    <template #default="{ row }">
                        <span>{{ row.name }}</span>
                        <el-tag
                            v-if="row.code"
                            size="small"
                            type="info"
                            effect="plain"
                            class="ml-2"
                            >{{ row.code }}</el-tag
                        >
                    </template>
                </el-table-column>

                <el-table-column :label="$t('department.leader')" prop="leader" width="120">
                    <template #default="{ row }">
                        <span>{{ row.leader || '-' }}</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('department.phone')" prop="phone" width="140">
                    <template #default="{ row }">
                        <span>{{ row.phone || '-' }}</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.sort')" prop="sort" width="80" />

                <el-table-column :label="$t('common.status')" prop="status" width="100">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.status"
                            :active-value="1"
                            :inactive-value="0"
                            :disabled="!userStore.hasPermission('system.department.update')"
                            @change="handleStatusChange(row)"
                        />
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.createdAt')" prop="created_at" width="160" />

                <el-table-column :label="$t('common.operation')" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-has-perm="['system.department.create']"
                            type="primary"
                            size="small"
                            text
                            @click="handleAdd(row)"
                        >
                            {{ $t('common.add') }}
                        </el-button>
                        <el-button
                            v-has-perm="['system.department.update']"
                            type="primary"
                            size="small"
                            text
                            @click="handleEdit(row)"
                        >
                            {{ $t('common.edit') }}
                        </el-button>
                        <el-button
                            v-has-perm="['system.department.delete']"
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
        <DeptForm
            v-model="formVisible"
            :form-data="formData"
            :parent-options="parentOptions"
            @success="getDeptTree"
        />
    </div>
</template>

<script setup lang="ts" name="DepartmentList">
import { Delete, Edit, Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { departmentApi } from '@/api/department'
import { useUserStore } from '@/store'

import DeptForm from './components/DeptForm.vue'

const { t } = useI18n()
const userStore = useUserStore()

const searchForm = reactive({ keyword: '', status: undefined as number | undefined })
const deptTree = ref<any[]>([])
const loading = ref(false)
const isExpandAll = ref(true)
const tableKey = ref(0)

// 弹窗
const formVisible = ref(false)
const formData = ref<Record<string, any>>({})
const parentOptions = ref<any[]>([])

const getDeptTree = async () => {
    try {
        loading.value = true
        const params: Record<string, any> = {}
        if (searchForm.keyword?.trim()) params.keyword = searchForm.keyword.trim()
        if (searchForm.status !== undefined) params.status = searchForm.status
        const res = await departmentApi.getTree(params)
        deptTree.value = res.data
    } catch {
        ElMessage.error(t('message.fetchFailed'))
    } finally {
        loading.value = false
    }
}

const getParentOptions = async () => {
    try {
        const res = await departmentApi.getOptions()
        // 添加顶级选项
        parentOptions.value = [{ id: 0, name: t('department.title'), children: res.data }]
    } catch {
        ElMessage.error(t('message.fetchFailed'))
    }
}

const resetSearch = () => {
    Object.assign(searchForm, { keyword: '', status: undefined })
    getDeptTree()
}

const expandAll = () => {
    isExpandAll.value = !isExpandAll.value
    tableKey.value++
}

const handleStatusChange = async (row: any) => {
    try {
        await departmentApi.updateStatus(row.id, row.status)
        ElMessage.success(t('message.statusUpdateSuccess'))
    } catch {
        row.status = row.status === 1 ? 0 : 1
        ElMessage.error(t('message.statusUpdateFailed'))
    }
}

const handleAdd = (parent?: any) => {
    formData.value = { parent_id: parent?.id || 0, status: 1, sort: 0 }
    getParentOptions()
    formVisible.value = true
}

const handleEdit = (row: any) => {
    formData.value = { ...row }
    getParentOptions()
    formVisible.value = true
}

const handleDelete = async (row: any) => {
    try {
        await ElMessageBox.confirm(t('message.deleteConfirmName', { name: row.name }), t('message.confirmDelete'), {
            confirmButtonText: t('common.confirm'),
            cancelButtonText: t('common.cancel'),
            type: 'warning'
        })
        await departmentApi.delete(row.id)
        ElMessage.success(t('message.deleteSuccess'))
        getDeptTree()
    } catch (error) {
        if (error !== 'cancel') ElMessage.error(t('common.error'))
    }
}

onMounted(() => {
    getDeptTree()
})
</script>

<style lang="scss" scoped>
.dept-container {
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
