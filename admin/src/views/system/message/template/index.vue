<template>
    <div class="message-template">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ $t('messageTemplate.title') }}</h1>
                <div class="page-desc">{{ $t('proPage.system_message_template_index') }}</div>
            </div>
            <div class="page-actions">
                <el-button type="primary" @click="handleAdd">{{
                    $t('messageTemplate.addTemplate')
                }}</el-button>
            </div>
        </div>
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
                    <el-button type="primary" @click="handleSearch">{{
                        $t('common.search')
                    }}</el-button>
                    <el-button @click="resetSearch">{{ $t('common.reset') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 表格 -->
        <ProTable
            :title="$t('messageTemplate.title')"
            storage-key="system-message-template-list"
            :columns="[
                {
                    key: 'name',
                    label: $t('proPage.table_system_message_template_index_vue_name'),
                    minWidth: 150,
                    showOverflowTooltip: true,
                    required: true
                },
                {
                    key: 'code',
                    label: $t('proPage.table_system_message_template_index_vue_code'),
                    width: 180
                },
                {
                    key: 'sms_enabled',
                    label: $t('proPage.table_system_message_template_index_vue_sms_enabled'),
                    width: 100,
                    align: 'center'
                },
                {
                    key: 'wechat_official_enabled',
                    label: $t(
                        'proPage.table_system_message_template_index_vue_wechat_official_enabled'
                    ),
                    width: 100,
                    align: 'center'
                },
                {
                    key: 'wechat_mini_enabled',
                    label: $t(
                        'proPage.table_system_message_template_index_vue_wechat_mini_enabled'
                    ),
                    width: 100,
                    align: 'center'
                },
                {
                    key: 'status',
                    label: $t('proPage.table_system_message_template_index_vue_status'),
                    width: 100,
                    align: 'center'
                },
                {
                    key: 'created_at',
                    label: $t('proPage.table_system_message_template_index_vue_created_at'),
                    width: 200
                },
                {
                    key: 'action',
                    label: $t('proPage.table_system_message_template_index_vue_action'),
                    width: 150,
                    fixed: 'right',
                    required: true
                }
            ]"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            ><template #sms_enabled="{ row }">
                <el-tag :type="row.sms_enabled ? 'success' : 'info'" size="small">
                    {{ row.sms_enabled ? $t('messageTemplate.on') : $t('messageTemplate.off') }}
                </el-tag>
            </template>
            <template #wechat_official_enabled="{ row }">
                <el-tag :type="row.wechat_official_enabled ? 'success' : 'info'" size="small">
                    {{
                        row.wechat_official_enabled
                            ? $t('messageTemplate.on')
                            : $t('messageTemplate.off')
                    }}
                </el-tag>
            </template>
            <template #wechat_mini_enabled="{ row }">
                <el-tag :type="row.wechat_mini_enabled ? 'success' : 'info'" size="small">
                    {{
                        row.wechat_mini_enabled
                            ? $t('messageTemplate.on')
                            : $t('messageTemplate.off')
                    }}
                </el-tag>
            </template>
            <template #status="{ row }">
                <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                    {{ row.status === 1 ? $t('common.enable') : $t('common.disable') }}
                </el-tag>
            </template>
            <template #action="{ row }">
                <el-button type="primary" size="small" text @click="handleEdit(row)">{{
                    $t('common.edit')
                }}</el-button>
                <el-button
                    type="danger"
                    size="small"
                    text
                    @click="handleDelete(row.id, row.name)"
                    >{{ $t('common.delete') }}</el-button
                >
            </template>
            <template #pagination
                ><el-pagination
                    small
                    v-model:current-page="pagination.page"
                    v-model:page-size="pagination.limit"
                    :total="pagination.total"
                    :page-sizes="[10, 20, 50]"
                    layout="prev, pager, next, sizes"
                    class="pro-pagination"
                    @size-change="handleSizeChange"
                    @current-change="handlePageChange" /></template
        ></ProTable>

        <!-- 编辑弹窗 -->
        <TemplateForm v-model="formVisible" :form-data="formData" @success="getList" />
    </div>
</template>

<script setup lang="ts" name="MessageTemplate">
import { ref } from 'vue'

import { messageTemplateApi } from '@/api/message'
import { useListPage } from '@/hooks/useListPage'

import TemplateForm from './components/TemplateForm.vue'

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
    handleDelete
} = useListPage<any, { keyword: string; status?: number }>({
    fetchFn: (params) => messageTemplateApi.getList(params),
    deleteFn: (id) => messageTemplateApi.delete(id),
    defaultSearchForm: { keyword: '', status: undefined }
})

const formVisible = ref(false)
const formData = ref<Record<string, any>>({})

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
</script>

<style lang="scss" scoped>
.message-template {
    // 业务特有样式（search-card / table-header / pagination 已在全局）
}
</style>
