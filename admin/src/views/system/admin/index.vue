<template>
    <div class="admin-container">
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
                <div class="table-title">{{ $t('admin.title') }}</div>
                <div class="table-actions">
                    <el-button
                        v-has-perm="['system.admin.create']"
                        type="primary"
                        @click="handleAdd"
                    >
                        <el-icon><Plus /></el-icon>
                        {{ $t('admin.addAdmin') }}
                    </el-button>
                    <el-button
                        v-has-perm="['system.admin.delete']"
                        type="danger"
                        :disabled="!multipleSelection.length"
                        @click="handleBatchDelete(multipleSelection.map((item) => item.id))"
                    >
                        <el-icon><Delete /></el-icon>
                        {{ $t('common.batchDelete') }}
                    </el-button>
                </div>
            </div>

            <!-- 表格 -->
            <el-table v-loading="loading" :data="list" @selection-change="handleSelectionChange">
                <el-table-column type="selection" width="55" />

                <el-table-column label="ID" prop="id" width="80" />

                <el-table-column :label="$t('admin.avatar')" width="80">
                    <template #default="{ row }">
                        <el-avatar
                            :size="40"
                            :src="appStore.getImageUrl(row.avatar)"
                            :alt="row.nickname || row.username"
                        >
                            {{ (row.nickname || row.username)?.[0] }}
                        </el-avatar>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('admin.username')" prop="username" width="120" />

                <el-table-column :label="$t('admin.nickname')" prop="nickname" width="120" />

                <el-table-column
                    :label="$t('admin.email')"
                    prop="email"
                    width="180"
                    show-overflow-tooltip
                />

                <el-table-column :label="$t('admin.mobile')" prop="mobile" width="140" />

                <el-table-column :label="$t('admin.department')" prop="department" width="120" />

                <el-table-column :label="$t('admin.position')" prop="position" width="120" />

                <el-table-column :label="$t('admin.role')" width="200">
                    <template #default="{ row }">
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
                </el-table-column>

                <el-table-column :label="$t('common.status')" width="100">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.status"
                            :active-value="1"
                            :inactive-value="0"
                            :disabled="
                                row.id == 1 || !userStore.hasPermission('system.admin.update')
                            "
                            @change="handleStatusChange(row)"
                        />
                    </template>
                </el-table-column>

                <el-table-column
                    :label="$t('common.lastLogin')"
                    prop="last_login_time"
                    width="180"
                />

                <el-table-column :label="$t('common.createdAt')" prop="created_at" width="180" />

                <el-table-column :label="$t('common.operation')" width="220" fixed="right">
                    <template #default="{ row }">
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
                </el-table-column>
            </el-table>

            <!-- 分页 -->
            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                class="pagination"
                @size-change="handleSizeChange"
                @current-change="handlePageChange"
            />
        </el-card>

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
import { Delete, Plus, Refresh, Search } from '@element-plus/icons-vue'
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
