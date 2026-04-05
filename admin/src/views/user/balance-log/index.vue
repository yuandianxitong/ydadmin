<template>
    <div class="balance-log-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="t('userMgmt.keyword')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="t('userMgmt.common.userNickname') + '/' + t('userMgmt.mobile')"
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
                        :range-separator="t('userMgmt.common.to')"
                        :start-placeholder="t('userMgmt.common.startDate')"
                        :end-placeholder="t('userMgmt.common.endDate')"
                        value-format="YYYY-MM-DD"
                        style="width: 260px"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="getLogList">
                        <el-icon><Search /></el-icon>
                        {{ t('userMgmt.common.search') }}
                    </el-button>
                    <el-button @click="resetSearch">
                        <el-icon><Refresh /></el-icon>
                        {{ t('userMgmt.common.reset') }}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 表格区域 -->
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">{{ t('userMgmt.balanceLog.title') }}</div>
            </div>

            <el-table v-loading="loading" :data="logList">
                <el-table-column label="ID" prop="id" width="80" />

                <el-table-column :label="t('userMgmt.common.userNickname')" prop="user_nickname" width="120" show-overflow-tooltip />

                <el-table-column :label="t('userMgmt.balanceLog.changeAmount')" width="130">
                    <template #default="{ row }">
                        <span :class="parseFloat(row.amount) >= 0 ? 'amount-positive' : 'amount-negative'">
                            {{ parseFloat(row.amount) >= 0 ? '+' : '' }}{{ row.amount }}
                        </span>
                    </template>
                </el-table-column>

                <el-table-column :label="t('userMgmt.balanceLog.beforeBalance')" width="130">
                    <template #default="{ row }">
                        ¥{{ row.before_balance }}
                    </template>
                </el-table-column>

                <el-table-column :label="t('userMgmt.balanceLog.afterBalance')" width="130">
                    <template #default="{ row }">
                        ¥{{ row.after_balance }}
                    </template>
                </el-table-column>

                <el-table-column :label="t('userMgmt.balanceLog.type')" width="110">
                    <template #default="{ row }">
                        <el-tag :type="getTypeTagType(row.type)" size="small">
                            {{ row.type_text }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="t('userMgmt.common.remark')" prop="remark" min-width="160" show-overflow-tooltip />

                <el-table-column :label="t('userMgmt.balanceLog.operator')" prop="operator_name" width="110">
                    <template #default="{ row }">
                        {{ row.operator_name || '-' }}
                    </template>
                </el-table-column>

                <el-table-column :label="t('userMgmt.balanceLog.time')" prop="created_at" width="170" />
            </el-table>

            <!-- 分页 -->
            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                class="pagination"
                @size-change="getLogList"
                @current-change="getLogList"
            />
        </el-card>
    </div>
</template>

<script setup lang="ts" name="BalanceLog">
import { Refresh, Search } from '@element-plus/icons-vue'
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { userManageApi } from '@/api/user'
import type { BalanceLogItem } from '@/api/user'

const { t } = useI18n()

// 搜索表单
const searchForm = reactive({
    keyword: '',
    type: undefined as number | undefined,
    dateRange: null as [string, string] | null
})

// 列表数据
const logList = ref<BalanceLogItem[]>([])
const loading = ref(false)

// 分页信息
const pagination = reactive({
    page: 1,
    limit: 20,
    total: 0
})

// 获取类型标签颜色
const getTypeTagType = (type: number): 'primary' | 'success' | 'warning' | 'info' | 'danger' => {
    const typeMap: Record<number, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
        1: 'success',  // 充值
        2: 'danger',   // 消费
        3: 'warning',  // 退款
        4: 'info'      // 后台调整
    }
    return typeMap[type] || 'info'
}

// 获取记录列表
const getLogList = async () => {
    try {
        loading.value = true
        const params: any = {
            keyword: searchForm.keyword,
            type: searchForm.type,
            page: pagination.page,
            limit: pagination.limit
        }

        if (searchForm.dateRange && searchForm.dateRange.length === 2) {
            params.start_date = searchForm.dateRange[0]
            params.end_date = searchForm.dateRange[1]
        }

        const response = await userManageApi.getBalanceLogs(params)
        logList.value = response.data.list
        pagination.total = response.data.pagination.total
    } catch (error) {
        console.error('获取余额记录失败:', error)
    } finally {
        loading.value = false
    }
}

// 重置搜索
const resetSearch = () => {
    Object.assign(searchForm, {
        keyword: '',
        type: undefined,
        dateRange: null
    })
    pagination.page = 1
    getLogList()
}

onMounted(() => {
    getLogList()
})
</script>

<style lang="scss" scoped>
.balance-log-container {
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
        }

        .pagination {
            margin-top: 16px;
            display: flex;
            justify-content: flex-end;
        }
    }

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
