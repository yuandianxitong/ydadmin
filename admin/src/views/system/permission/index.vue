<template>
    <div class="permission-container">
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('log.keyword')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('permission.searchPlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item :label="$t('permission.group')">
                    <el-input
                        v-model="searchForm.group"
                        :placeholder="$t('permission.groupPlaceholder')"
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

        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">{{ $t('permission.title') }}</div>
                <div class="table-actions">
                    <el-button
                        v-has-perm="['system.permission.create']"
                        type="primary"
                        @click="handleAdd"
                    >
                        <el-icon><Plus /></el-icon>
                        {{ $t('permission.addPermission') }}
                    </el-button>
                </div>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column :label="$t('common.id')" prop="id" width="80" />
                <el-table-column
                    :label="$t('permission.permCode')"
                    prop="name"
                    min-width="180"
                    show-overflow-tooltip
                />
                <el-table-column :label="$t('permission.permName')" prop="title" width="150" />
                <el-table-column :label="$t('permission.group')" prop="group" width="120" />
                <el-table-column
                    :label="$t('permission.description')"
                    prop="description"
                    min-width="180"
                    show-overflow-tooltip
                />
                <el-table-column :label="$t('common.status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
                            {{ row.status === 1 ? $t('common.enable') : $t('common.disable') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('common.sort')" prop="sort" width="80" />
                <el-table-column :label="$t('common.createdAt')" prop="created_at" width="170" />
                <el-table-column :label="$t('common.operation')" width="160" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-has-perm="['system.permission.update']"
                            type="primary"
                            size="small"
                            text
                            @click="handleEdit(row)"
                        >
                            {{ $t('common.edit') }}
                        </el-button>
                        <el-button
                            v-has-perm="['system.permission.delete']"
                            type="danger"
                            size="small"
                            text
                            @click="handleDelete(row.id, row.title)"
                        >
                            {{ $t('common.delete') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

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
        <el-dialog
            v-model="formVisible"
            :title="formData.id ? $t('permission.editPermission') : $t('permission.addPermission')"
            width="500px"
            destroy-on-close
        >
            <el-form ref="formRef" :model="formData" :rules="rules" label-width="90px">
                <el-form-item :label="$t('permission.permCode')" prop="name">
                    <el-input
                        v-model="formData.name"
                        :placeholder="$t('permission.codePlaceholder')"
                    />
                </el-form-item>
                <el-form-item :label="$t('permission.permName')" prop="title">
                    <el-input
                        v-model="formData.title"
                        :placeholder="$t('permission.namePlaceholder')"
                    />
                </el-form-item>
                <el-form-item :label="$t('permission.group')" prop="group">
                    <el-input
                        v-model="formData.group"
                        :placeholder="$t('permission.groupInputPlaceholder')"
                    />
                </el-form-item>
                <el-form-item :label="$t('permission.description')" prop="description">
                    <el-input
                        v-model="formData.description"
                        type="textarea"
                        :rows="2"
                        :placeholder="$t('permission.descPlaceholder')"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.sort')" prop="sort">
                    <el-input-number v-model="formData.sort" :min="0" :max="9999" />
                </el-form-item>
                <el-form-item :label="$t('common.status')" prop="status">
                    <el-switch v-model="formData.status" :active-value="1" :inactive-value="0" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="formVisible = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="submitLoading" @click="handleSubmit">{{
                    $t('common.confirm')
                }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { permissionApi } from '@/api/permission'
import { useListPage } from '@/hooks/useListPage'

const { t } = useI18n()

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
    handleDelete
} = useListPage<any, { keyword: string; group: string }>({
    fetchFn: (params) => permissionApi.getPermissionList(params),
    deleteFn: (id) => permissionApi.deletePermission(id),
    defaultSearchForm: { keyword: '', group: '' }
})

const formVisible = ref(false)
const formData = ref<any>({ status: 1, sort: 0 })
const formRef = ref<FormInstance>()
const submitLoading = ref(false)

const rules: FormRules = {
    name: [
        { required: true, message: () => t('permission.validate.codeRequired'), trigger: 'blur' }
    ],
    title: [
        { required: true, message: () => t('permission.validate.nameRequired'), trigger: 'blur' }
    ],
    group: [
        { required: true, message: () => t('permission.validate.groupRequired'), trigger: 'blur' }
    ]
}

const handleAdd = () => {
    formData.value = { status: 1, sort: 0 }
    formVisible.value = true
}

const handleEdit = (row: any) => {
    formData.value = { ...row }
    formVisible.value = true
}

const handleSubmit = async () => {
    if (!formRef.value) return
    await formRef.value.validate()
    try {
        submitLoading.value = true
        if (formData.value.id) {
            await permissionApi.updatePermission(formData.value.id, formData.value)
            ElMessage.success(t('message.updateSuccess'))
        } else {
            await permissionApi.createPermission(formData.value)
            ElMessage.success(t('message.createSuccess'))
        }
        formVisible.value = false
        getList()
    } catch (error) {
        console.error('提交失败:', error)
    } finally {
        submitLoading.value = false
    }
}
</script>

<style lang="scss" scoped>
.permission-container {
    // 业务特有样式（search-card / table-header / pagination 已在全局）
}
</style>
