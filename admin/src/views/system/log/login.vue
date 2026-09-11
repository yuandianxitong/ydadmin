<template>
    <div class="log-container">
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
                <div class="table-title">{{ $t('loginLog.title') }}</div>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column :label="$t('common.id')" prop="id" width="80" />
                <el-table-column :label="$t('admin.username')" prop="username" width="120" />
                <el-table-column :label="$t('loginLog.loginIp')" prop="ip" width="140" />
                <el-table-column
                    :label="$t('loginLog.browser')"
                    prop="browser"
                    width="160"
                    show-overflow-tooltip
                />
                <el-table-column :label="$t('loginLog.os')" prop="os" width="130" />
                <el-table-column :label="$t('loginLog.loginResult')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.login_result ? 'success' : 'danger'" size="small">
                            {{
                                row.login_result
                                    ? $t('loginLog.resultOptions.success')
                                    : $t('loginLog.resultOptions.failed')
                            }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('loginLog.loginMessage')"
                    prop="login_message"
                    min-width="160"
                    show-overflow-tooltip
                />
                <el-table-column :label="$t('loginLog.loginTime')" prop="login_time" width="170" />
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
