<template>
    <div class="agreement-container">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ $t('agreementMgmt.title') }}</h1>
                <div class="page-desc">{{ $t('proPage.content_agreement_index') }}</div>
            </div>
            <div class="page-actions">
                <el-button v-has-perm="['agreement.create']" type="primary" @click="handleAdd">
                    <i class="i-svg:plus" />
                    {{ $t('agreementMgmt.addAgreement') }}
                </el-button>
            </div>
        </div>
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
            :title="$t('agreementMgmt.title')"
            storage-key="content-agreement-list"
            :columns="[
                {
                    key: 'title',
                    label: $t('proPage.table_content_agreement_index_vue_title'),
                    minWidth: 250,
                    showOverflowTooltip: true,
                    required: true
                },
                {
                    key: 'code',
                    label: $t('proPage.table_content_agreement_index_vue_code'),
                    width: 180
                },
                {
                    key: 'status',
                    label: $t('proPage.table_content_agreement_index_vue_status'),
                    width: 100
                },
                {
                    key: 'created_at',
                    label: $t('proPage.table_content_agreement_index_vue_created_at'),
                    width: 160
                },
                {
                    key: 'action',
                    label: $t('proPage.table_content_agreement_index_vue_action'),
                    width: 150,
                    fixed: 'right',
                    required: true
                }
            ]"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            ><template #code="{ row }">
                <el-tag type="info" size="small">
                    {{ row.code }}
                </el-tag>
            </template>
            <template #status="{ row }">
                <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                    {{ row.status === 1 ? $t('common.enable') : $t('common.disable') }}
                </el-tag>
            </template>
            <template #action="{ row }">
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

        <!-- 表单弹窗 -->
        <AgreementForm v-model="formVisible" :form-data="formData" @success="getList" />
    </div>
</template>

<script setup lang="ts" name="AgreementList">

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
