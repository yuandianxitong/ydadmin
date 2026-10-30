<template>
    <div class="user-container">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ t('userMgmt.title') }}</h1>
                <div class="page-desc">{{ $t('proPage.user_user_index') }}</div>
            </div>
            <div class="page-actions">
                <ImportData
                    v-has-perm="['user.import']"
                    :import-fun="handleImport"
                    :template-url="importTemplateUrl"
                    @success="getList"
                />
            </div>
        </div>
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="t('userMgmt.keyword')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="t('userMgmt.keywordPlaceholder')"
                        clearable
                        style="width: 260px"
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
                <el-form-item class="filter-sp" />
                <el-form-item>
                    <el-button @click="resetSearch">
                        <i class="i-svg:refresh-cw" />
                        {{ $t('common.reset') }}
                    </el-button>
                    <el-button type="primary" @click="handleSearch">
                        <i class="i-svg:search" />
                        {{ $t('common.search') }}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 表格区域 -->
        <ProTable
            :title="t('userMgmt.title')"
            storage-key="user-user-list"
            :columns="[
                {
                    key: 'id',
                    label: $t('proPage.table_user_user_index_vue_id'),
                    width: 80,
                    required: true
                },
                {
                    key: 'avatar',
                    label: $t('proPage.table_user_user_index_vue_avatar'),
                    width: 100
                },
                {
                    key: 'nickname',
                    label: $t('proPage.table_user_user_index_vue_nickname'),
                    minWidth: 120,
                    showOverflowTooltip: true
                },
                {
                    key: 'mobile',
                    label: $t('proPage.table_user_user_index_vue_mobile'),
                    width: 140
                },
                {
                    key: 'balance',
                    label: $t('proPage.table_user_user_index_vue_balance'),
                    width: 120
                },
                {
                    key: 'points',
                    label: $t('proPage.table_user_user_index_vue_points'),
                    width: 100
                },
                {
                    key: 'status',
                    label: $t('proPage.table_user_user_index_vue_status'),
                    width: 100
                },
                {
                    key: 'created_at',
                    label: $t('proPage.table_user_user_index_vue_created_at'),
                    width: 170
                },
                {
                    key: 'last_login_time',
                    label: $t('proPage.table_user_user_index_vue_last_login_time'),
                    width: 170
                },
                {
                    key: 'action',
                    label: $t('proPage.table_user_user_index_vue_action'),
                    width: 240,
                    fixed: 'right',
                    required: true
                }
            ]"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            style="width: 100%"
            ><template #avatar="{ row }">
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
            <template #balance="{ row }">
                <span class="balance-text">¥{{ row.balance }}</span>
            </template>
            <template #points="{ row }">
                <span class="points-text">{{ row.points }}</span>
            </template>
            <template #status="{ row }">
                <el-switch
                    v-model="row.status"
                    :active-value="1"
                    :inactive-value="0"
                    @change="handleStatusChange(row)"
                />
            </template>
            <template #action="{ row }">
                <el-button type="primary" size="small" text @click="handleDetail(row as UserItem)">
                    {{ t('userMgmt.viewDetail') }}
                </el-button>
                <el-button
                    type="warning"
                    size="small"
                    text
                    @click="handleAdjustBalance(row as UserItem)"
                >
                    {{ t('userMgmt.adjustBalance') }}
                </el-button>
                <el-button
                    type="success"
                    size="small"
                    text
                    @click="handleAdjustPoints(row as UserItem)"
                >
                    {{ t('userMgmt.adjustPoints') }}
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
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import type { UserItem } from '@/api/user'
import { userManageApi } from '@/api/user'
import ImportData from '@/components/ImportData/index.vue'
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

// 带 BOM：不带的话 Excel 会按 GBK 打开，用户填完中文再存回来就是乱码
const importTemplateUrl =
    'data:text/csv;charset=utf-8,' +
    encodeURIComponent('\ufeffmobile,nickname,password,email,gender,status\n')

const handleImport = async (file: File) => {
    const res = await userManageApi.importUsers(file)
    const data = res.data
    return {
        success: data.success_count,
        fail: data.fail_count,
        errors: (data.errors ?? []).map((item) => `${item.row}: ${item.message}`)
    }
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
