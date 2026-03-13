<template>
    <div class="message-template">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('log.keyword')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('messageTemplate.searchPlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('common.enable')" :value="1" />
                        <el-option :label="$t('common.disable')" :value="0" />
                    </el-select>
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
                <div class="table-title">{{ $t('messageTemplate.title') }}</div>
                <el-button type="primary" @click="handleAdd">{{ $t('messageTemplate.addTemplate') }}</el-button>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column
                    :label="$t('messageTemplate.templateName')"
                    prop="name"
                    min-width="150"
                    show-overflow-tooltip
                />
                <el-table-column :label="$t('messageTemplate.templateCode')" prop="code" width="160" />
                <el-table-column :label="$t('messageTemplate.sms')" width="80" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.sms_enabled ? 'success' : 'info'" size="small">
                            {{ row.sms_enabled ? $t('messageTemplate.on') : $t('messageTemplate.off') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('messageTemplate.official')" width="80" align="center">
                    <template #default="{ row }">
                        <el-tag
                            :type="row.wechat_official_enabled ? 'success' : 'info'"
                            size="small"
                        >
                            {{ row.wechat_official_enabled ? $t('messageTemplate.on') : $t('messageTemplate.off') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('messageTemplate.miniapp')" width="80" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.wechat_mini_enabled ? 'success' : 'info'" size="small">
                            {{ row.wechat_mini_enabled ? $t('messageTemplate.on') : $t('messageTemplate.off') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('common.status')" width="80" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                            {{ row.status === 1 ? $t('common.enable') : $t('common.disable') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('common.createdAt')" prop="created_at" width="160" />
                <el-table-column :label="$t('common.operation')" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" size="small" text @click="handleEdit(row)"
                            >{{ $t('common.edit') }}</el-button
                        >
                        <el-button type="danger" size="small" text @click="handleDelete(row)"
                            >{{ $t('common.delete') }}</el-button
                        >
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="pagination.current_page"
                v-model:page-size="pagination.per_page"
                :total="pagination.total"
                :page-sizes="[10, 20, 50]"
                layout="total, sizes, prev, pager, next, jumper"
                class="pagination"
                @size-change="getList"
                @current-change="getList"
            />
        </el-card>

        <!-- 编辑弹窗 -->
        <TemplateForm v-model="formVisible" :form-data="formData" @success="getList" />
    </div>
</template>

<script setup lang="ts" name="MessageTemplate">
import { ElMessage, ElMessageBox } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { messageTemplateApi } from '@/api/message'

import TemplateForm from './components/TemplateForm.vue'

const { t } = useI18n()

const searchForm = reactive({ keyword: '', status: undefined as number | undefined })
const list = ref<any[]>([])
const loading = ref(false)
const pagination = reactive({ current_page: 1, per_page: 20, total: 0, last_page: 1 })
const formVisible = ref(false)
const formData = ref<Record<string, any>>({})

const getList = async () => {
    try {
        loading.value = true
        const params: Record<string, any> = {
            page: pagination.current_page,
            limit: pagination.per_page
        }
        if (searchForm.keyword?.trim()) params.keyword = searchForm.keyword.trim()
        if (searchForm.status !== undefined) params.status = searchForm.status

        const res = await messageTemplateApi.getList(params)
        list.value = res.data.list
        Object.assign(pagination, res.data.pagination)
    } catch {
        ElMessage.error(t('message.fetchFailed'))
    } finally {
        loading.value = false
    }
}

const resetSearch = () => {
    Object.assign(searchForm, { keyword: '', status: undefined })
    pagination.current_page = 1
    getList()
}

const handleAdd = () => {
    formData.value = {
        status: 1,
        sms_enabled: 0,
        wechat_official_enabled: 0,
        wechat_mini_enabled: 0
    }
    formVisible.value = true
}

const handleEdit = (row: any) => {
    formData.value = { ...row }
    formVisible.value = true
}

const handleDelete = async (row: any) => {
    try {
        await ElMessageBox.confirm(
            t('message.deleteConfirmName', { name: row.name }),
            t('common.tip'),
            { type: 'warning' }
        )
        await messageTemplateApi.delete(row.id)
        ElMessage.success(t('message.deleteSuccess'))
        getList()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('http.operationFailed'))
    }
}

onMounted(() => getList())
</script>

<style lang="scss" scoped>
.message-template {
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
