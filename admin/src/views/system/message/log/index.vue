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
                        <el-option :label="$t('messageTemplate.official')" value="wechat_official" />
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
                    <el-button type="primary" @click="getList">{{ $t('common.search') }}</el-button>
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
                <el-table-column :label="$t('messageTemplate.templateCode')" prop="template_code" width="160" />
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
                v-model:current-page="pagination.current_page"
                v-model:page-size="pagination.per_page"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                class="pagination"
                @size-change="getList"
                @current-change="getList"
            />
        </el-card>
    </div>
</template>

<script setup lang="ts" name="MessageLog">
import { ElMessage } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { messageLogApi } from '@/api/message'

const { t } = useI18n()

const searchForm = reactive({
    channel: '' as string,
    status: undefined as number | undefined,
    receiver: ''
})
const list = ref<any[]>([])
const loading = ref(false)
const pagination = reactive({ current_page: 1, per_page: 20, total: 0, last_page: 1 })

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

const getList = async () => {
    try {
        loading.value = true
        const params: Record<string, any> = {
            page: pagination.current_page,
            limit: pagination.per_page
        }
        if (searchForm.channel) params.channel = searchForm.channel
        if (searchForm.status !== undefined) params.status = searchForm.status
        if (searchForm.receiver?.trim()) params.receiver = searchForm.receiver.trim()

        const res = await messageLogApi.getList(params)
        list.value = res.data.list
        Object.assign(pagination, res.data.pagination)
    } catch {
        ElMessage.error(t('message.fetchFailed'))
    } finally {
        loading.value = false
    }
}

const resetSearch = () => {
    Object.assign(searchForm, { channel: '', status: undefined, receiver: '' })
    pagination.current_page = 1
    getList()
}

onMounted(() => getList())
</script>

<style lang="scss" scoped>
.message-log {
    .search-card {
        margin-bottom: 16px;
    }
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
</style>
