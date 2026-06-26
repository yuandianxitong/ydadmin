<template>
    <div class="feedback-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="t('feedbackMgmt.type')">
                    <el-select
                        v-model="searchForm.type"
                        :placeholder="t('feedbackMgmt.typePlaceholder')"
                        clearable
                        style="width: 130px"
                    >
                        <el-option :label="t('feedbackMgmt.types.suggestion')" value="suggestion" />
                        <el-option :label="t('feedbackMgmt.types.bug')" value="bug" />
                        <el-option :label="t('feedbackMgmt.types.complaint')" value="complaint" />
                        <el-option :label="t('feedbackMgmt.types.other')" value="other" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="t('feedbackMgmt.statusPlaceholder')"
                        clearable
                        style="width: 130px"
                    >
                        <el-option :label="t('feedbackMgmt.statusTexts.pending')" :value="0" />
                        <el-option :label="t('feedbackMgmt.statusTexts.processing')" :value="1" />
                        <el-option :label="t('feedbackMgmt.statusTexts.replied')" :value="2" />
                        <el-option :label="t('feedbackMgmt.statusTexts.closed')" :value="3" />
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

        <!-- 列表 -->
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">{{ t('feedbackMgmt.title') }}</div>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column :label="t('common.id')" prop="id" width="70" />

                <el-table-column
                    :label="t('feedbackMgmt.content')"
                    prop="content"
                    min-width="250"
                    show-overflow-tooltip
                />

                <el-table-column :label="t('feedbackMgmt.type')" prop="type" width="100">
                    <template #default="{ row }">
                        <el-tag :type="typeTagMap[row.type] || 'info'" size="small">
                            {{ typeText(row.type) }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column
                    :label="t('feedbackMgmt.contact')"
                    prop="contact"
                    width="150"
                    show-overflow-tooltip
                />

                <el-table-column :label="t('common.status')" prop="status" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusTagMap[row.status] || 'info'" size="small">
                            {{ statusText(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column
                    :label="t('feedbackMgmt.submittedAt')"
                    prop="created_at"
                    width="170"
                />

                <el-table-column :label="t('common.operation')" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-has-perm="['feedback.detail']"
                            type="primary"
                            size="small"
                            text
                            @click="handleDetail(row as FeedbackInfo)"
                            >{{ t('common.detail') }}</el-button
                        >
                        <el-button
                            v-if="row.status < 2"
                            v-has-perm="['feedback.reply']"
                            type="success"
                            size="small"
                            text
                            @click="handleReply(row as FeedbackInfo)"
                            >{{ t('feedbackMgmt.reply') }}</el-button
                        >
                        <el-button
                            v-if="row.status < 3"
                            v-has-perm="['feedback.close']"
                            type="warning"
                            size="small"
                            text
                            @click="handleClose(row as FeedbackInfo)"
                            >{{ t('feedbackMgmt.close') }}</el-button
                        >
                        <el-button
                            v-has-perm="['feedback.delete']"
                            type="danger"
                            size="small"
                            text
                            @click="handleDeleteRow(row as FeedbackInfo)"
                            >{{ t('common.delete') }}</el-button
                        >
                    </template>
                </el-table-column>
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

        <!-- 详情/回复对话框 -->
        <el-dialog
            v-model="dialogVisible"
            :title="
                dialogMode === 'reply'
                    ? t('feedbackMgmt.replyDialog')
                    : t('feedbackMgmt.detailDialog')
            "
            width="600px"
            destroy-on-close
        >
            <el-descriptions v-if="currentRow" :column="1" border>
                <el-descriptions-item :label="t('feedbackMgmt.feedbackId')">{{
                    currentRow.id
                }}</el-descriptions-item>
                <el-descriptions-item :label="t('feedbackMgmt.type')">
                    <el-tag :type="typeTagMap[currentRow.type] || 'info'" size="small">
                        {{ typeText(currentRow.type) }}
                    </el-tag>
                </el-descriptions-item>
                <el-descriptions-item :label="t('common.status')">
                    <el-tag :type="statusTagMap[currentRow.status] || 'info'" size="small">
                        {{ statusText(currentRow.status) }}
                    </el-tag>
                </el-descriptions-item>
                <el-descriptions-item :label="t('feedbackMgmt.contact')">{{
                    currentRow.contact || '-'
                }}</el-descriptions-item>
                <el-descriptions-item :label="t('feedbackMgmt.submittedAt')">{{
                    currentRow.created_at
                }}</el-descriptions-item>
                <el-descriptions-item :label="t('feedbackMgmt.content')">{{
                    currentRow.content
                }}</el-descriptions-item>
                <el-descriptions-item
                    v-if="currentRow.reply"
                    :label="t('feedbackMgmt.replyContent')"
                    >{{ currentRow.reply }}</el-descriptions-item
                >
                <el-descriptions-item
                    v-if="currentRow.replied_at"
                    :label="t('feedbackMgmt.repliedAt')"
                    >{{ currentRow.replied_at }}</el-descriptions-item
                >
            </el-descriptions>

            <div v-if="dialogMode === 'reply'" style="margin-top: 16px">
                <el-input
                    v-model="replyContent"
                    type="textarea"
                    :rows="4"
                    :placeholder="t('feedbackMgmt.replyPlaceholder')"
                />
            </div>

            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('common.close') }}</el-button>
                <el-button
                    v-if="dialogMode === 'reply'"
                    type="primary"
                    :loading="submitLoading"
                    @click="submitReply"
                    >{{ t('feedbackMgmt.submitReply') }}</el-button
                >
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { feedbackApi } from '@/api/feedback'
import type { FeedbackInfo } from '@/types/content'
import { useListPage } from '@/hooks/useListPage'

const { t } = useI18n()

const typeTagMap: Record<string, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
    suggestion: 'primary',
    bug: 'danger',
    complaint: 'warning',
    other: 'info'
}
const statusTagMap: Record<number, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
    0: 'info',
    1: 'warning',
    2: 'success',
    3: 'info'
}

/** 将类型 code 翻译为显示文本（未知类型降级为原始值） */
function typeText(type: string): string {
    const key = `feedbackMgmt.types.${type}`
    const translated = t(key)
    return translated === key ? type : translated
}

/** 将状态 code 翻译为显示文本 */
function statusText(status: number): string {
    const map: Record<number, string> = {
        0: 'feedbackMgmt.statusTexts.pending',
        1: 'feedbackMgmt.statusTexts.processing',
        2: 'feedbackMgmt.statusTexts.replied',
        3: 'feedbackMgmt.statusTexts.closed'
    }
    return map[status] ? t(map[status]) : t('feedbackMgmt.unknown')
}

// 使用统一的列表页 composable
// 后端 feedback 接口返回 { list, total }（非标准 pagination），需要 fetchFn 内做适配
const {
    list,
    loading,
    pagination,
    searchForm,
    getList,
    handleSearch,
    resetSearch,
    handleSizeChange,
    handlePageChange
} = useListPage<FeedbackInfo, { status: number | string; type: string }>({
    fetchFn: async (params) => {
        const res = await feedbackApi.getList(params)
        const payload = res?.data || {}
        return {
            ...res,
            data: {
                list: (payload.list || payload.data || []) as FeedbackInfo[],
                pagination: {
                    total: payload.total || 0,
                    page: params.page,
                    limit: params.limit,
                    total_pages: 0
                }
            }
        } as any
    },
    defaultSearchForm: { status: '', type: '' }
})

const submitLoading = ref(false)
const dialogVisible = ref(false)
const dialogMode = ref<'detail' | 'reply'>('detail')
const currentRow = ref<FeedbackInfo | null>(null)
const replyContent = ref('')

const handleDetail = (row: FeedbackInfo) => {
    currentRow.value = row
    dialogMode.value = 'detail'
    dialogVisible.value = true
}

const handleReply = (row: FeedbackInfo) => {
    currentRow.value = row
    replyContent.value = ''
    dialogMode.value = 'reply'
    dialogVisible.value = true
}

const submitReply = async () => {
    if (!replyContent.value.trim()) {
        ElMessage.warning(t('feedbackMgmt.replyContentRequired'))
        return
    }
    submitLoading.value = true
    try {
        await feedbackApi.reply(currentRow.value!.id, replyContent.value)
        ElMessage.success(t('feedbackMgmt.replySuccess'))
        dialogVisible.value = false
        await getList()
    } finally {
        submitLoading.value = false
    }
}

const handleClose = async (row: FeedbackInfo) => {
    await ElMessageBox.confirm(t('feedbackMgmt.closeConfirm'), t('common.tip'), { type: 'warning' })
    await feedbackApi.close(row.id)
    ElMessage.success(t('feedbackMgmt.closeSuccess'))
    await getList()
}

const handleDeleteRow = async (row: FeedbackInfo) => {
    await ElMessageBox.confirm(t('feedbackMgmt.deleteConfirm'), t('common.tip'), {
        type: 'warning'
    })
    await feedbackApi.delete(row.id)
    ElMessage.success(t('common.success'))
    await getList()
}
</script>

<style scoped lang="scss">
.feedback-container {
    padding: 16px;
}
</style>
