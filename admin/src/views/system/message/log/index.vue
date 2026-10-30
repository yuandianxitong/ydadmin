<template>
    <div class="message-log">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ $t('messageLog.title') }}</h1>
                <div class="page-desc">{{ $t('proPage.system_message_log_index') }}</div>
            </div>
        </div>
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('messageLog.channel')">
                    <el-select
                        v-model="searchForm.channel"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 140px"
                    >
                        <el-option :label="$t('messageTemplate.sms')" value="sms" />
                        <el-option
                            :label="$t('messageTemplate.official')"
                            value="wechat_official"
                        />
                        <el-option :label="$t('messageTemplate.miniapp')" value="wechat_mini" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('messageLog.statusOptions.pending')" :value="0" />
                        <el-option :label="$t('messageLog.statusOptions.success')" :value="1" />
                        <el-option :label="$t('messageLog.statusOptions.failed')" :value="2" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('messageLog.receiver')">
                    <el-input
                        v-model="searchForm.receiver"
                        :placeholder="$t('messageLog.receiverPlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">{{
                        $t('common.search')
                    }}</el-button>
                    <el-button @click="resetSearch">{{ $t('common.reset') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 表格 -->
        <ProTable
            :title="$t('messageLog.title')"
            storage-key="system-message-log-list"
            :columns="[
                {
                    key: 'template_code',
                    label: $t('proPage.table_system_message_log_index_vue_template_code'),
                    width: 160,
                    required: true
                },
                {
                    key: 'channel',
                    label: $t('proPage.table_system_message_log_index_vue_channel'),
                    width: 120,
                    align: 'center'
                },
                {
                    key: 'receiver',
                    label: $t('proPage.table_system_message_log_index_vue_receiver'),
                    minWidth: 180,
                    showOverflowTooltip: true
                },
                {
                    key: 'status',
                    label: $t('proPage.table_system_message_log_index_vue_status'),
                    width: 90,
                    align: 'center'
                },
                {
                    key: 'error_msg',
                    label: $t('proPage.table_system_message_log_index_vue_error_msg'),
                    minWidth: 200,
                    showOverflowTooltip: true
                },
                {
                    key: 'sent_at',
                    label: $t('proPage.table_system_message_log_index_vue_sent_at'),
                    width: 160
                },
                {
                    key: 'created_at',
                    label: $t('proPage.table_system_message_log_index_vue_created_at'),
                    width: 160
                }
            ]"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            ><template #channel="{ row }">
                <el-tag size="small" :type="channelTagType[row.channel]">
                    {{ channelTextMap[row.channel] || row.channel }}
                </el-tag>
            </template>
            <template #status="{ row }">
                <el-tag :type="statusTagType[row.status]" size="small">
                    {{ statusTextMap[row.status] }}
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

<script setup lang="ts" name="MessageLog">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { messageLogApi } from '@/api/message'
import { useListPage } from '@/hooks/useListPage'

const { t } = useI18n()

const channelTextMap = computed<Record<string, string>>(() => ({
    sms: t('messageTemplate.sms'),
    wechat_official: t('messageTemplate.official'),
    wechat_mini: t('messageTemplate.miniapp')
}))
const channelTagType: Record<string, any> = {
    sms: 'primary',
    wechat_official: 'success',
    wechat_mini: 'warning'
}
const statusTextMap = computed<Record<number, string>>(() => ({
    0: t('messageLog.statusOptions.pending'),
    1: t('messageLog.statusOptions.success'),
    2: t('messageLog.statusOptions.failed')
}))
const statusTagType: Record<number, any> = { 0: 'info', 1: 'success', 2: 'danger' }

const {
    list,
    loading,
    pagination,
    searchForm,
    handleSearch,
    resetSearch,
    handleSizeChange,
    handlePageChange
} = useListPage<any, { channel: string; status?: number; receiver: string }>({
    fetchFn: (params) => messageLogApi.getList(params),
    defaultSearchForm: { channel: '', status: undefined, receiver: '' }
})
</script>

<style lang="scss" scoped>
.message-log {
    // 业务特有样式（search-card / table-header / pagination 已在全局）
}
</style>
