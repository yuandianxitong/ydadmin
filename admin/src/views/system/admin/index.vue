<template>
    <div class="admin-container">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ $t('admin.title') }}</h1>
                <div class="page-desc">{{ $t('proPage.system_admin_index') }}</div>
            </div>
            <div class="page-actions">
                <el-button v-has-perm="['system.admin.create']" type="primary" @click="handleAdd">
                    <i class="i-svg:plus" />
                    {{ $t('admin.addAdmin') }}
                </el-button>
            </div>
        </div>
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('admin.username') + '/' + $t('admin.nickname')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('admin.searchPlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('admin.statusPlaceholder')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('common.normal')" :value="1" />
                        <el-option :label="$t('common.disable')" :value="0" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('admin.department')">
                    <el-input
                        v-model="searchForm.department"
                        :placeholder="$t('admin.deptPlaceholder')"
                        clearable
                        style="width: 150px"
                    />
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
            :title="$t('admin.title')"
            storage-key="system-admin-list"
            :columns="[
                {
                    key: 'id',
                    label: $t('proPage.table_system_admin_index_vue_id'),
                    width: 80,
                    required: true
                },
                {
                    key: 'avatar',
                    label: $t('proPage.table_system_admin_index_vue_avatar'),
                    width: 100
                },
                {
                    key: 'username',
                    label: $t('proPage.table_system_admin_index_vue_username'),
                    minWidth: 120
                },
                {
                    key: 'nickname',
                    label: $t('proPage.table_system_admin_index_vue_nickname'),
                    width: 140
                },
                {
                    key: 'email',
                    label: $t('proPage.table_system_admin_index_vue_email'),
                    width: 180,
                    showOverflowTooltip: true
                },
                {
                    key: 'mobile',
                    label: $t('proPage.table_system_admin_index_vue_mobile'),
                    width: 160
                },
                {
                    key: 'department',
                    label: $t('proPage.table_system_admin_index_vue_department'),
                    width: 140
                },
                {
                    key: 'position',
                    label: $t('proPage.table_system_admin_index_vue_position'),
                    width: 120,
                    defaultVisible: false
                },
                {
                    key: 'roles',
                    label: $t('proPage.table_system_admin_index_vue_roles'),
                    width: 200
                },
                {
                    key: 'status',
                    label: $t('proPage.table_system_admin_index_vue_status'),
                    width: 100
                },
                {
                    key: 'last_login_time',
                    label: $t('proPage.table_system_admin_index_vue_last_login_time'),
                    width: 160,
                    defaultVisible: false
                },
                {
                    key: 'created_at',
                    label: $t('proPage.table_system_admin_index_vue_created_at'),
                    width: 160,
                    defaultVisible: false
                },
                {
                    key: 'action',
                    label: $t('proPage.table_system_admin_index_vue_action'),
                    width: 220,
                    fixed: 'right',
                    required: true
                }
            ]"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            selection
            @selection-change="handleSelectionChange"
            ><template #avatar="{ row }">
                <el-avatar
                    :size="40"
                    :src="appStore.getImageUrl(row.avatar)"
                    :alt="row.nickname || row.username"
                >
                    {{ (row.nickname || row.username)?.[0] }}
                </el-avatar>
            </template>
            <template #roles="{ row }">
                <el-tag
                    v-for="role in row.roles"
                    :key="role.id"
                    size="small"
                    effect="light"
                    class="role-tag"
                >
                    {{ role.title }}
                </el-tag>
                <span v-if="!row.roles?.length" class="text-gray-400">{{
                    $t('common.noRole')
                }}</span>
            </template>
            <template #status="{ row }">
                <el-switch
                    v-model="row.status"
                    :active-value="1"
                    :inactive-value="0"
                    :disabled="row.id == 1 || !userStore.hasPermission('system.admin.update')"
                    @change="handleStatusChange(row)"
                />
            </template>
            <template #action="{ row }">
                <el-button
                    v-has-perm="['system.admin.update']"
                    type="primary"
                    size="small"
                    text
                    @click="handleEdit(row as AdminInfo)"
                >
                    {{ $t('common.edit') }}
                </el-button>
                <el-button
                    v-has-perm="['system.admin.update']"
                    type="warning"
                    size="small"
                    text
                    @click="handleResetPassword(row as AdminInfo)"
                >
                    {{ $t('common.resetPassword') }}
                </el-button>
                <el-button
                    v-if="row.id != 1"
                    v-has-perm="['system.admin.delete']"
                    type="danger"
                    size="small"
                    text
                    @click="handleDelete(row.id, row.username)"
                >
                    {{ $t('common.delete') }}
                </el-button>
            </template>
            <template #batchActions
                ><el-button
                    v-has-perm="['system.admin.delete']"
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
        <AdminForm
            v-model="formVisible"
            :form-data="formData"
            :role-options="roleOptions"
            :department-options="departmentOptions"
            @success="getList"
        />

        <!-- 重置密码弹窗 -->
        <ResetPasswordDialog
            v-model="resetPasswordVisible"
            :admin-info="currentAdmin"
            @success="getList"
        />
    </div>
</template>

<script setup lang="ts" name="AdminList">

import { onMounted, ref } from 'vue'

import { adminApi } from '@/api/admin'
import { departmentApi } from '@/api/department'
import { roleApi } from '@/api/role'
import { useListPage } from '@/hooks/useListPage'
import { useUserStore } from '@/store'
import useAppStore from '@/store/modules/app.store'
import type { AdminInfo, RoleOption } from '@/types/system'

import AdminForm from './components/AdminForm.vue'
import ResetPasswordDialog from './components/ResetPasswordDialog.vue'

const userStore = useUserStore()
const appStore = useAppStore()

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
} = useListPage<AdminInfo, { keyword: string; status?: number; department: string }>({
    fetchFn: (params) => adminApi.getAdminList(params),
    deleteFn: (id) => adminApi.deleteAdmin(id),
    batchDeleteFn: (ids) => adminApi.batchDeleteAdmin({ ids }),
    updateStatusFn: (id, status) => adminApi.updateAdminStatus(id, { status }),
    defaultSearchForm: {
        keyword: '',
        status: undefined,
        department: ''
    }
})

// 表格选择
const multipleSelection = ref<AdminInfo[]>([])

// 弹窗相关
const formVisible = ref(false)
const formData = ref<Partial<AdminInfo>>({})
const resetPasswordVisible = ref(false)
const currentAdmin = ref<AdminInfo | null>(null)

// 角色选项
const roleOptions = ref<RoleOption[]>([])

// 部门选项
const departmentOptions = ref<any[]>([])

// 获取角色选项
const getRoleOptions = async () => {
    try {
        const response = await roleApi.getRoleOptions()
        roleOptions.value = response.data
    } catch (error) {
        console.error('获取角色选项失败:', error)
    }
}

// 获取部门选项
const getDepartmentOptions = async () => {
    try {
        const response = await departmentApi.getOptions()
        departmentOptions.value = response.data
    } catch (error) {
        console.error('获取部门选项失败:', error)
    }
}

// 表格选择变化
const handleSelectionChange = (selection: AdminInfo[]) => {
    multipleSelection.value = selection
}

// 新增管理员
const handleAdd = () => {
    formData.value = {
        status: 1
    }
    formVisible.value = true
}

// 编辑管理员
const handleEdit = (row: AdminInfo) => {
    formData.value = { ...row }
    formVisible.value = true
}

// 重置密码
const handleResetPassword = (row: AdminInfo) => {
    currentAdmin.value = row
    resetPasswordVisible.value = true
}

onMounted(() => {
    getRoleOptions()
    getDepartmentOptions()
})
</script>

<style lang="scss" scoped>
.admin-container {
    .role-tag {
        margin-right: 4px;
        margin-bottom: 4px;
    }
}
</style>
