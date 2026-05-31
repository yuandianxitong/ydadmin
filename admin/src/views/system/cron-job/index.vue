<template>
    <div class="cron-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('cronJob.taskName')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('cronJob.searchPlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('common.selectPlaceholder')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('common.enable')" :value="1" />
                        <el-option :label="$t('common.disable')" :value="0" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">
                        <el-icon><Search /></el-icon>
                        {{ $t('common.search') }}
                    </el-button>
                    <el-button @click="resetSearch">
                        <el-icon><Refresh /></el-icon>
                        {{ $t('common.reset') }}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 操作区域 -->
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">{{ $t('cronJob.title') }}</div>
                <div class="table-actions">
                    <el-button
                        v-has-perm="['system.cron_job.create']"
                        type="primary"
                        @click="handleAdd"
                    >
                        <el-icon><Plus /></el-icon>
                        {{ $t('cronJob.addTask') }}
                    </el-button>
                </div>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column :label="$t('cronJob.taskName')" prop="name" min-width="140">
                    <template #default="{ row }">
                        <div>
                            <div>{{ row.name }}</div>
                            <div v-if="row.description" class="text-xs text-gray-400">
                                {{ row.description }}
                            </div>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('cronJob.command')" prop="command" width="180">
                    <template #default="{ row }">
                        <el-tag size="small" effect="plain" type="info">{{ row.command }}</el-tag>
                    </template>
                </el-table-column>

                <el-table-column
                    :label="$t('cronJob.executionCycle')"
                    prop="expression"
                    width="180"
                >
                    <template #default="{ row }">
                        {{ cronToText(row.expression) || row.expression }}
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.status')" prop="status" width="100">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.status"
                            :active-value="1"
                            :inactive-value="0"
                            :disabled="!userStore.hasPermission('system.cron_job.update')"
                            @change="handleStatusChange(row)"
                        />
                    </template>
                </el-table-column>

                <el-table-column :label="$t('cronJob.lastExecution')" width="180">
                    <template #default="{ row }">
                        <template v-if="row.last_run_at">
                            <span>{{ row.last_run_at }}</span>
                            <el-tag
                                :type="row.last_status === 1 ? 'success' : 'danger'"
                                size="small"
                                class="ml-2"
                            >
                                {{
                                    row.last_status === 1
                                        ? $t('common.success')
                                        : $t('common.error')
                                }}
                            </el-tag>
                        </template>
                        <span v-else>-</span>
                    </template>
                </el-table-column>

                <el-table-column
                    :label="$t('cronJob.executionCount')"
                    prop="run_count"
                    width="90"
                />

                <el-table-column :label="$t('common.operation')" width="280" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-has-perm="['system.cron_job.run']"
                            type="warning"
                            size="small"
                            text
                            :loading="row._running"
                            @click="handleRun(row)"
                        >
                            {{ $t('cronJob.execute') }}
                        </el-button>
                        <el-button type="primary" size="small" text @click="handleLogs(row)">
                            {{ $t('cronJob.executionLog') }}
                        </el-button>
                        <el-button
                            v-has-perm="['system.cron_job.update']"
                            type="primary"
                            size="small"
                            text
                            @click="handleEdit(row)"
                        >
                            {{ $t('common.edit') }}
                        </el-button>
                        <el-button
                            v-has-perm="['system.cron_job.delete']"
                            type="danger"
                            size="small"
                            text
                            @click="handleDelete(row.id, row.name)"
                        >
                            {{ $t('common.delete') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 分页 -->
            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50]"
                layout="total, sizes, prev, pager, next, jumper"
                class="pagination"
                @size-change="handleSizeChange"
                @current-change="handlePageChange"
            />
        </el-card>

        <!-- 表单弹窗 -->
        <CronJobForm v-model="formVisible" :form-data="formData" @success="getList" />

        <!-- 日志弹窗 -->
        <el-dialog
            v-model="logsVisible"
            :title="`${$t('cronJob.executionLog')} - ${logsJobName}`"
            width="800px"
        >
            <el-table v-loading="logsLoading" :data="logs" size="small">
                <el-table-column
                    :label="$t('common.status')"
                    prop="status"
                    width="80"
                    align="center"
                >
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
                            {{ row.status === 1 ? $t('common.success') : $t('common.error') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('cronJob.output')"
                    prop="output"
                    min-width="200"
                    show-overflow-tooltip
                />
                <el-table-column
                    :label="$t('cronJob.errorMsg')"
                    prop="error"
                    width="200"
                    show-overflow-tooltip
                >
                    <template #default="{ row }">
                        <span class="text-red-500">{{ row.error || '-' }}</span>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('cronJob.executionDuration')"
                    prop="duration"
                    width="90"
                    align="center"
                >
                    <template #default="{ row }"> {{ row.duration }}ms </template>
                </el-table-column>
                <el-table-column
                    :label="$t('cronJob.executionTime')"
                    prop="started_at"
                    width="160"
                    align="center"
                />
            </el-table>
            <div
                v-if="logsPagination.total > 0"
                style="display: flex; justify-content: flex-end; margin-top: 16px"
            >
                <el-pagination
                    v-model:current-page="logsPagination.current_page"
                    v-model:page-size="logsPagination.per_page"
                    :total="logsPagination.total"
                    layout="total, prev, pager, next"
                    small
                    @current-change="loadLogs"
                />
            </div>
        </el-dialog>
    </div>
</template>

<script setup lang="ts" name="CronJobList">
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { cronJobApi } from '@/api/cron-job'
import { cronToText } from '@/components/CronBuilder/cron-text'
import { useListPage } from '@/hooks/useListPage'
import { useUserStore } from '@/store'

import CronJobForm from './components/CronJobForm.vue'

const { t } = useI18n()
const userStore = useUserStore()

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
    handleDelete,
    handleStatusChange
} = useListPage<any, { keyword: string; status?: number }>({
    fetchFn: (params) => {
        const query: Record<string, any> = {
            page: params.page,
            limit: params.limit
        }
        if (params.keyword?.trim()) query.keyword = params.keyword.trim()
        if (params.status !== undefined) query.status = params.status
        return cronJobApi.getList(query)
    },
    deleteFn: (id) => cronJobApi.delete(id),
    updateStatusFn: (id, status) => cronJobApi.updateStatus(id, status),
    defaultSearchForm: { keyword: '', status: undefined }
})

const formVisible = ref(false)
const formData = ref<Record<string, any>>({})

// 日志相关
const logsVisible = ref(false)
const logsJobName = ref('')
const logsJobId = ref(0)
const logs = ref<any[]>([])
const logsLoading = ref(false)
const logsPagination = reactive({ current_page: 1, per_page: 20, total: 0 })

const handleAdd = () => {
    formData.value = { status: 1, sort: 0 }
    formVisible.value = true
}

const handleEdit = (row: any) => {
    formData.value = { ...row }
    formVisible.value = true
}

const handleRun = async (row: any) => {
    try {
        await ElMessageBox.confirm(
            t('cronJob.runConfirm', { name: row.name }),
            t('common.confirm'),
            {
                confirmButtonText: t('common.confirm'),
                cancelButtonText: t('common.cancel'),
                type: 'warning'
            }
        )
        row._running = true
        const res = await cronJobApi.run(row.id)
        if (res.data?.status === 1) {
            ElMessage.success(t('common.success'))
        } else {
            ElMessage.warning(t('common.error'))
        }
        getList()
    } catch (error) {
        if (error !== 'cancel') ElMessage.error(t('common.error'))
    } finally {
        row._running = false
    }
}

const handleLogs = (row: any) => {
    logsJobId.value = row.id
    logsJobName.value = row.name
    logsPagination.current_page = 1
    logsVisible.value = true
    loadLogs()
}

const loadLogs = async () => {
    try {
        logsLoading.value = true
        const res = await cronJobApi.getLogs(logsJobId.value, {
            page: logsPagination.current_page,
            limit: logsPagination.per_page
        })
        logs.value = res.data.list
        Object.assign(logsPagination, res.data.pagination)
    } catch {
        ElMessage.error(t('message.fetchFailed'))
    } finally {
        logsLoading.value = false
    }
}
</script>
