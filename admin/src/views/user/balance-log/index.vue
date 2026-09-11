<template>
    <div class="balance-log-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="t('userMgmt.keyword')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="t('common.userNickname') + '/' + t('userMgmt.mobile')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item :label="t('userMgmt.balanceLog.type')">
                    <el-select
                        v-model="searchForm.type"
                        :placeholder="t('userMgmt.balanceLog.typeAll')"
                        clearable
                        style="width: 140px"
                    >
                        <el-option :label="t('userMgmt.balanceLog.typeRecharge')" :value="1" />
                        <el-option :label="t('userMgmt.balanceLog.typeConsume')" :value="2" />
                        <el-option :label="t('userMgmt.balanceLog.typeRefund')" :value="3" />
                        <el-option :label="t('userMgmt.balanceLog.typeAdmin')" :value="4" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('userMgmt.balanceLog.dateRange')">
                    <el-date-picker
                        v-model="searchForm.dateRange"
                        type="daterange"
                        :range-separator="t('common.to')"
                        :start-placeholder="t('common.startDate')"
                        :end-placeholder="t('common.endDate')"
                        value-format="YYYY-MM-DD"
                        style="width: 260px"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">
                        <el-icon><Search /></el-icon>
                        {{ t('common.search') }}
                    </el-button>
                    <el-button @click="resetSearch">
                        <el-icon><Refresh /></el-icon>
                        {{ t('common.reset') }}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 表格区域 -->
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">{{ t('userMgmt.balanceLog.title') }}</div>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column label="ID" prop="id" width="80" />

                <el-table-column
                    :label="t('common.userNickname')"
                    prop="user_nickname"
                    width="120"
                    show-overflow-tooltip
                />

                <el-table-column :label="t('userMgmt.balanceLog.changeAmount')" width="130">
                    <template #default="{ row }">
                        <span
                            :class="
                                parseFloat(row.amount) >= 0 ? 'amount-positive' : 'amount-negative'
                            "
                        >
                            {{ parseFloat(row.amount) >= 0 ? '+' : '' }}{{ row.amount }}
                        </span>
                    </template>
                </el-table-column>

                <el-table-column :label="t('userMgmt.balanceLog.beforeBalance')" width="130">
                    <template #default="{ row }"> ¥{{ row.before_balance }} </template>
                </el-table-column>

                <el-table-column :label="t('userMgmt.balanceLog.afterBalance')" width="130">
                    <template #default="{ row }"> ¥{{ row.after_balance }} </template>
                </el-table-column>

                <el-table-column :label="t('userMgmt.balanceLog.type')" width="110">
                    <template #default="{ row }">
                        <el-tag :type="getTypeTagType(row.type)" size="small">
                            {{ row.type_text }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column
                    :label="t('common.remark')"
                    prop="remark"
                    min-width="160"
                    show-overflow-tooltip
                />

                <el-table-column
                    :label="t('userMgmt.balanceLog.operator')"
                    prop="operator_name"
                    width="110"
                >
                    <template #default="{ row }">
                        {{ row.operator_name || '-' }}
                    </template>
                </el-table-column>

                <el-table-column
                    :label="t('userMgmt.balanceLog.time')"
                    prop="created_at"
                    width="170"
                />
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
    </div>
</template>

<script setup lang="ts" name="BalanceLog">
import { Refresh, Search } from '@element-plus/icons-vue'
import { useI18n } from 'vue-i18n'

import type { BalanceLogItem } from '@/api/user'
import { userManageApi } from '@/api/user'
import { useListPage } from '@/hooks/useListPage'

const { t } = useI18n()

// 获取类型标签颜色
const getTypeTagType = (type: number): 'primary' | 'success' | 'warning' | 'info' | 'danger' => {
    const typeMap: Record<number, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
        1: 'success', // 充值
        2: 'danger', // 消费
        3: 'warning', // 退款
        4: 'info' // 后台调整
    }
    return typeMap[type] || 'info'
}

// 日期范围需要拆成 start_date / end_date 再传给后端，这里用自定义 fetch 包装
const {
    list,
    loading,
    pagination,
    searchForm,
    handleSearch,
    resetSearch,
    handleSizeChange,
    handlePageChange
} = useListPage<
    BalanceLogItem,
    {
        keyword: string
        type?: number
        dateRange: [string, string] | null
    }
>({
    fetchFn: (params) => {
        const query: any = {
            keyword: params.keyword,
            type: params.type,
            page: params.page,
            limit: params.limit
        }
        if (params.dateRange && params.dateRange.length === 2) {
            query.start_date = params.dateRange[0]
            query.end_date = params.dateRange[1]
        }
        return userManageApi.getBalanceLogs(query)
    },
    defaultSearchForm: {
        keyword: '',
        type: undefined,
        dateRange: null
    }
})
</script>

<style lang="scss" scoped>
.balance-log-container {
    .amount-positive {
        color: var(--el-color-success);
        font-weight: 600;
    }

    .amount-negative {
        color: var(--el-color-danger);
        font-weight: 600;
    }
}
</style>
