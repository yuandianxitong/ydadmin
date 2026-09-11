<template>
    <div class="announcement-container">
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
                <div class="table-title">{{ $t('announcementMgmt.title') }}</div>
                <div class="table-actions">
                    <el-button
                        v-has-perm="['announcement.create']"
                        type="primary"
                        @click="handleAdd"
                    >
                        <el-icon><Plus /></el-icon>
                        {{ $t('announcementMgmt.addAnnouncement') }}
                    </el-button>
                </div>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column
                    :label="$t('announcementMgmt.announcementTitle')"
                    prop="title"
                    min-width="250"
                    show-overflow-tooltip
                />

                <el-table-column
                    :label="$t('announcementMgmt.announcementType')"
                    prop="type"
                    width="110"
                >
                    <template #default="{ row }">
                        <el-tag :type="typeTagMap[row.type] || 'info'" size="small">
                            {{ typeTextMap[row.type] || row.type }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.status')" prop="status" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                            {{
                                row.status === 1
                                    ? $t('announcementMgmt.published')
                                    : $t('announcementMgmt.draft')
                            }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.sort')" prop="sort" width="80" />

                <el-table-column
                    :label="$t('announcementMgmt.publishAt')"
                    prop="publish_at"
                    width="160"
                />

                <el-table-column :label="$t('common.createdAt')" prop="created_at" width="160" />

                <el-table-column :label="$t('common.operation')" width="150" fixed="right">
                    <template #default="{ row }">
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
                </el-table-column>
            </el-table>

            <!-- 分页 -->
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

        <!-- 表单弹窗 -->
        <AnnouncementForm v-model="formVisible" :form-data="formData" @success="getList" />
    </div>
</template>

<script setup lang="ts" name="AnnouncementList">
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
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
