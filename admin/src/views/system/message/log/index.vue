<template>
    <div class="message-log">
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
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">{{ $t('messageLog.title') }}</div>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column
                    :label="$t('messageTemplate.templateCode')"
                    prop="template_code"
                    width="160"
                />
                <el-table-column :label="$t('messageLog.channel')" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag size="small" :type="channelTagType[row.channel]">
                            {{ channelTextMap[row.channel] || row.channel }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('messageLog.receiver')"
                    prop="receiver"
                    min-width="180"
                    show-overflow-tooltip
                />
                <el-table-column :label="$t('common.status')" width="90" align="center">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType[row.status]" size="small">
                            {{ statusTextMap[row.status] }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('messageLog.errorMessage')"
                    prop="error_msg"
                    min-width="200"
                    show-overflow-tooltip
                />
                <el-table-column :label="$t('messageLog.sendTime')" prop="sent_at" width="160" />
                <el-table-column :label="$t('common.createdAt')" prop="created_at" width="160" />
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
