<template>
    <div class="announcement-container">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ $t('announcementMgmt.title') }}</h1>
                <div class="page-desc">{{ $t('proPage.content_announcement_index') }}</div>
            </div>
            <div class="page-actions">
                <el-button v-has-perm="['announcement.create']" type="primary" @click="handleAdd">
                    <i class="i-svg:plus" />
                    {{ $t('announcementMgmt.addAnnouncement') }}
                </el-button>
            </div>
        </div>
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('announcementMgmt.announcementTitle')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('announcementMgmt.titlePlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item :label="$t('announcementMgmt.announcementType')">
                    <el-select
                        v-model="searchForm.type"
                        :placeholder="$t('announcementMgmt.typePlaceholder')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('announcementMgmt.typeOptions.notice')" :value="1" />
                        <el-option :label="$t('announcementMgmt.typeOptions.update')" :value="2" />
                        <el-option
                            :label="$t('announcementMgmt.typeOptions.activity')"
                            :value="3"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('common.selectPlaceholder')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('announcementMgmt.published')" :value="1" />
                        <el-option :label="$t('announcementMgmt.draft')" :value="0" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">
                        <i class="i-svg:search" />
                        {{ $t('common.search') }}
                    </el-button>
                    <el-button @click="resetSearch">
                        <i class="i-svg:refresh-cw" />
                        {{ $t('common.reset') }}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 操作区域 -->
        <ProTable
            :title="$t('announcementMgmt.title')"
            storage-key="content-announcement-list"
            :columns="[
                {
                    key: 'title',
                    label: $t('proPage.table_content_announcement_index_vue_title'),
                    minWidth: 250,
                    showOverflowTooltip: true,
                    required: true
                },
                {
                    key: 'type',
                    label: $t('proPage.table_content_announcement_index_vue_type'),
                    width: 110
                },
                {
                    key: 'status',
                    label: $t('proPage.table_content_announcement_index_vue_status'),
                    width: 100
                },
                {
                    key: 'sort',
                    label: $t('proPage.table_content_announcement_index_vue_sort'),
                    width: 100
                },
                {
                    key: 'publish_at',
                    label: $t('proPage.table_content_announcement_index_vue_publish_at'),
                    width: 160
                },
                {
                    key: 'created_at',
                    label: $t('proPage.table_content_announcement_index_vue_created_at'),
                    width: 160
                },
                {
                    key: 'action',
                    label: $t('proPage.table_content_announcement_index_vue_action'),
                    width: 150,
                    fixed: 'right',
                    required: true
                }
            ]"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            ><template #type="{ row }">
                <el-tag :type="typeTagMap[row.type] || 'info'" size="small">
                    {{ typeTextMap[row.type] || row.type }}
                </el-tag>
            </template>
            <template #status="{ row }">
                <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                    {{
                        row.status === 1
                            ? $t('announcementMgmt.published')
                            : $t('announcementMgmt.draft')
                    }}
                </el-tag>
            </template>
            <template #action="{ row }">
                <el-button
                    v-has-perm="['announcement.update']"
                    type="primary"
                    size="small"
                    text
                    @click="handleEdit(row)"
                >
                    {{ $t('common.edit') }}
                </el-button>
                <el-button
                    v-has-perm="['announcement.delete']"
                    type="danger"
                    size="small"
                    text
                    @click="handleDelete(row.id, row.title)"
                >
                    {{ $t('common.delete') }}
                </el-button>
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

        <!-- 表单弹窗 -->
        <AnnouncementForm v-model="formVisible" :form-data="formData" @success="getList" />
    </div>
</template>

<script setup lang="ts" name="AnnouncementList">

import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { announcementApi } from '@/api/announcement'
import { useListPage } from '@/hooks/useListPage'

import AnnouncementForm from './components/AnnouncementForm.vue'

const { t } = useI18n()

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
    handleDelete
} = useListPage<any, { keyword: string; type?: number; status?: number }>({
    fetchFn: (params) => announcementApi.getList(params),
    deleteFn: (id) => announcementApi.delete(id),
    defaultSearchForm: { keyword: '', type: undefined, status: undefined }
})

const formVisible = ref(false)
const formData = ref<Record<string, any>>({})

const typeTextMap = computed(
    () =>
        ({
            1: t('announcementMgmt.typeOptions.notice'),
            2: t('announcementMgmt.typeOptions.update'),
            3: t('announcementMgmt.typeOptions.activity')
        }) as Record<number, string>
)
const typeTagMap: Record<number, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
    1: 'primary',
    2: 'warning',
    3: 'success'
}

const handleAdd = () => {
    formData.value = { type: 1, status: 1, sort: 0 }
    formVisible.value = true
}

const handleEdit = (row: any) => {
    formData.value = { ...row }
    formVisible.value = true
}
</script>
