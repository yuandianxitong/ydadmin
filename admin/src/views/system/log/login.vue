<template>
    <div class="log-container">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ $t('loginLog.title') }}</h1>
                <div class="page-desc">{{ $t('proPage.system_log_login') }}</div>
            </div>
        </div>
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('admin.username')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('loginLog.usernamePlaceholder')"
                        clearable
                        style="width: 160px"
                    />
                </el-form-item>
                <el-form-item label="IP">
                    <el-input
                        v-model="searchForm.ip"
                        :placeholder="$t('loginLog.ipPlaceholder')"
                        clearable
                        style="width: 150px"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.login_result"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 100px"
                    >
                        <el-option :label="$t('loginLog.resultOptions.success')" :value="1" />
                        <el-option :label="$t('loginLog.resultOptions.failed')" :value="0" />
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
            :title="$t('loginLog.title')"
            storage-key="system-log-login-list"
            :columns="[
                {
                    key: 'id',
                    label: $t('proPage.table_system_log_login_vue_id'),
                    width: 80,
                    required: true
                },
                {
                    key: 'username',
                    label: $t('proPage.table_system_log_login_vue_username'),
                    width: 120
                },
                { key: 'ip', label: $t('proPage.table_system_log_login_vue_ip'), width: 140 },
                {
                    key: 'browser',
                    label: $t('proPage.table_system_log_login_vue_browser'),
                    width: 160,
                    showOverflowTooltip: true
                },
                { key: 'os', label: $t('proPage.table_system_log_login_vue_os'), width: 130 },
                {
                    key: 'login_result',
                    label: $t('proPage.table_system_log_login_vue_login_result'),
                    width: 120
                },
                {
                    key: 'login_message',
                    label: $t('proPage.table_system_log_login_vue_login_message'),
                    minWidth: 160,
                    showOverflowTooltip: true
                },
                {
                    key: 'login_time',
                    label: $t('proPage.table_system_log_login_vue_login_time'),
                    width: 200
                }
            ]"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            ><template #login_result="{ row }">
                <el-tag :type="row.login_result ? 'success' : 'danger'" size="small">
                    {{
                        row.login_result
                            ? $t('loginLog.resultOptions.success')
                            : $t('loginLog.resultOptions.failed')
                    }}
                </el-tag>
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

const {
    list,
    loading,
    pagination,
    searchForm,
    handleSearch,
    resetSearch,
    handleSizeChange,
    handlePageChange
} = useListPage<any, { keyword: string; ip: string; login_result?: number }>({
    fetchFn: (params) => logApi.getLoginLogList(params),
    defaultSearchForm: { keyword: '', ip: '', login_result: undefined }
})
</script>

<style lang="scss" scoped>
.log-container {
    // 业务特有样式（search-card / table-header / pagination 已在全局）
}
</style>
