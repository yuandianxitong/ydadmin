<template>
    <div class="feedback-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item label="反馈类型">
                    <el-select
                        v-model="searchForm.type"
                        placeholder="全部类型"
                        clearable
                        style="width: 130px"
                    >
                        <el-option label="建议" value="suggestion" />
                        <el-option label="Bug" value="bug" />
                        <el-option label="投诉" value="complaint" />
                        <el-option label="其他" value="other" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态">
                    <el-select
                        v-model="searchForm.status"
                        placeholder="全部状态"
                        clearable
                        style="width: 130px"
                    >
                        <el-option label="待处理" :value="0" />
                        <el-option label="处理中" :value="1" />
                        <el-option label="已回复" :value="2" />
                        <el-option label="已关闭" :value="3" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="getList">
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

        <!-- 列表 -->
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">反馈管理</div>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column label="ID" prop="id" width="70" />

                <el-table-column label="反馈内容" prop="content" min-width="250" show-overflow-tooltip />

                <el-table-column label="类型" prop="type" width="100">
                    <template #default="{ row }">
                        <el-tag :type="typeTagMap[row.type] || 'info'" size="small">
                            {{ typeTextMap[row.type] || row.type }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="联系方式" prop="contact" width="150" show-overflow-tooltip />

                <el-table-column label="状态" prop="status" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusTagMap[row.status] || 'info'" size="small">
                            {{ statusTextMap[row.status] || '未知' }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="提交时间" prop="created_at" width="170" />

                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-has-perm="['feedback.detail']"
                            type="primary" size="small" text
                            @click="handleDetail(row)"
                        >详情</el-button>
                        <el-button
                            v-if="row.status < 2"
                            v-has-perm="['feedback.reply']"
                            type="success" size="small" text
                            @click="handleReply(row)"
                        >回复</el-button>
                        <el-button
                            v-if="row.status < 3"
                            v-has-perm="['feedback.close']"
                            type="warning" size="small" text
                            @click="handleClose(row)"
                        >关闭</el-button>
                        <el-button
                            v-has-perm="['feedback.delete']"
                            type="danger" size="small" text
                            @click="handleDelete(row)"
                        >删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="searchForm.page_no"
                    v-model:page-size="searchForm.page_size"
                    :total="total"
                    :page-sizes="[10, 20, 50, 100]"
                    layout="total, sizes, prev, pager, next, jumper"
                    @size-change="getList"
                    @current-change="getList"
                />
            </div>
        </el-card>

        <!-- 详情/回复对话框 -->
        <el-dialog
            v-model="dialogVisible"
            :title="dialogMode === 'reply' ? '回复反馈' : '反馈详情'"
            width="600px"
            destroy-on-close
        >
            <el-descriptions v-if="currentRow" :column="1" border>
                <el-descriptions-item label="反馈ID">{{ currentRow.id }}</el-descriptions-item>
                <el-descriptions-item label="类型">
                    <el-tag :type="typeTagMap[currentRow.type] || 'info'" size="small">
                        {{ typeTextMap[currentRow.type] || currentRow.type }}
                    </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="状态">
                    <el-tag :type="statusTagMap[currentRow.status] || 'info'" size="small">
                        {{ statusTextMap[currentRow.status] || '未知' }}
                    </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="联系方式">{{ currentRow.contact || '-' }}</el-descriptions-item>
                <el-descriptions-item label="提交时间">{{ currentRow.created_at }}</el-descriptions-item>
                <el-descriptions-item label="反馈内容">{{ currentRow.content }}</el-descriptions-item>
                <el-descriptions-item v-if="currentRow.reply" label="回复内容">{{ currentRow.reply }}</el-descriptions-item>
                <el-descriptions-item v-if="currentRow.replied_at" label="回复时间">{{ currentRow.replied_at }}</el-descriptions-item>
            </el-descriptions>

            <div v-if="dialogMode === 'reply'" style="margin-top: 16px;">
                <el-input
                    v-model="replyContent"
                    type="textarea"
                    :rows="4"
                    placeholder="请输入回复内容"
                />
            </div>

            <template #footer>
                <el-button @click="dialogVisible = false">关闭</el-button>
                <el-button
                    v-if="dialogMode === 'reply'"
                    type="primary"
                    :loading="submitLoading"
                    @click="submitReply"
                >提交回复</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Refresh } from '@element-plus/icons-vue'
import { feedbackApi, type FeedbackInfo } from '@/api/feedback'

const typeTextMap: Record<string, string> = {
    suggestion: '建议',
    bug: 'Bug',
    complaint: '投诉',
    other: '其他',
}
const typeTagMap: Record<string, string> = {
    suggestion: 'primary',
    bug: 'danger',
    complaint: 'warning',
    other: 'info',
}
const statusTextMap: Record<number, string> = {
    0: '待处理',
    1: '处理中',
    2: '已回复',
    3: '已关闭',
}
const statusTagMap: Record<number, string> = {
    0: 'info',
    1: 'warning',
    2: 'success',
    3: '',
}

const loading = ref(false)
const submitLoading = ref(false)
const list = ref<FeedbackInfo[]>([])
const total = ref(0)
const dialogVisible = ref(false)
const dialogMode = ref<'detail' | 'reply'>('detail')
const currentRow = ref<FeedbackInfo | null>(null)
const replyContent = ref('')

const searchForm = reactive({
    page_no: 1,
    page_size: 20,
    status: '' as number | string,
    type: '',
})

const getList = async () => {
    loading.value = true
    try {
        const res = await feedbackApi.getList(searchForm)
        list.value = res.data?.list || res.data?.data || []
        total.value = res.data?.total || 0
    } finally {
        loading.value = false
    }
}

const resetSearch = () => {
    searchForm.page_no = 1
    searchForm.status = ''
    searchForm.type = ''
    getList()
}

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
        ElMessage.warning('请输入回复内容')
        return
    }
    submitLoading.value = true
    try {
        await feedbackApi.reply(currentRow.value!.id, replyContent.value)
        ElMessage.success('回复成功')
        dialogVisible.value = false
        await getList()
    } finally {
        submitLoading.value = false
    }
}

const handleClose = async (row: FeedbackInfo) => {
    await ElMessageBox.confirm('确定关闭该反馈？', '确认', { type: 'warning' })
    await feedbackApi.close(row.id)
    ElMessage.success('已关闭')
    await getList()
}

const handleDelete = async (row: FeedbackInfo) => {
    await ElMessageBox.confirm('确定删除该反馈？此操作不可恢复。', '确认删除', { type: 'warning' })
    await feedbackApi.delete(row.id)
    ElMessage.success('删除成功')
    await getList()
}

onMounted(() => {
    getList()
})
</script>

<style scoped lang="scss">
.feedback-container {
    padding: 16px;
}
.search-card {
    margin-bottom: 16px;
}
.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.table-title {
    font-size: 16px;
    font-weight: 600;
}
.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}
</style>
