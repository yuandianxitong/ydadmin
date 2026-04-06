<template>
    <div class="agreement-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('agreementMgmt.agreementTitle')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('agreementMgmt.titlePlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('common.selectPlaceholder')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('common.enable')" :value="1" />
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
                <div class="table-title">{{ $t('agreementMgmt.title') }}</div>
                <div class="table-actions">
                    <el-button v-has-perm="['agreement.create']" type="primary" @click="handleAdd">
                        <el-icon><Plus /></el-icon>
                        {{ $t('agreementMgmt.addAgreement') }}
                    </el-button>
                </div>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column
                    :label="$t('agreementMgmt.agreementTitle')"
                    prop="title"
                    min-width="250"
                    show-overflow-tooltip
                />

                <el-table-column :label="$t('agreementMgmt.agreementCode')" prop="code" width="180">
                    <template #default="{ row }">
                        <el-tag type="info" size="small">
                            {{ row.code }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.status')" prop="status" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                            {{ row.status === 1 ? $t('common.enable') : $t('common.disable') }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.createdAt')" prop="created_at" width="160" />

                <el-table-column :label="$t('common.operation')" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-has-perm="['agreement.update']"
                            type="primary"
                            size="small"
                            text
                            @click="handleEdit(row)"
                        >
                            {{ $t('common.edit') }}
                        </el-button>
                        <el-button
                            v-has-perm="['agreement.delete']"
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

        <!-- 表单弹窗 -->
        <AgreementForm v-model="formVisible" :form-data="formData" @success="getList" />
    </div>
</template>

<script setup lang="ts" name="AgreementList">
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ref } from 'vue'

import { agreementApi } from '@/api/agreement'
import { useListPage } from '@/hooks/useListPage'

import AgreementForm from './components/AgreementForm.vue'

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
    handleDelete
} = useListPage<any, { keyword: string; status?: number }>({
    fetchFn: (params) => agreementApi.getList(params),
    deleteFn: (id) => agreementApi.delete(id),
    defaultSearchForm: { keyword: '', status: undefined }
})

const formVisible = ref(false)
const formData = ref<Record<string, any>>({})

const handleAdd = () => {
    formData.value = { status: 1 }
    formVisible.value = true
}

const handleEdit = (row: any) => {
    formData.value = { ...row }
    formVisible.value = true
}
</script>
