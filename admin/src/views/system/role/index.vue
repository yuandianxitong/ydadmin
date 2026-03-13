<template>
    <div class="role-container">
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
                    <el-button type="primary" @click="getRoleList">
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
                <div class="table-title">{{ $t('role.title') }}</div>
                <div class="table-actions">
                    <el-button
                        v-has-perm="['system.role.create']"
                        type="primary"
                        @click="handleAdd"
                    >
                        <el-icon><Plus /></el-icon>
                        {{ $t('role.addRole') }}
                    </el-button>
                    <el-button
                        v-has-perm="['system.role.delete']"
                        type="danger"
                        :disabled="!multipleSelection.length"
                        @click="handleBatchDelete"
                    >
                        <el-icon><Delete /></el-icon>
                        {{ $t('common.batchDelete') }}
                    </el-button>
                </div>
            </div>

            <!-- 表格 -->
            <el-table
                v-loading="loading"
                :data="roleList"
                @selection-change="handleSelectionChange"
            >
                <el-table-column type="selection" width="55" />

                <el-table-column label="ID" prop="id" width="80" />

                <el-table-column :label="$t('role.roleCode')" prop="name" width="150" />

                <el-table-column :label="$t('role.roleName')" prop="title" width="150" />

                <el-table-column :label="$t('role.description')" prop="description" show-overflow-tooltip />

                <el-table-column :label="$t('role.dataScope')" prop="data_scope" width="120">
                    <template #default="{ row }">
                        <el-tag :type="getDataScopeTagType(row.data_scope)" size="small">
                            {{ getDataScopeText(row.data_scope) }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.systemRole')" prop="is_system" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.is_system ? 'danger' : 'info'" size="small">
                            {{ row.is_system ? $t('common.yes') : $t('common.no') }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.status')" width="100">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.status"
                            :active-value="1"
                            :inactive-value="0"
                            :disabled="
                                !userStore.hasPermission('system.role.update') || row.is_system
                            "
                            @change="handleStatusChange(row)"
                        />
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.createdAt')" prop="created_at" width="160" />

                <el-table-column :label="$t('common.operation')" width="250" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-if="!row.is_system"
                            v-has-perm="['system.role.update']"
                            type="primary"
                            size="small"
                            text
                            @click="handleEdit(row)"
                        >
                            {{ $t('common.edit') }}
                        </el-button>
                        <el-button
                            v-has-perm="['system.role.permission']"
                            type="success"
                            size="small"
                            text
                            @click="handleAssignPermissions(row)"
                        >
                            {{ $t('common.assignPermission') }}
                        </el-button>
                        <el-button
                            v-if="!row.is_system"
                            v-has-perm="['system.role.delete']"
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

            <!-- 分页 -->
            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                class="pagination"
                @size-change="getRoleList"
                @current-change="getRoleList"
            />
        </el-card>

        <!-- 新增/编辑弹窗 -->
        <RoleForm v-model="formVisible" :form-data="formData" @success="getRoleList" />

        <!-- 权限分配弹窗 -->
        <AssignPermissionsDialog
            v-model="assignVisible"
            :role-info="currentRole"
            @success="getRoleList"
        />
    </div>
</template>

<script setup lang="ts" name="RoleList">
import { Delete, Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { roleApi } from '@/api/role'
import { useUserStore } from '@/store'
import type { RoleInfo, RoleQuery } from '@/types/api'

import AssignPermissionsDialog from './components/AssignPermissionsDialog.vue'
import RoleForm from './components/RoleForm.vue'

const { t } = useI18n()
const userStore = useUserStore()

// 搜索表单
const searchForm = reactive<RoleQuery>({
    keyword: '',
    status: undefined,
    page: 1,
    limit: 20
})

// 角色列表
const roleList = ref<RoleInfo[]>([])
const loading = ref(false)

// 分页信息
const pagination = reactive({
    page: 1,
    limit: 20,
    total: 0
})

// 表格选择
const multipleSelection = ref<RoleInfo[]>([])

// 弹窗相关
const formVisible = ref(false)
const formData = ref<Partial<RoleInfo>>({})
const assignVisible = ref(false)
const currentRole = ref<RoleInfo | null>(null)

// 获取角色列表
const getRoleList = async () => {
    try {
        loading.value = true
        const params = {
            ...searchForm,
            page: pagination.page,
            limit: pagination.limit
        }

        const response = await roleApi.getRoleList(params)
        roleList.value = response.data.list
        pagination.total = response.data.pagination.total
    } catch (error) {
        console.error('获取角色列表失败:', error)
    } finally {
        loading.value = false
    }
}

// 重置搜索
const resetSearch = () => {
    Object.assign(searchForm, {
        keyword: '',
        status: undefined
    })
    pagination.page = 1
    getRoleList()
}

// 表格选择变化
const handleSelectionChange = (selection: RoleInfo[]) => {
    multipleSelection.value = selection
}

// 状态变更
const handleStatusChange = async (row: RoleInfo) => {
    try {
        await roleApi.updateRoleStatus(row.id, { status: row.status })
        ElMessage.success(t('message.statusUpdateSuccess'))
    } catch (error) {
        // 恢复状态
        row.status = row.status === 1 ? 0 : 1
        console.error('状态更新失败:', error)
    }
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

// 删除角色
const handleDelete = async (row: RoleInfo) => {
    try {
        await ElMessageBox.confirm(
            t('message.deleteConfirmName', { name: row.title }),
            t('message.confirmDelete'),
            {
                confirmButtonText: t('common.confirm'),
                cancelButtonText: t('common.cancel'),
                type: 'warning'
            }
        )

        await roleApi.deleteRole(row.id)
        ElMessage.success(t('message.deleteSuccess'))
        getRoleList()
    } catch (error) {
        if (error !== 'cancel') {
            console.error('删除失败:', error)
        }
    }
}

// 批量删除
const handleBatchDelete = async () => {
    try {
        await ElMessageBox.confirm(
            t('message.batchDeleteConfirmCount', { count: multipleSelection.value.length, type: t('role.title') }),
            t('message.confirmBatchDelete'),
            {
                confirmButtonText: t('common.confirm'),
                cancelButtonText: t('common.cancel'),
                type: 'warning'
            }
        )

        const ids = multipleSelection.value.map((item) => item.id)
        await roleApi.batchDeleteRole({ ids })
        ElMessage.success(t('message.batchDeleteSuccess'))
        getRoleList()
    } catch (error) {
        if (error !== 'cancel') {
            console.error('批量删除失败:', error)
        }
    }
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
const getDataScopeTagType = (dataScope: number) => {
    const typeMap: Record<number, string> = {
        1: 'danger',
        2: 'warning',
        3: 'info',
        4: 'success',
        5: 'primary'
    }
    return typeMap[dataScope] || 'info'
}

onMounted(() => {
    getRoleList()
})
</script>

<style lang="scss" scoped>
.role-container {
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

        .pagination {
            margin-top: 16px;
            display: flex;
            justify-content: flex-end;
        }
    }
}
</style>
