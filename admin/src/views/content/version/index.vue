<template>
    <div class="version-container">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ $t('versionMgmt.title') }}</h1>
                <div class="page-desc">{{ $t('proPage.content_version_index') }}</div>
            </div>
            <div class="page-actions">
                <el-button v-has-perm="['version.create']" type="primary" @click="handleAdd">
                    <i class="i-svg:plus" />
                    {{ $t('versionMgmt.addVersion') }}
                </el-button>
            </div>
        </div>
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('versionMgmt.platform')">
                    <el-select
                        v-model="searchForm.platform"
                        :placeholder="$t('versionMgmt.platformPlaceholder')"
                        clearable
                        style="width: 150px"
                    >
                        <el-option
                            :label="$t('versionMgmt.platformOptions.android')"
                            value="android"
                        />
                        <el-option :label="$t('versionMgmt.platformOptions.ios')" value="ios" />
                        <el-option
                            :label="$t('versionMgmt.platformOptions.harmony')"
                            value="harmony"
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
                        <el-option :label="$t('common.enable')" :value="1" />
                        <el-option :label="$t('common.disable')" :value="0" />
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
            :title="$t('versionMgmt.title')"
            storage-key="content-version-list"
            :columns="[
                {
                    key: 'platform',
                    label: $t('proPage.table_content_version_index_vue_platform'),
                    width: 120,
                    required: true
                },
                {
                    key: 'version',
                    label: $t('proPage.table_content_version_index_vue_version'),
                    width: 120
                },
                {
                    key: 'version_code',
                    label: $t('proPage.table_content_version_index_vue_version_code'),
                    width: 120
                },
                {
                    key: 'download_url',
                    label: $t('proPage.table_content_version_index_vue_download_url'),
                    minWidth: 250,
                    showOverflowTooltip: true
                },
                {
                    key: 'force_update',
                    label: $t('proPage.table_content_version_index_vue_force_update'),
                    width: 110
                },
                {
                    key: 'status',
                    label: $t('proPage.table_content_version_index_vue_status'),
                    width: 100
                },
                {
                    key: 'created_at',
                    label: $t('proPage.table_content_version_index_vue_created_at'),
                    width: 160
                },
                {
                    key: 'action',
                    label: $t('proPage.table_content_version_index_vue_action'),
                    width: 150,
                    fixed: 'right',
                    required: true
                }
            ]"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            ><template #platform="{ row }">
                <el-tag :type="platformTagMap[row.platform] || 'info'" size="small">
                    {{ platformTextMap[row.platform] || row.platform }}
                </el-tag>
            </template>
            <template #force_update="{ row }">
                <el-tag :type="row.force_update === 1 ? 'danger' : 'info'" size="small">
                    {{ row.force_update === 1 ? $t('common.yes') : $t('common.no') }}
                </el-tag>
            </template>
            <template #status="{ row }">
                <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                    {{ row.status === 1 ? $t('common.enable') : $t('common.disable') }}
                </el-tag>
            </template>
            <template #action="{ row }">
                <el-button
                    v-has-perm="['version.update']"
                    type="primary"
                    size="small"
                    text
                    @click="handleEdit(row)"
                >
                    {{ $t('common.edit') }}
                </el-button>
                <el-button
                    v-has-perm="['version.delete']"
                    type="danger"
                    size="small"
                    text
                    @click="handleDelete(row.id, `${row.platform} v${row.version}`)"
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
        <VersionForm v-model="formVisible" :form-data="formData" @success="getList" />
    </div>
</template>

<script setup lang="ts" name="VersionList">

import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { versionApi } from '@/api/version'
import { useListPage } from '@/hooks/useListPage'

import VersionForm from './components/VersionForm.vue'

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
} = useListPage<any, { platform?: string; status?: number }>({
    fetchFn: (params) => versionApi.getList(params),
    deleteFn: (id) => versionApi.delete(id),
    defaultSearchForm: { platform: undefined, status: undefined }
})

const formVisible = ref(false)
const formData = ref<Record<string, any>>({})

const platformTextMap = computed(
    () =>
        ({
            android: t('versionMgmt.platformOptions.android'),
            ios: t('versionMgmt.platformOptions.ios'),
            harmony: t('versionMgmt.platformOptions.harmony')
        }) as Record<string, string>
)
const platformTagMap: Record<string, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
    android: 'success',
    ios: 'primary',
    harmony: 'warning'
}

const handleAdd = () => {
    formData.value = { platform: '', force_update: 0, status: 1 }
    formVisible.value = true
}

const handleEdit = (row: any) => {
    formData.value = { ...row }
    formVisible.value = true
}
</script>
