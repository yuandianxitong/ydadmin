<template>
    <div class="feedback-container">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ t('feedbackMgmt.title') }}</h1>
                <div class="page-desc">{{ $t('proPage.content_feedback_index') }}</div>
            </div>
        </div>
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
                        <i class="i-svg:search" />
                        {{ t('common.search') }}
                    </el-button>
                    <el-button @click="resetSearch">
                        <i class="i-svg:refresh-cw" />
                        {{ t('common.reset') }}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 列表 -->
        <ProTable
            :title="t('feedbackMgmt.title')"
            storage-key="content-feedback-list"
            :columns="[
                {
                    key: 'id',
                    label: $t('proPage.table_content_feedback_index_vue_id'),
                    width: 70,
                    required: true
                },
                {
                    key: 'content',
                    label: $t('proPage.table_content_feedback_index_vue_content'),
                    minWidth: 250,
                    showOverflowTooltip: true
                },
                {
                    key: 'type',
                    label: $t('proPage.table_content_feedback_index_vue_type'),
                    width: 120
                },
                {
                    key: 'contact',
                    label: $t('proPage.table_content_feedback_index_vue_contact'),
                    width: 150,
                    showOverflowTooltip: true
                },
                {
                    key: 'status',
                    label: $t('proPage.table_content_feedback_index_vue_status'),
                    width: 100
                },
                {
                    key: 'created_at',
                    label: $t('proPage.table_content_feedback_index_vue_created_at'),
                    width: 170
                },
                {
                    key: 'action',
                    label: $t('proPage.table_content_feedback_index_vue_action'),
                    width: 200,
                    fixed: 'right',
                    required: true
                }
            ]"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            ><template #type="{ row }">
                <el-tag :type="typeTagMap[row.type] || 'info'" size="small">
                    {{ typeText(row.type) }}
                </el-tag>
            </template>
            <template #status="{ row }">
                <el-tag :type="statusTagMap[row.status] || 'info'" size="small">
                    {{ statusText(row.status) }}
                </el-tag>
            </template>
            <template #action="{ row }">
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
import { ElMessage, ElMessageBox } from 'element-plus'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { feedbackApi } from '@/api/feedback'
import { useListPage } from '@/hooks/useListPage'
import type { FeedbackInfo } from '@/types/content'

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

<style scoped lang="scss"></style>
