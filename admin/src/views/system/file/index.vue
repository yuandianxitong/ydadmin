<template>
    <div class="file-page">
        <!-- 左侧分组 -->
        <el-card class="group-card" shadow="never">
            <template #header>
                <span>{{ $t('file.fileGroup') }}</span>
            </template>
            <div class="group-list">
                <div
                    :class="['group-item', { active: currentGroup === '' }]"
                    @click="selectGroup('')"
                >
                    <span>{{ $t('file.allFiles') }}</span>
                </div>
                <div
                    v-for="g in groups"
                    :key="g.group"
                    :class="['group-item', { active: currentGroup === g.group }]"
                    @click="selectGroup(g.group)"
                >
                    <span>{{ g.group }}</span>
                    <el-tag size="small" type="info">{{ g.count }}</el-tag>
                </div>
            </div>
        </el-card>

        <!-- 右侧文件列表 -->
        <el-card class="file-list-card" shadow="never">
            <template #header>
                <div class="card-header">
                    <div class="header-left">
                        <el-input
                            v-model="searchKeyword"
                            :placeholder="$t('file.searchPlaceholder')"
                            :prefix-icon="Search"
                            clearable
                            size="small"
                            style="width: 200px"
                            @input="handleSearch"
                        />
                        <el-select
                            v-model="mimeFilter"
                            :placeholder="$t('file.fileType')"
                            clearable
                            size="small"
                            style="width: 120px; margin-left: 8px"
                            @change="fetchFileList"
                        >
                            <el-option :label="$t('common.all')" value="" />
                            <el-option :label="$t('file.image')" value="image" />
                            <el-option :label="$t('file.file')" value="other" />
                        </el-select>
                    </div>
                    <div class="header-right">
                        <el-button
                            v-has-perm="'system.file.delete'"
                            type="danger"
                            size="small"
                            :disabled="selectedIds.length === 0"
                            @click="handleBatchDelete"
                        >
                            {{ $t('common.batchDelete') }} ({{ selectedIds.length }})
                        </el-button>
                    </div>
                </div>
            </template>

            <el-table
                v-loading="loading"
                :data="fileList"
                size="small"
                style="width: 100%"
                @selection-change="handleSelectionChange"
            >
                <el-table-column type="selection" width="40" />
                <el-table-column :label="$t('common.preview')" width="60" align="center">
                    <template #default="{ row }">
                        <el-image
                            v-if="row.mime_type?.startsWith('image/')"
                            :src="row.url"
                            :preview-src-list="[row.url]"
                            fit="cover"
                            style="width: 36px; height: 36px; border-radius: 4px"
                        />
                        <el-icon v-else :size="24" color="#909399"><Document /></el-icon>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="name"
                    :label="$t('file.fileName')"
                    min-width="180"
                    show-overflow-tooltip
                />
                <el-table-column
                    prop="extension"
                    :label="$t('file.type')"
                    width="70"
                    align="center"
                />
                <el-table-column :label="$t('file.size')" width="90" align="center">
                    <template #default="{ row }">
                        {{ formatSize(row.size) }}
                    </template>
                </el-table-column>
                <el-table-column prop="group" :label="$t('file.group')" width="90" />
                <el-table-column prop="created_at" :label="$t('file.uploadTime')" width="160" />
                <el-table-column :label="$t('common.operation')" width="140" align="center">
                    <template #default="{ row }">
                        <el-button link type="primary" size="small" @click="handleCopyUrl(row)">{{
                            $t('common.copy')
                        }}</el-button>
                        <el-button
                            v-has-perm="'system.file.update'"
                            link
                            type="primary"
                            size="small"
                            @click="handleRename(row)"
                            >{{ $t('common.rename') }}</el-button
                        >
                        <el-button
                            v-has-perm="'system.file.delete'"
                            link
                            type="danger"
                            size="small"
                            @click="handleDelete(row)"
                            >{{ $t('common.delete') }}</el-button
                        >
                    </template>
                </el-table-column>
            </el-table>

            <!-- 分页 -->
            <div v-if="total > 0" class="pagination-wrap">
                <el-pagination
                    v-model:page-size="query.limit"
                    v-model:current-page="query.page"
                    layout="total, sizes, prev, pager, next"
                    :total="total"
                    :page-sizes="[20, 50, 100]"
                    @current-change="fetchFileList"
                    @size-change="fetchFileList"
                />
            </div>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { Document, Search } from '@element-plus/icons-vue'
import { useClipboard } from '@vueuse/core'
import { ElMessage, ElMessageBox } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { fileApi } from '@/api/file'

const { t } = useI18n()
const { copy } = useClipboard()

const loading = ref(false)
const fileList = ref<any[]>([])
const total = ref(0)
const query = reactive({ keyword: '', group: '', mime_type: '', page: 1, limit: 20 })
const searchKeyword = ref('')
const currentGroup = ref('')
const mimeFilter = ref('')
const groups = ref<any[]>([])
const selectedIds = ref<number[]>([])

let searchTimer: ReturnType<typeof setTimeout> | null = null
const handleSearch = () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        query.keyword = searchKeyword.value
        query.page = 1
        fetchFileList()
    }, 300)
}

const fetchFileList = async () => {
    loading.value = true
    query.group = currentGroup.value
    query.mime_type = mimeFilter.value
    try {
        const res = await fileApi.getList(query)
        fileList.value = res.data.list || []
        total.value = res.data.pagination?.total || 0
    } catch (error) {
        console.error('获取文件列表失败:', error)
    } finally {
        loading.value = false
    }
}

const fetchGroups = async () => {
    try {
        const res = await fileApi.getGroups()
        groups.value = res.data || []
    } catch (error) {
        console.error('获取分组失败:', error)
    }
}

const selectGroup = (group: string) => {
    currentGroup.value = group
    fetchFileList()
}

const handleSelectionChange = (rows: any[]) => {
    selectedIds.value = rows.map((r) => r.id)
}

const handleCopyUrl = async (row: any) => {
    await copy(row.url)
    ElMessage.success(t('file.urlCopied'))
}

const handleRename = async (row: any) => {
    const { value } = await ElMessageBox.prompt(t('file.newFileName'), t('common.rename'), {
        inputValue: row.name,
        confirmButtonText: t('common.confirm'),
        cancelButtonText: t('common.cancel')
    })
    if (value && value !== row.name) {
        await fileApi.rename(row.id, value)
        ElMessage.success(t('file.renameSuccess'))
        fetchFileList()
    }
}

const handleDelete = async (row: any) => {
    await ElMessageBox.confirm(t('file.deleteFileConfirm', { name: row.name }), t('common.tip'), {
        type: 'warning'
    })
    await fileApi.delete(row.id)
    ElMessage.success(t('message.deleteSuccess'))
    fetchFileList()
    fetchGroups()
}

const handleBatchDelete = async () => {
    await ElMessageBox.confirm(
        t('file.batchDeleteFileConfirm', { count: selectedIds.value.length }),
        t('common.tip'),
        {
            type: 'warning'
        }
    )
    await fileApi.batchDelete(selectedIds.value)
    ElMessage.success(t('message.batchDeleteSuccess'))
    selectedIds.value = []
    fetchFileList()
    fetchGroups()
}

const formatSize = (size: number): string => {
    if (size < 1024) return size + ' B'
    if (size < 1048576) return (size / 1024).toFixed(1) + ' KB'
    if (size < 1073741824) return (size / 1048576).toFixed(1) + ' MB'
    return (size / 1073741824).toFixed(1) + ' GB'
}

onMounted(() => {
    fetchFileList()
    fetchGroups()
})
</script>

<style lang="scss" scoped>
.file-page {
    display: flex;
    gap: 16px;
    height: 100%;
}

.group-card {
    width: 200px;
    flex-shrink: 0;
}

.group-list {
    .group-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        cursor: pointer;
        border-radius: 4px;
        font-size: 14px;
        transition: all 0.2s;

        &:hover {
            background: var(--el-fill-color-light);
        }

        &.active {
            background: var(--el-color-primary-light-9);
            color: var(--el-color-primary);
            font-weight: 500;
        }
    }
}

.file-list-card {
    flex: 1;
    min-width: 0;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-left {
    display: flex;
    align-items: center;
}

.pagination-wrap {
    margin-top: 12px;
    display: flex;
    justify-content: flex-end;
}
</style>
