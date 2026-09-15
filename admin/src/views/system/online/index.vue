<template>
    <div class="online-container">
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">{{ $t('onlineAdmin.title') }}</div>
                <div class="table-actions">
                    <el-button :loading="loading" @click="getList">
                        <el-icon><Refresh /></el-icon>
                        {{ $t('common.refresh') }}
                    </el-button>
                </div>
            </div>

            <el-table v-loading="loading" :data="list" row-key="admin_id">
                <el-table-column
                    :label="$t('onlineAdmin.username')"
                    prop="username"
                    min-width="120"
                />
                <el-table-column
                    :label="$t('onlineAdmin.nickname')"
                    prop="nickname"
                    min-width="120"
                />
                <el-table-column
                    :label="$t('onlineAdmin.connections')"
                    prop="connections"
                    width="90"
                    align="center"
                />
                <el-table-column :label="$t('onlineAdmin.ip')" prop="ip" width="140" />
                <el-table-column
                    :label="$t('onlineAdmin.userAgent')"
                    min-width="180"
                    show-overflow-tooltip
                >
                    <template #default="{ row }">
                        <span :title="row.ua">{{ summarizeUserAgent(row.ua) }}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('onlineAdmin.connectedAt')"
                    prop="connected_at"
                    width="170"
                />
                <el-table-column :label="$t('onlineAdmin.lastSeen')" prop="last_seen" width="170" />
                <el-table-column :label="$t('common.operation')" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-has-perm="['system.online.logout']"
                            type="danger"
                            size="small"
                            text
                            @click="handleForceLogout(row as OnlineAdminInfo)"
                        >
                            {{ $t('onlineAdmin.forceLogout') }}
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
    </div>
</template>

<script setup lang="ts" name="OnlineAdminList">
import { Refresh } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'

import { onlineApi } from '@/api/online'
import { useListPage } from '@/hooks/useListPage'
import type { OnlineAdminInfo } from '@/types/system'
import { summarizeUserAgent } from '@/utils/user-agent'

const { t } = useI18n()

// 在线管理员没有搜索条件：只分页
const { list, loading, pagination, getList, handleSizeChange, handlePageChange } = useListPage<
    OnlineAdminInfo,
    Record<string, never>
>({
    fetchFn: (params: { page: number; limit: number }) =>
        onlineApi.getList({ page: params.page, limit: params.limit }),
    defaultSearchForm: {}
})

const handleForceLogout = async (row: OnlineAdminInfo) => {
    try {
        await ElMessageBox.confirm(
            t('onlineAdmin.forceLogoutConfirm', { name: row.nickname || row.username }),
            t('common.tip'),
            {
                confirmButtonText: t('common.confirm'),
                cancelButtonText: t('common.cancel'),
                type: 'warning'
            }
        )
    } catch {
        return
    }

    try {
        const res = await onlineApi.logout(row.admin_id)
        ElMessage.success(t('onlineAdmin.forceLogoutSuccess', { count: res.data?.kicked ?? 0 }))
        getList()
    } catch {
        // request.ts 响应拦截器已提示错误（404 范围外、422 不能踢自己或超管）；
        // 不能让错误冒泡到事件处理器，否则会触发 App.vue 的 ErrorBoundary 错误页面。
    }
}
</script>
