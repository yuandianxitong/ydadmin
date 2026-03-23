<template>
    <div class="points-log-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item label="用户昵称/账号">
                    <el-input
                        v-model="searchForm.keyword"
                        placeholder="请输入用户昵称或账号"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item label="类型">
                    <el-select
                        v-model="searchForm.type"
                        placeholder="全部"
                        clearable
                        style="width: 140px"
                    >
                        <el-option label="后台调整" :value="1" />
                        <el-option label="注册赠送" :value="2" />
                        <el-option label="签到" :value="3" />
                        <el-option label="消费赠送" :value="4" />
                        <el-option label="消费扣减" :value="5" />
                    </el-select>
                </el-form-item>
                <el-form-item label="时间范围">
                    <el-date-picker
                        v-model="searchForm.dateRange"
                        type="daterange"
                        range-separator="至"
                        start-placeholder="开始日期"
                        end-placeholder="结束日期"
                        value-format="YYYY-MM-DD"
                        style="width: 260px"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="getLogList">
                        <el-icon><Search /></el-icon>
                        搜索
                    </el-button>
                    <el-button @click="resetSearch">
                        <el-icon><Refresh /></el-icon>
                        重置
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 表格区域 -->
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">积分记录</div>
            </div>

            <el-table v-loading="loading" :data="logList">
                <el-table-column label="ID" prop="id" width="80" />

                <el-table-column label="用户昵称" prop="user_nickname" width="120" show-overflow-tooltip />

                <el-table-column label="变动积分" width="120">
                    <template #default="{ row }">
                        <span :class="row.points >= 0 ? 'points-positive' : 'points-negative'">
                            {{ row.points >= 0 ? '+' : '' }}{{ row.points }}
                        </span>
                    </template>
                </el-table-column>

                <el-table-column label="变动前积分" prop="before_points" width="120" />

                <el-table-column label="变动后积分" prop="after_points" width="120" />

                <el-table-column label="类型" width="110">
                    <template #default="{ row }">
                        <el-tag :type="getTypeTagType(row.type)" size="small">
                            {{ row.type_text }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="备注" prop="remark" min-width="160" show-overflow-tooltip />

                <el-table-column label="操作人" prop="operator_name" width="110">
                    <template #default="{ row }">
                        {{ row.operator_name || '-' }}
                    </template>
                </el-table-column>

                <el-table-column label="时间" prop="created_at" width="170" />
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

<script setup lang="ts" name="PointsLog">
import { Refresh, Search } from '@element-plus/icons-vue'
import { onMounted, reactive, ref } from 'vue'

import { userManageApi } from '@/api/user'
import type { PointsLogItem } from '@/api/user'

// 搜索表单
const searchForm = reactive({
    keyword: '',
    type: undefined as number | undefined,
    dateRange: null as [string, string] | null
})

// 列表数据
const logList = ref<PointsLogItem[]>([])
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
        1: 'info',      // 后台调整
        2: 'success',   // 注册赠送
        3: 'primary',   // 签到
        4: 'warning',   // 消费赠送
        5: 'danger'     // 消费扣减
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

        const response = await userManageApi.getPointsLogs(params)
        logList.value = response.data.list
        pagination.total = response.data.pagination.total
    } catch (error) {
        console.error('获取积分记录失败:', error)
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
.points-log-container {
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
