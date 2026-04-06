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
                    <el-button type="primary" @click="handleSearch">
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
                :data="list"
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
                            @click="handleDelete(row.id, row.name)"
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
            @success="getList"
        />
    </div>
</template>

<script setup lang="ts" name="DepartmentList">
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { departmentApi } from '@/api/department'
import { useListPage } from '@/hooks/useListPage'
import { useUserStore } from '@/store'

import DeptForm from './components/DeptForm.vue'

const { t } = useI18n()
const userStore = useUserStore()

const isExpandAll = ref(true)
const tableKey = ref(0)

// 弹窗
const formVisible = ref(false)
const formData = ref<Record<string, any>>({})
const parentOptions = ref<any[]>([])

// 部门接口返回的是树形结构（无分页），用自定义 fetchFn 包装成通用 PageResult 形式
const {
    list,
    loading,
    searchForm,
    getList,
    handleSearch,
    resetSearch,
    handleDelete,
    handleStatusChange
} = useListPage<any, { keyword: string; status?: number }>({
    fetchFn: async (params) => {
        const query: Record<string, any> = {}
        if (params.keyword?.trim()) query.keyword = params.keyword.trim()
        if (params.status !== undefined) query.status = params.status
        const res = await departmentApi.getTree(query)
        const tree = (res.data || []) as any[]
        return {
            ...res,
            data: {
                list: tree,
                pagination: {
                    current_page: 1,
                    per_page: tree.length,
                    total: tree.length,
                    last_page: 1
                }
            }
        } as any
    },
    deleteFn: (id) => departmentApi.delete(id),
    updateStatusFn: (id, status) => departmentApi.updateStatus(id, status),
    defaultSearchForm: { keyword: '', status: undefined }
})

const getParentOptions = async () => {
    try {
        const res = await departmentApi.getOptions()
        // 添加顶级选项
        parentOptions.value = [{ id: 0, name: t('department.title'), children: res.data }]
    } catch {
        ElMessage.error(t('message.fetchFailed'))
    }
}

const expandAll = () => {
    isExpandAll.value = !isExpandAll.value
    tableKey.value++
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
</script>
