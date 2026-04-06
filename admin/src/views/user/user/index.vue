<template>
    <div class="user-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="t('userMgmt.keyword')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="t('userMgmt.keywordPlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item :label="t('userMgmt.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="t('userMgmt.statusAll')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="t('userMgmt.statusEnabled')" :value="1" />
                        <el-option :label="t('userMgmt.statusDisabled')" :value="0" />
                    </el-select>
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
                <div class="table-title">{{ t('userMgmt.title') }}</div>
            </div>

            <el-table v-loading="loading" :data="list" style="width: 100%">
                <el-table-column label="ID" prop="id" width="80" />

                <el-table-column :label="t('userMgmt.avatar')" width="80">
                    <template #default="{ row }">
                        <el-image
                            :src="appStore.getImageUrl(row.avatar)"
                            :alt="row.nickname"
                            style="width: 40px; height: 40px; border-radius: 50%"
                            fit="cover"
                        >
                            <template #error>
                                <div class="avatar-fallback">
                                    {{ (row.nickname || '用')[0] }}
                                </div>
                            </template>
                        </el-image>
                    </template>
                </el-table-column>

                <el-table-column
                    :label="t('userMgmt.nickname')"
                    prop="nickname"
                    min-width="120"
                    show-overflow-tooltip
                />

                <el-table-column :label="t('userMgmt.mobile')" prop="mobile" width="140" />

                <el-table-column :label="t('userMgmt.balance')" width="120">
                    <template #default="{ row }">
                        <span class="balance-text">¥{{ row.balance }}</span>
                    </template>
                </el-table-column>

                <el-table-column :label="t('userMgmt.points')" width="100">
                    <template #default="{ row }">
                        <span class="points-text">{{ row.points }}</span>
                    </template>
                </el-table-column>

                <el-table-column :label="t('userMgmt.status')" width="100">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.status"
                            :active-value="1"
                            :inactive-value="0"
                            @change="handleStatusChange(row)"
                        />
                    </template>
                </el-table-column>

                <el-table-column
                    :label="t('userMgmt.registerTime')"
                    prop="created_at"
                    width="170"
                />

                <el-table-column
                    :label="t('userMgmt.lastLogin')"
                    prop="last_login_time"
                    width="170"
                />

                <el-table-column :label="t('userMgmt.actions')" width="240" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" size="small" text @click="handleDetail(row)">
                            {{ t('userMgmt.viewDetail') }}
                        </el-button>
                        <el-button
                            type="warning"
                            size="small"
                            text
                            @click="handleAdjustBalance(row)"
                        >
                            {{ t('userMgmt.adjustBalance') }}
                        </el-button>
                        <el-button
                            type="success"
                            size="small"
                            text
                            @click="handleAdjustPoints(row)"
                        >
                            {{ t('userMgmt.adjustPoints') }}
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

        <!-- 用户详情弹窗 -->
        <UserDetailDialog v-model="detailVisible" :user-data="currentUser" />

        <!-- 调整余额弹窗 -->
        <AdjustBalanceDialog
            v-model="balanceVisible"
            :user-id="currentUser?.id || 0"
            :current-balance="currentUser?.balance || '0.00'"
            @success="getList"
        />

        <!-- 调整积分弹窗 -->
        <AdjustPointsDialog
            v-model="pointsVisible"
            :user-id="currentUser?.id || 0"
            :current-points="currentUser?.points || 0"
            @success="getList"
        />
    </div>
</template>

<script setup lang="ts" name="UserList">
import { Refresh, Search } from '@element-plus/icons-vue'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import type { UserItem } from '@/api/user'
import { userManageApi } from '@/api/user'
import { useListPage } from '@/hooks/useListPage'
import useAppStore from '@/store/modules/app.store'

import AdjustBalanceDialog from './components/AdjustBalanceDialog.vue'
import AdjustPointsDialog from './components/AdjustPointsDialog.vue'
import UserDetailDialog from './components/UserDetailDialog.vue'

const { t } = useI18n()
const appStore = useAppStore()

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
    handleStatusChange
} = useListPage<UserItem, { keyword: string; status?: number }>({
    fetchFn: (params) => userManageApi.getUserList(params),
    updateStatusFn: (id, status) => userManageApi.updateStatus(id, { status }),
    defaultSearchForm: { keyword: '', status: undefined }
})

// 弹窗相关
const detailVisible = ref(false)
const balanceVisible = ref(false)
const pointsVisible = ref(false)
const currentUser = ref<UserItem | null>(null)

// 查看详情
const handleDetail = (row: UserItem) => {
    currentUser.value = row
    detailVisible.value = true
}

// 调整余额
const handleAdjustBalance = (row: UserItem) => {
    currentUser.value = row
    balanceVisible.value = true
}

// 调整积分
const handleAdjustPoints = (row: UserItem) => {
    currentUser.value = row
    pointsVisible.value = true
}
</script>

<style lang="scss" scoped>
.user-container {
    .avatar-fallback {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: var(--el-color-primary-light-7);
        color: var(--el-color-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: 600;
    }

    .balance-text {
        color: var(--el-color-warning);
        font-weight: 600;
    }

    .points-text {
        color: var(--el-color-primary);
        font-weight: 600;
    }
}
</style>
