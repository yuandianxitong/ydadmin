<template>
    <div class="file-page">
        <!-- 左侧：文件类型 + 文件分组 -->
        <el-card class="side-card" shadow="never">
            <div class="side-section">
                <div class="side-title">{{ $t('file.fileType') }}</div>
                <div class="side-list">
                    <div
                        v-for="t in typeOptions"
                        :key="t.value"
                        :class="['side-item', { active: currentType === t.value }]"
                        @click="selectType(t.value)"
                    >
                        <span class="side-icon" :style="{ color: t.color }" v-html="t.icon" />
                        <span class="side-label">{{ $t(t.label) }}</span>
                    </div>
                </div>
            </div>

            <div class="side-section">
                <div class="side-title">{{ $t('file.fileGroup') }}</div>
                <div class="side-list">
                    <div
                        :class="['side-item', { active: currentGroup === '' }]"
                        @click="selectGroup('')"
                    >
                        <span class="group-dot" />
                        <span class="side-label">{{ $t('file.allFiles') }}</span>
                    </div>
                    <div
                        v-for="g in groups"
                        :key="g.group"
                        :class="['side-item', { active: currentGroup === g.group }]"
                        @click="selectGroup(g.group)"
                    >
                        <span class="group-dot" />
                        <span class="side-label">{{ g.group }}</span>
                        <el-tag class="side-count" size="small" type="info" round>{{ g.count }}</el-tag>
                    </div>
                </div>
            </div>
        </el-card>

        <!-- 右侧：工具栏 + 卡片网格 -->
        <el-card class="content-card" shadow="never">
            <template #header>
                <div class="content-header">
                    <div class="header-left">
                        <el-input
                            v-model="searchKeyword"
                            :placeholder="$t('file.searchPlaceholder')"
                            :prefix-icon="Search"
                            clearable
                            size="default"
                            style="width: 260px"
                            @input="handleSearch"
                        />
                    </div>
                    <div class="header-right">
                        <span v-if="selectedIds.length > 0" class="selected-count">
                            已选 {{ selectedIds.length }} 项
                        </span>
                        <el-button
                            v-if="selectedIds.length > 0"
                            size="default"
                            plain
                            @click="clearSelection"
                        >
                            取消选择
                        </el-button>
                        <el-button
                            v-has-perm="'system.file.delete'"
                            type="danger"
                            size="default"
                            :disabled="selectedIds.length === 0"
                            @click="handleBatchDelete"
                        >
                            {{ $t('common.batchDelete') }}
                        </el-button>
                    </div>
                </div>
            </template>

            <div v-loading="loading" class="grid-wrap">
                <div v-if="!loading && fileList.length === 0" class="empty-wrap">
                    <el-empty :description="$t('file.empty')" />
                </div>

                <div v-else class="file-grid">
                    <div
                        v-for="file in fileList"
                        :key="file.id"
                        :class="['file-card', { selected: selectedIds.includes(file.id) }]"
                        @click="toggleSelect(file)"
                    >
                        <!-- 预览区 -->
                        <div class="card-preview">
                            <el-image
                                v-if="isImage(file)"
                                :src="file.url"
                                :preview-src-list="[file.url]"
                                :preview-teleported="true"
                                fit="cover"
                                class="preview-image"
                                @click.stop
                            >
                                <template #error>
                                    <div class="preview-fallback">
                                        <span class="file-icon" v-html="getFileIcon(file).icon" />
                                    </div>
                                </template>
                            </el-image>
                            <div
                                v-else
                                class="preview-fallback"
                                :style="{ background: getFileIcon(file).bg }"
                            >
                                <span
                                    class="file-icon"
                                    :style="{ color: getFileIcon(file).color }"
                                    v-html="getFileIcon(file).icon"
                                />
                                <span class="file-ext">{{ (file.extension || '').toUpperCase() }}</span>
                            </div>

                            <!-- 选中勾选 -->
                            <div
                                class="check-badge"
                                :class="{ checked: selectedIds.includes(file.id) }"
                                @click.stop="toggleSelect(file)"
                            >
                                <el-icon v-if="selectedIds.includes(file.id)"><Check /></el-icon>
                            </div>

                            <!-- 更多操作 -->
                            <el-dropdown
                                class="more-action"
                                trigger="click"
                                placement="bottom-end"
                                @click.stop
                                @command="(cmd: string) => handleCommand(cmd, file)"
                            >
                                <el-button
                                    class="more-btn"
                                    circle
                                    size="small"
                                    @click.stop
                                >
                                    <el-icon><MoreFilled /></el-icon>
                                </el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="copy">
                                            <el-icon><CopyDocument /></el-icon>
                                            {{ $t('common.copy') }}
                                        </el-dropdown-item>
                                        <el-dropdown-item
                                            v-if="hasPerm('system.file.update')"
                                            command="rename"
                                        >
                                            <el-icon><Edit /></el-icon>
                                            {{ $t('common.rename') }}
                                        </el-dropdown-item>
                                        <el-dropdown-item
                                            v-if="hasPerm('system.file.delete')"
                                            command="delete"
                                            divided
                                        >
                                            <el-icon><Delete /></el-icon>
                                            <span style="color: var(--el-color-danger)">
                                                {{ $t('common.delete') }}
                                            </span>
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>

                        <!-- 信息 -->
                        <div class="card-info">
                            <div class="file-name" :title="file.name">{{ file.name }}</div>
                            <div class="file-meta">
                                <span>{{ formatSize(file.size) }}</span>
                                <span class="meta-dot">·</span>
                                <span class="meta-time">{{ formatTime(file.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 分页 -->
            <div v-if="total > 0" class="pagination-wrap">
                <el-pagination
                    v-model:page-size="query.limit"
                    v-model:current-page="query.page"
                    layout="total, sizes, prev, pager, next"
                    :total="total"
                    :page-sizes="[20, 40, 80, 120]"
                    @current-change="fetchFileList"
                    @size-change="fetchFileList"
                />
            </div>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import {
    Check,
    CopyDocument,
    Delete,
    Edit,
    MoreFilled,
    Search
} from '@element-plus/icons-vue'
import { useClipboard } from '@vueuse/core'
import { ElMessage, ElMessageBox } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { fileApi } from '@/api/file'
import { useUserStore } from '@/store'

const { t } = useI18n()
const { copy } = useClipboard()
const userStore = useUserStore()

const loading = ref(false)
const fileList = ref<any[]>([])
const total = ref(0)
const query = reactive({ keyword: '', group: '', mime_type: '', page: 1, limit: 40 })
const searchKeyword = ref('')
const currentGroup = ref('')
const currentType = ref('')
const groups = ref<any[]>([])
const selectedIds = ref<number[]>([])

// SVG 图标定义（24x24，currentColor）
const SVG = {
    all: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 6a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6Z" stroke="currentColor" stroke-width="1.6"/></svg>',
    image: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3.5" y="4.5" width="17" height="15" rx="2" stroke="currentColor" stroke-width="1.6"/><circle cx="9" cy="10" r="1.6" fill="currentColor"/><path d="m4 18 5-5 4 4 3-3 4 4" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>',
    video: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3.5" y="5.5" width="17" height="13" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M10 9.5v5l4.5-2.5L10 9.5Z" fill="currentColor"/></svg>',
    audio: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 18V7l10-2v11" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><circle cx="7" cy="18" r="2" stroke="currentColor" stroke-width="1.6"/><circle cx="17" cy="16" r="2" stroke="currentColor" stroke-width="1.6"/></svg>',
    document: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 3h8l4 4v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M14 3v4h4M8 13h8M8 17h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
    archive: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2H4V6Z" stroke="currentColor" stroke-width="1.6"/><path d="M5 8v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8" stroke="currentColor" stroke-width="1.6"/><path d="M11 12h2v2h-2zM11 15h2v2h-2z" fill="currentColor"/></svg>',
    other: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 3h8l4 4v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M14 3v4h4" stroke="currentColor" stroke-width="1.6"/></svg>'
}

const typeOptions = [
    { value: '', label: 'file.allFiles', icon: SVG.all, color: '#64748b' },
    { value: 'image', label: 'file.image', icon: SVG.image, color: '#10b981' },
    { value: 'video', label: 'file.video', icon: SVG.video, color: '#8b5cf6' },
    { value: 'audio', label: 'file.audio', icon: SVG.audio, color: '#ec4899' },
    { value: 'document', label: 'file.document', icon: SVG.document, color: '#3b82f6' },
    { value: 'archive', label: 'file.archive', icon: SVG.archive, color: '#f59e0b' },
    { value: 'other', label: 'file.other', icon: SVG.other, color: '#94a3b8' }
]

const hasPerm = (code: string) => userStore.hasPermission(code)

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
    query.mime_type = currentType.value
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
    query.page = 1
    fetchFileList()
}

const selectType = (type: string) => {
    currentType.value = type
    query.page = 1
    fetchFileList()
}

const isImage = (file: any) => file.mime_type?.startsWith('image/')

const ICON_MAP: Record<string, { icon: string; color: string; bg: string }> = {
    image: { icon: SVG.image, color: '#10b981', bg: 'linear-gradient(135deg,#ecfdf5,#d1fae5)' },
    video: { icon: SVG.video, color: '#8b5cf6', bg: 'linear-gradient(135deg,#f5f3ff,#ede9fe)' },
    audio: { icon: SVG.audio, color: '#ec4899', bg: 'linear-gradient(135deg,#fdf2f8,#fce7f3)' },
    document: { icon: SVG.document, color: '#3b82f6', bg: 'linear-gradient(135deg,#eff6ff,#dbeafe)' },
    archive: { icon: SVG.archive, color: '#f59e0b', bg: 'linear-gradient(135deg,#fffbeb,#fef3c7)' },
    other: { icon: SVG.other, color: '#64748b', bg: 'linear-gradient(135deg,#f8fafc,#f1f5f9)' }
}

const getFileIcon = (file: any) => {
    const mime = file.mime_type || ''
    const ext = (file.extension || '').toLowerCase()
    if (mime.startsWith('image/')) return ICON_MAP.image
    if (mime.startsWith('video/')) return ICON_MAP.video
    if (mime.startsWith('audio/')) return ICON_MAP.audio
    if (['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'md'].includes(ext))
        return ICON_MAP.document
    if (['zip', 'rar', '7z', 'tar', 'gz', 'bz2'].includes(ext)) return ICON_MAP.archive
    return ICON_MAP.other
}

const toggleSelect = (file: any) => {
    const idx = selectedIds.value.indexOf(file.id)
    if (idx >= 0) selectedIds.value.splice(idx, 1)
    else selectedIds.value.push(file.id)
}

const clearSelection = () => {
    selectedIds.value = []
}

const handleCommand = (cmd: string, file: any) => {
    if (cmd === 'copy') handleCopyUrl(file)
    else if (cmd === 'rename') handleRename(file)
    else if (cmd === 'delete') handleDelete(file)
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

const formatTime = (time: string): string => {
    if (!time) return ''
    return time.slice(0, 16).replace('T', ' ')
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

/* 左侧 */
.side-card {
    width: 220px;
    flex-shrink: 0;

    :deep(.el-card__body) {
        padding: 12px 8px;
    }
}

.side-section {
    & + .side-section {
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px dashed var(--el-border-color-lighter);
    }
}

.side-title {
    font-size: 12px;
    font-weight: 600;
    color: var(--el-text-color-secondary);
    padding: 0 12px 8px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.side-list {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.side-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    cursor: pointer;
    border-radius: 8px;
    font-size: 14px;
    color: var(--el-text-color-regular);
    transition: all 0.15s;

    &:hover {
        background: var(--el-fill-color-light);
    }

    &.active {
        background: var(--el-color-primary-light-9);
        color: var(--el-color-primary);
        font-weight: 500;

        .side-icon {
            color: var(--el-color-primary) !important;
        }
    }
}

.side-icon {
    display: inline-flex;
    width: 18px;
    height: 18px;

    :deep(svg) {
        width: 100%;
        height: 100%;
    }
}

.group-dot {
    width: 6px;
    height: 6px;
    margin: 0 6px;
    border-radius: 50%;
    background: var(--el-border-color);
    flex-shrink: 0;
}

.side-item.active .group-dot {
    background: var(--el-color-primary);
}

.side-label {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.side-count {
    height: 20px;
    line-height: 18px;
    padding: 0 8px;
    font-size: 11px;
}

/* 右侧 */
.content-card {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;

    :deep(.el-card__body) {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }
}

.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.selected-count {
    font-size: 13px;
    color: var(--el-color-primary);
    font-weight: 500;
}

.grid-wrap {
    flex: 1;
    min-height: 300px;
}

.empty-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 300px;
}

.file-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 16px;
}

/* 卡片 */
.file-card {
    position: relative;
    background: var(--el-bg-color);
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 10px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.2s;

    &:hover {
        border-color: var(--el-color-primary-light-5);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
        transform: translateY(-2px);

        .check-badge,
        .more-action {
            opacity: 1;
        }
    }

    &.selected {
        border-color: var(--el-color-primary);
        box-shadow: 0 0 0 2px var(--el-color-primary-light-7);

        .check-badge {
            opacity: 1;
        }
    }
}

.card-preview {
    position: relative;
    width: 100%;
    aspect-ratio: 4 / 3;
    background: var(--el-fill-color-lighter);
    overflow: hidden;
}

.preview-image {
    width: 100%;
    height: 100%;
    display: block;
}

.preview-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 8px;
}

.file-icon {
    display: inline-flex;
    width: 44px;
    height: 44px;

    :deep(svg) {
        width: 100%;
        height: 100%;
    }
}

.file-ext {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.5px;
    color: var(--el-text-color-regular);
    opacity: 0.75;
}

.check-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.95);
    border: 1.5px solid var(--el-border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: all 0.15s;
    color: #fff;
    z-index: 2;

    &.checked {
        background: var(--el-color-primary);
        border-color: var(--el-color-primary);
        opacity: 1;
    }

    :deep(svg) {
        width: 14px;
        height: 14px;
    }
}

.more-action {
    position: absolute;
    top: 6px;
    right: 6px;
    opacity: 0;
    transition: opacity 0.15s;
    z-index: 2;
}

.more-btn {
    background: rgba(255, 255, 255, 0.95) !important;
    border-color: var(--el-border-color-lighter) !important;
    backdrop-filter: blur(4px);
}

.card-info {
    padding: 10px 12px 12px;
}

.file-name {
    font-size: 13px;
    color: var(--el-text-color-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 4px;
    line-height: 1.4;
}

.file-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--el-text-color-secondary);
}

.meta-dot {
    opacity: 0.6;
}

.meta-time {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* 分页 */
.pagination-wrap {
    margin-top: 16px;
    padding-top: 12px;
    display: flex;
    justify-content: flex-end;
    border-top: 1px solid var(--el-border-color-lighter);
}
</style>
