<template>
    <div class="online-container">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ $t('onlineAdmin.title') }}</h1>
                <div class="page-desc">{{ $t('proPage.system_online_index') }}</div>
            </div>
        </div>
        <ProTable
            :title="$t('onlineAdmin.title')"
            storage-key="system-online-list"
            :columns="[
                { key: 'username', label: $t('onlineAdmin.username'), minWidth: 120 },
                { key: 'nickname', label: $t('onlineAdmin.nickname'), minWidth: 120 },
                {
                    key: 'connections',
                    label: $t('onlineAdmin.connections'),
                    width: 90,
                    align: 'center'
                },
                { key: 'ip', label: $t('onlineAdmin.ip'), width: 140 },
                {
                    key: 'ua',
                    label: $t('onlineAdmin.userAgent'),
                    minWidth: 180,
                    showOverflowTooltip: true
                },
                { key: 'connected_at', label: $t('onlineAdmin.connectedAt'), width: 170 },
                { key: 'last_seen', label: $t('onlineAdmin.lastSeen'), width: 170 },
                {
                    key: 'action',
                    label: $t('common.operation'),
                    width: 120,
                    fixed: 'right',
                    required: true
                }
            ]"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            row-key="admin_id"
            ><template #ua="{ row }">
                <span :title="row.ua">{{ summarizeUserAgent(row.ua) }}</span>
            </template>
            <template #action="{ row }">
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
            <template #headerExtra
                ><el-button :loading="loading" @click="getList">
                    <i class="i-svg:refresh-cw" />
                    {{ $t('common.refresh') }}
                </el-button></template
            >
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

<script setup lang="ts" name="OnlineAdminList">

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
