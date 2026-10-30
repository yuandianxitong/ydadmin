<template>
    <div class="role-container">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ $t('role.title') }}</h1>
                <div class="page-desc">{{ $t('proPage.system_role_index') }}</div>
            </div>
            <div class="page-actions">
                <el-button v-has-perm="['system.role.create']" type="primary" @click="handleAdd">
                    <i class="i-svg:plus" />
                    {{ $t('role.addRole') }}
                </el-button>
            </div>
        </div>
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('role.roleName')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('role.searchPlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('role.statusPlaceholder')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('common.normal')" :value="1" />
                        <el-option :label="$t('common.disable')" :value="0" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">
                        <i class="i-svg:search" />
                        {{ $t('common.search') }}
                    </el-button>
                    <el-button @click="resetSearch">
                        <i class="i-svg:refresh-cw" />
                        {{ $t('common.reset') }}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 操作区域 -->
        <ProTable
            :title="$t('role.title')"
            storage-key="system-role-list"
            :columns="[
                {
                    key: 'id',
                    label: $t('proPage.table_system_role_index_vue_id'),
                    width: 80,
                    required: true
                },
                { key: 'name', label: $t('proPage.table_system_role_index_vue_name'), width: 150 },
                {
                    key: 'title',
                    label: $t('proPage.table_system_role_index_vue_title'),
                    width: 150
                },
                {
                    key: 'description',
                    label: $t('proPage.table_system_role_index_vue_description'),
                    showOverflowTooltip: true
                },
                {
                    key: 'data_scope',
                    label: $t('proPage.table_system_role_index_vue_data_scope'),
                    width: 150
                },
                {
                    key: 'is_system',
                    label: $t('proPage.table_system_role_index_vue_is_system'),
                    width: 120
                },
                {
                    key: 'status',
                    label: $t('proPage.table_system_role_index_vue_status'),
                    width: 100
                },
                {
                    key: 'created_at',
                    label: $t('proPage.table_system_role_index_vue_created_at'),
                    width: 200
                },
                {
                    key: 'action',
                    label: $t('proPage.table_system_role_index_vue_action'),
                    width: 250,
                    fixed: 'right',
                    required: true
                }
            ]"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            selection
            @selection-change="handleSelectionChange"
            ><template #data_scope="{ row }">
                <el-tag :type="getDataScopeTagType(row.data_scope)" size="small">
                    {{ getDataScopeText(row.data_scope) }}
                </el-tag>
            </template>
            <template #is_system="{ row }">
                <el-tag :type="row.is_system ? 'danger' : 'info'" size="small">
                    {{ row.is_system ? $t('common.yes') : $t('common.no') }}
                </el-tag>
            </template>
            <template #status="{ row }">
                <el-switch
                    v-model="row.status"
                    :active-value="1"
                    :inactive-value="0"
                    :disabled="!userStore.hasPermission('system.role.update') || row.is_system"
                    @change="handleStatusChange(row)"
                />
            </template>
            <template #action="{ row }">
                <el-button
                    v-if="!row.is_system"
                    v-has-perm="['system.role.update']"
                    type="primary"
                    size="small"
                    text
                    @click="handleEdit(row as RoleInfo)"
                >
                    {{ $t('common.edit') }}
                </el-button>
                <el-button
                    v-has-perm="['system.role.permission']"
                    type="success"
                    size="small"
                    text
                    @click="handleAssignPermissions(row as RoleInfo)"
                >
                    {{ $t('common.assignPermission') }}
                </el-button>
                <el-button
                    v-if="!row.is_system"
                    v-has-perm="['system.role.delete']"
                    type="danger"
                    size="small"
                    text
                    @click="handleDelete(row.id, row.title)"
                >
                    {{ $t('common.delete') }}
                </el-button>
            </template>
            <template #batchActions
                ><el-button
                    v-has-perm="['system.role.delete']"
                    type="danger"
                    :disabled="!multipleSelection.length"
                    @click="handleBatchDelete(multipleSelection.map((item) => item.id))"
                >
                    <i class="i-svg:trash-2" />
                    {{ $t('common.batchDelete') }}
                </el-button></template
            >
            <template #pagination
                ><el-pagination
                    small
                    v-model:current-page="pagination.page"
                    v-model:page-size="pagination.limit"
                    :total="pagination.total"
                    :page-sizes="[10, 20, 50, 100]"
                    layout="prev, pager, next, sizes"
                    class="pro-pagination"
                    @size-change="handleSizeChange"
                    @current-change="handlePageChange" /></template
        ></ProTable>

        <!-- 新增/编辑弹窗 -->
        <RoleForm v-model="formVisible" :form-data="formData" @success="getList" />

        <!-- 权限分配弹窗 -->
        <AssignPermissionsDialog
            v-model="assignVisible"
            :role-info="currentRole"
            @success="getList"
        />
    </div>
</template>

<script setup lang="ts" name="RoleList">

import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { roleApi } from '@/api/role'
import { useListPage } from '@/hooks/useListPage'
import { useUserStore } from '@/store'
import type { RoleInfo } from '@/types/system'

import AssignPermissionsDialog from './components/AssignPermissionsDialog.vue'
import RoleForm from './components/RoleForm.vue'

const { t } = useI18n()
const userStore = useUserStore()

// 使用统一的列表页 composable
const {
    list,
    loading,
    pagination,
    searchForm,
    getList,
    handleSearch,
    resetSearch,
    handleSizeChange,
    handlePageChange,
    handleDelete,
    handleBatchDelete,
    handleStatusChange
} = useListPage<RoleInfo, { keyword: string; status?: number }>({
    fetchFn: (params) => roleApi.getRoleList(params),
    deleteFn: (id) => roleApi.deleteRole(id),
    batchDeleteFn: (ids) => roleApi.batchDeleteRole({ ids }),
    updateStatusFn: (id, status) => roleApi.updateRoleStatus(id, { status }),
    defaultSearchForm: {
        keyword: '',
        status: undefined
    }
})

// 表格选择
const multipleSelection = ref<RoleInfo[]>([])

// 弹窗相关
const formVisible = ref(false)
const formData = ref<Partial<RoleInfo>>({})
const assignVisible = ref(false)
const currentRole = ref<RoleInfo | null>(null)

// 表格选择变化
const handleSelectionChange = (selection: RoleInfo[]) => {
    multipleSelection.value = selection
}

// 新增角色
const handleAdd = () => {
    formData.value = {
        status: 1,
        data_scope: 1
    }
    formVisible.value = true
}

// 编辑角色
const handleEdit = (row: RoleInfo) => {
    formData.value = { ...row }
    formVisible.value = true
}

// 分配权限
const handleAssignPermissions = (row: RoleInfo) => {
    currentRole.value = row
    assignVisible.value = true
}

// 获取数据权限文本
const getDataScopeText = (dataScope: number) => {
    const scopeMap: Record<number, string> = {
        1: t('role.dataScopeOptions.all'),
        2: t('role.dataScopeOptions.department'),
        3: t('role.dataScopeOptions.departmentAndBelow'),
        4: t('role.dataScopeOptions.self'),
        5: t('role.dataScopeOptions.custom')
    }
    return scopeMap[dataScope] || t('common.noData')
}

// 获取数据权限标签类型
const getDataScopeTagType = (
    dataScope: number
): 'primary' | 'success' | 'warning' | 'info' | 'danger' => {
    const typeMap: Record<number, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
        1: 'danger',
        2: 'warning',
        3: 'info',
        4: 'success',
        5: 'primary'
    }
    return typeMap[dataScope] || 'info'
}
</script>
