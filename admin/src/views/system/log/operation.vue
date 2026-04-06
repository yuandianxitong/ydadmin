<template>
    <div class="log-container">
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
                <div class="table-title">{{ $t('log.operationTitle') }}</div>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column :label="$t('common.id')" prop="id" width="80" />
                <el-table-column :label="$t('log.operator')" prop="username" width="120" />
                <el-table-column :label="$t('log.method')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="methodTagType(row.method)" size="small" effect="light">
                            {{ row.method }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('log.requestPath')"
                    prop="path"
                    min-width="200"
                    show-overflow-tooltip
                />
                <el-table-column :label="$t('log.action')" prop="action" width="120" />
                <el-table-column
                    :label="$t('log.actionDesc')"
                    prop="description"
                    min-width="160"
                    show-overflow-tooltip
                />
                <el-table-column :label="$t('log.ip')" prop="ip" width="140" />
                <el-table-column :label="$t('log.duration')" width="100">
                    <template #default="{ row }">
                        {{ row.execution_time ? (row.execution_time * 1000).toFixed(0) : '-' }}
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('log.operationTime')"
                    prop="operation_time"
                    width="170"
                />
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
    </div>
</template>

<script setup lang="ts">
import { Refresh, Search } from '@element-plus/icons-vue'

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
