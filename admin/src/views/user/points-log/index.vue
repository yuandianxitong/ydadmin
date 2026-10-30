<template>
    <div class="points-log-container">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ t('userMgmt.pointsLog.title') }}</h1>
                <div class="page-desc">{{ $t('proPage.user_points-log_index') }}</div>
            </div>
        </div>
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
                <el-form-item :label="t('userMgmt.pointsLog.type')">
                    <el-select
                        v-model="searchForm.type"
                        :placeholder="t('userMgmt.pointsLog.typeAll')"
                        clearable
                        style="width: 140px"
                    >
                        <el-option :label="t('userMgmt.pointsLog.typeAdmin')" :value="1" />
                        <el-option :label="t('userMgmt.pointsLog.typeRegister')" :value="2" />
                        <el-option :label="t('userMgmt.pointsLog.typeCheckin')" :value="3" />
                        <el-option :label="t('userMgmt.pointsLog.typeConsumeGift')" :value="4" />
                        <el-option :label="t('userMgmt.pointsLog.typeConsumeDeduct')" :value="5" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('userMgmt.pointsLog.dateRange')">
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
                        <i class="i-svg:search" />
                        {{ t('common.search') }}
                    </el-button>
                    <el-button @click="resetSearch">
                        <i class="i-svg:refresh-cw" />
                        {{ t('common.reset') }}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 表格区域 -->
        <ProTable
            :title="t('userMgmt.pointsLog.title')"
            storage-key="user-points-log-list"
            :columns="[
                {
                    key: 'id',
                    label: $t('proPage.table_user_points_log_index_vue_id'),
                    width: 80,
                    required: true
                },
                {
                    key: 'user_nickname',
                    label: $t('proPage.table_user_points_log_index_vue_user_nickname'),
                    width: 120,
                    showOverflowTooltip: true
                },
                {
                    key: 'points',
                    label: $t('proPage.table_user_points_log_index_vue_points'),
                    width: 120
                },
                {
                    key: 'before_points',
                    label: $t('proPage.table_user_points_log_index_vue_before_points'),
                    width: 120
                },
                {
                    key: 'after_points',
                    label: $t('proPage.table_user_points_log_index_vue_after_points'),
                    width: 120
                },
                {
                    key: 'type',
                    label: $t('proPage.table_user_points_log_index_vue_type'),
                    width: 110
                },
                {
                    key: 'remark',
                    label: $t('proPage.table_user_points_log_index_vue_remark'),
                    minWidth: 160,
                    showOverflowTooltip: true
                },
                {
                    key: 'operator_name',
                    label: $t('proPage.table_user_points_log_index_vue_operator_name'),
                    width: 110
                },
                {
                    key: 'created_at',
                    label: $t('proPage.table_user_points_log_index_vue_created_at'),
                    width: 170
                }
            ]"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            ><template #points="{ row }">
                <span :class="row.points >= 0 ? 'points-positive' : 'points-negative'">
                    {{ row.points >= 0 ? '+' : '' }}{{ row.points }}
                </span>
            </template>
            <template #type="{ row }">
                <el-tag :type="getTypeTagType(row.type)" size="small">
                    {{ row.type_text }}
                </el-tag>
            </template>
            <template #operator_name="{ row }">
                {{ row.operator_name || '-' }}
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

<script setup lang="ts" name="PointsLog">
import { useI18n } from 'vue-i18n'

import type { PointsLogItem } from '@/api/user'
import { userManageApi } from '@/api/user'
import { useListPage } from '@/hooks/useListPage'

const { t } = useI18n()

// 获取类型标签颜色
const getTypeTagType = (type: number): 'primary' | 'success' | 'warning' | 'info' | 'danger' => {
    const typeMap: Record<number, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
        1: 'info', // 后台调整
        2: 'success', // 注册赠送
        3: 'primary', // 签到
        4: 'warning', // 消费赠送
        5: 'danger' // 消费扣减
    }
    return typeMap[type] || 'info'
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
} = useListPage<
    PointsLogItem,
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
        return userManageApi.getPointsLogs(query)
    },
    defaultSearchForm: {
        keyword: '',
        type: undefined,
        dateRange: null
    }
})
</script>

<style lang="scss" scoped>
.points-log-container {
    .points-positive {
        color: var(--el-color-success);
        font-weight: 600;
    }

    .points-negative {
        color: var(--el-color-danger);
        font-weight: 600;
    }
}
</style>
