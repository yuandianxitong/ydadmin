<template>
    <div class="log-container">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ $t('log.operationTitle') }}</h1>
                <div class="page-desc">{{ $t('proPage.system_log_operation') }}</div>
            </div>
        </div>
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('log.keyword')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('log.keywordPlaceholder')"
                        clearable
                        style="width: 180px"
                    />
                </el-form-item>
                <el-form-item :label="$t('log.method')">
                    <el-select
                        v-model="searchForm.method"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 110px"
                    >
                        <el-option label="GET" value="GET" />
                        <el-option label="POST" value="POST" />
                        <el-option label="PUT" value="PUT" />
                        <el-option label="DELETE" value="DELETE" />
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

        <ProTable
            :title="$t('log.operationTitle')"
            storage-key="system-log-operation-list"
            :columns="[
                {
                    key: 'id',
                    label: $t('proPage.table_system_log_operation_vue_id'),
                    width: 80,
                    required: true
                },
                {
                    key: 'username',
                    label: $t('proPage.table_system_log_operation_vue_username'),
                    width: 120
                },
                {
                    key: 'method',
                    label: $t('proPage.table_system_log_operation_vue_method'),
                    width: 120
                },
                {
                    key: 'path',
                    label: $t('proPage.table_system_log_operation_vue_path'),
                    minWidth: 200,
                    showOverflowTooltip: true
                },
                {
                    key: 'action',
                    label: $t('proPage.table_system_log_operation_vue_action'),
                    width: 120,
                    required: true
                },
                {
                    key: 'description',
                    label: $t('proPage.table_system_log_operation_vue_description'),
                    minWidth: 160,
                    showOverflowTooltip: true
                },
                { key: 'ip', label: $t('proPage.table_system_log_operation_vue_ip'), width: 140 },
                {
                    key: 'execution_time',
                    label: $t('proPage.table_system_log_operation_vue_execution_time'),
                    width: 120
                },
                {
                    key: 'operation_time',
                    label: $t('proPage.table_system_log_operation_vue_operation_time'),
                    width: 200
                }
            ]"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            ><template #method="{ row }">
                <el-tag :type="methodTagType(row.method)" size="small" effect="light">
                    {{ row.method }}
                </el-tag>
            </template>
            <template #execution_time="{ row }">
                {{ row.execution_time ? (row.execution_time * 1000).toFixed(0) : '-' }}
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
    </div>
</template>

<script setup lang="ts">


import { logApi } from '@/api/log'
import { useListPage } from '@/hooks/useListPage'

const methodTagType = (method: string) => {
    const map: Record<string, string> = {
        GET: 'success',
        POST: 'primary',
        PUT: 'warning',
        DELETE: 'danger'
    }
    return (map[method] || 'info') as any
}

const {
    list,
    loading,
    pagination,
    searchForm,
    handleSearch,
    resetSearch,
    handleSizeChange,
    handlePageChange
} = useListPage<any, { keyword: string; method: string }>({
    fetchFn: (params) => logApi.getOperationLogList(params),
    defaultSearchForm: { keyword: '', method: '' }
})
</script>

<style lang="scss" scoped>
.log-container {
    // 业务特有样式（search-card / table-header / pagination 已在全局）
}
</style>
