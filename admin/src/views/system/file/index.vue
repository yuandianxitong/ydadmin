<template>
    <div class="file-page flex flex-col gap-4 h-full">
        <div class="page-head">
            <div>
                <div class="page-title">文件管理</div>
                <div class="page-desc">所有上传文件集中管理</div>
            </div>
            <div class="page-actions">
                <el-button
                    v-has-perm="'system.file.delete'"
                    type="danger"
                    plain
                    :disabled="selectedIds.length === 0"
                    @click="handleBatchDelete"
                >
                    {{ $t('common.batchDelete') }}
                </el-button>
                <el-input
                    v-model="searchKeyword"
                    :placeholder="$t('file.searchPlaceholder')"
                    clearable
                    size="default"
                    style="width: 220px"
                    @input="handleSearch"
                >
                    <template #prefix><i class="i-svg:search" /></template>
                </el-input>
            </div>
        </div>

        <div class="fm-layout">
            <!-- Left sidebar: type filter -->
            <aside class="fm-side">
                <div class="fm-side-t">分类</div>
                <div class="fm-side-list flex flex-col gap-0.5">
                    <button
                        v-for="t in typeOptions"
                        :key="t.value"
                        :class="[
                            'fm-side-item flex items-center gap-2.5 px-2.5 py-2 rounded-md',
                            { on: currentType === t.value }
                        ]"
                        @click="selectType(t.value)"
                    >
                        <span class="ic" v-html="t.icon" />
                        <span class="l">{{ $t(t.label) }}</span>
                        <span
                            class="c"
                            :title="t.value ? $t('proPage.currentPageCount') : $t('file.allFiles')"
                            >{{ typeCounts[t.value] || 0 }}</span
                        >
                    </button>
                </div>

                <div class="fm-side-t" style="margin-top: 20px">{{ $t('file.fileGroup') }}</div>
                <div class="fm-side-list flex flex-col gap-0.5">
                    <button
                        class="fm-side-item flex items-center gap-2.5 px-2.5 py-2 rounded-md"
                        :class="{ on: currentGroup === '' }"
                        @click="selectGroup('')"
                    >
                        <span class="l">{{ $t('file.allFiles') }}</span>
                    </button>
                    <button
                        v-for="g in groups"
                        :key="g.group"
                        class="fm-side-item flex items-center gap-2.5 px-2.5 py-2 rounded-md"
                        :class="{ on: currentGroup === g.group }"
                        @click="selectGroup(g.group)"
                    >
                        <span class="l">{{ g.group }}</span
                        ><span class="c">{{ g.count }}</span>
                    </button>
                </div>
            </aside>

            <!-- Right: toolbar + file grid/list -->
            <div class="fm-main">
                <!-- Toolbar -->
                <div class="fm-toolbar flex-between gap-2.5">
                    <div class="fm-toolbar-l flex items-center gap-2.5 flex-wrap">
                        <template v-if="selectedIds.length > 0">
                            <label class="fm-chkall inline-flex items-center gap-2 py-1">
                                <input
                                    type="checkbox"
                                    :checked="allShownSelected"
                                    @change="toggleAll"
                                />
                                <span
                                    >已选 <b>{{ selectedIds.length }}</b> 项</span
                                >
                            </label>
                            <button class="fm-link" @click="clearSelection">取消选择</button>
                        </template>
                        <template v-else>
                            <label class="fm-chkall inline-flex items-center gap-2 py-1">
                                <input type="checkbox" :checked="false" @change="toggleAll" />
                                <span
                                    >共 <b>{{ total }}</b> 个文件</span
                                >
                            </label>
                            <div class="fm-sep" />
                            <div class="fm-sort inline-flex items-center gap-0.5">
                                <span class="lb">排序</span>
                                <button
                                    v-for="s in sortOptions"
                                    :key="s.value"
                                    :class="{ on: currentSort === s.value }"
                                    @click="setSort(s.value)"
                                >
                                    {{ s.label }}
                                </button>
                            </div>
                        </template>
                    </div>
                    <div class="fm-toolbar-r flex items-center gap-2">
                        <div class="fm-view">
                            <button
                                :class="{ on: viewMode === 'grid' }"
                                title="网格视图"
                                @click="viewMode = 'grid'"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    width="14"
                                    height="14"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <rect x="3" y="3" width="7" height="7" />
                                    <rect x="14" y="3" width="7" height="7" />
                                    <rect x="3" y="14" width="7" height="7" />
                                    <rect x="14" y="14" width="7" height="7" />
                                </svg>
                            </button>
                            <button
                                :class="{ on: viewMode === 'list' }"
                                title="列表视图"
                                @click="viewMode = 'list'"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    width="14"
                                    height="14"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Loading / Empty -->
                <div v-if="loading" v-loading="true" class="fm-loading" />

                <div
                    v-else-if="fileList.length === 0"
                    class="fm-empty flex-center flex-1 min-h-300px"
                >
                    <el-empty :description="$t('file.empty')" />
                </div>

                <!-- Grid view -->
                <div v-else-if="viewMode === 'grid'" class="fm-grid">
                    <div
                        v-for="file in sortedFiles"
                        :key="file.id"
                        :class="['fm-tile', { on: selectedIds.includes(file.id) }]"
                        @click="toggleSelect(file)"
                    >
                        <div class="fm-tile-thumb" :style="{ background: tileBg(file) }">
                            <!-- Extension tag -->
                            <span class="fm-ext-tag" :style="{ background: extColor(file) }">{{
                                (file.extension || '').toUpperCase()
                            }}</span>

                            <!-- Image thumbnail -->
                            <template v-if="isImage(file)">
                                <el-image
                                    :src="file.url"
                                    :preview-src-list="[file.url]"
                                    :preview-teleported="true"
                                    fit="cover"
                                    class="fm-thumb-img"
                                    @click.stop
                                >
                                    <template #error>
                                        <div class="fm-big-ic" :style="{ color: extColor(file) }">
                                            <span v-html="getFileIcon(file).icon" />
                                        </div>
                                    </template>
                                </el-image>
                            </template>

                            <!-- Video placeholder -->
                            <template v-else-if="isVideo(file)">
                                <div class="fm-play">
                                    <svg viewBox="0 0 24 24" width="22" height="22" fill="#fff">
                                        <path d="M8 5v14l11-7z" />
                                    </svg>
                                </div>
                            </template>

                            <!-- Other file type icon -->
                            <template v-else>
                                <div class="fm-big-ic" :style="{ color: extColor(file) }">
                                    <span v-html="getFileIcon(file).icon" />
                                </div>
                            </template>

                            <!-- Checkbox -->
                            <label class="fm-tile-chk" @click.stop>
                                <input
                                    type="checkbox"
                                    :checked="selectedIds.includes(file.id)"
                                    @change.stop="toggleSelect(file)"
                                />
                            </label>

                            <!-- More menu button -->
                            <el-dropdown
                                class="fm-tile-more-wrap"
                                trigger="click"
                                placement="bottom-end"
                                @command="(cmd: string) => handleCommand(cmd, file)"
                            >
                                <button class="fm-tile-more" @click.stop>
                                    <svg
                                        viewBox="0 0 24 24"
                                        width="14"
                                        height="14"
                                        fill="currentColor"
                                    >
                                        <circle cx="5" cy="12" r="1.6" />
                                        <circle cx="12" cy="12" r="1.6" />
                                        <circle cx="19" cy="12" r="1.6" />
                                    </svg>
                                </button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="copy">
                                            <i class="i-svg:copy" />
                                            {{ $t('common.copy') }}
                                        </el-dropdown-item>
                                        <el-dropdown-item
                                            v-if="hasPerm('system.file.update')"
                                            command="rename"
                                        >
                                            <i class="i-svg:pencil" />
                                            {{ $t('common.rename') }}
                                        </el-dropdown-item>
                                        <el-dropdown-item
                                            v-if="hasPerm('system.file.delete')"
                                            command="delete"
                                            divided
                                        >
                                            <i class="i-svg:trash-2" />
                                            <span style="color: var(--el-color-danger)">{{
                                                $t('common.delete')
                                            }}</span>
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>

                        <div class="fm-tile-meta">
                            <div class="nm" :title="file.name">{{ file.name }}</div>
                            <div class="info">
                                <span>{{ formatSize(file.size) }}</span>
                                <span class="dot">&middot;</span>
                                <span>{{ formatShortTime(file.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- List view -->
                <div v-else class="fm-list">
                    <div class="fm-list-head">
                        <div style="width: 28px">
                            <input
                                type="checkbox"
                                :checked="allShownSelected"
                                @change="toggleAll"
                            />
                        </div>
                        <div style="flex: 1 1 320px">名称</div>
                        <div style="width: 100px">大小</div>
                        <div style="width: 90px">存储</div>
                        <div style="width: 150px">上传时间</div>
                        <div style="width: 90px; text-align: right">操作</div>
                    </div>
                    <div
                        v-for="file in sortedFiles"
                        :key="file.id"
                        :class="['fm-list-row', { on: selectedIds.includes(file.id) }]"
                        @click="toggleSelect(file)"
                    >
                        <div style="width: 28px" @click.stop>
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(file.id)"
                                @change.stop="toggleSelect(file)"
                            />
                        </div>
                        <div
                            style="
                                flex: 1 1 320px;
                                display: flex;
                                align-items: center;
                                gap: 10px;
                                min-width: 0;
                            "
                        >
                            <span class="fm-list-ext" :style="{ background: extColor(file) }">{{
                                (file.extension || '').toUpperCase()
                            }}</span>
                            <span class="fm-list-nm">{{ file.name }}</span>
                        </div>
                        <div style="width: 100px; font-size: 12px; color: var(--ink-600)">
                            {{ formatSize(file.size) }}
                        </div>
                        <div style="width: 90px; font-size: 12px; color: var(--ink-600)">
                            {{ file.storage || '本地' }}
                        </div>
                        <div style="width: 150px; font-size: 12px; color: var(--ink-500)">
                            {{ formatTime(file.created_at) }}
                        </div>
                        <div style="width: 90px; text-align: right">
                            <el-dropdown
                                trigger="click"
                                placement="bottom-end"
                                @command="(cmd: string) => handleCommand(cmd, file)"
                            >
                                <button class="fm-list-more" @click.stop>
                                    <svg
                                        viewBox="0 0 24 24"
                                        width="14"
                                        height="14"
                                        fill="currentColor"
                                    >
                                        <circle cx="5" cy="12" r="1.6" />
                                        <circle cx="12" cy="12" r="1.6" />
                                        <circle cx="19" cy="12" r="1.6" />
                                    </svg>
                                </button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="copy">
                                            <i class="i-svg:copy" />
                                            {{ $t('common.copy') }}
                                        </el-dropdown-item>
                                        <el-dropdown-item
                                            v-if="hasPerm('system.file.update')"
                                            command="rename"
                                        >
                                            <i class="i-svg:pencil" />
                                            {{ $t('common.rename') }}
                                        </el-dropdown-item>
                                        <el-dropdown-item
                                            v-if="hasPerm('system.file.delete')"
                                            command="delete"
                                            divided
                                        >
                                            <i class="i-svg:trash-2" />
                                            <span style="color: var(--el-color-danger)">{{
                                                $t('common.delete')
                                            }}</span>
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="total > 0" class="fm-pagination">
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
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Check } from '@element-plus/icons-vue'
import { useClipboard } from '@vueuse/core'
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
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
    all: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
    image: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="m21 15-5-5L8 18"/></svg>',
    video: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="14" height="14" rx="2"/><path d="m17 10 4-2v8l-4-2"/></svg>',
    audio: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
    document: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h4"/></svg>',
    archive: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M12 10v2M12 14v2M12 18v2"/></svg>',
    other: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16v.1"/></svg>'
}

// 文件类型配色（typeOptions 和 ICON_MAP 共用）
const FILE_TYPE_COLORS: Record<string, { color: string; icon: string }> = {
    all:      { color: '#64748b', icon: SVG.all },
    image:    { color: '#10b981', icon: SVG.image },
    video:    { color: '#8b5cf6', icon: SVG.video },
    audio:    { color: '#ec4899', icon: SVG.audio },
    document: { color: '#3b82f6', icon: SVG.document },
    archive:  { color: '#f59e0b', icon: SVG.archive },
    other:    { color: '#94a3b8', icon: SVG.other }
}

const typeOptions = [
    { value: '', label: 'file.allFiles', ...FILE_TYPE_COLORS.all },
    { value: 'image', label: 'file.image', ...FILE_TYPE_COLORS.image },
    { value: 'video', label: 'file.video', ...FILE_TYPE_COLORS.video },
    { value: 'document', label: 'file.document', ...FILE_TYPE_COLORS.document },
    { value: 'audio', label: 'file.audio', ...FILE_TYPE_COLORS.audio },
    { value: 'archive', label: 'file.archive', ...FILE_TYPE_COLORS.archive },
    { value: 'other', label: 'file.other', ...FILE_TYPE_COLORS.other }
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

// 文件类型 → 图标 + 颜色 + CSS class（渐变背景通过 CSS 控制，支持暗黑模式）
const ICON_MAP: Record<string, { icon: string; color: string; cssClass: string }> = {
    image:    { ...FILE_TYPE_COLORS.image, cssClass: 'type-image' },
    video:    { ...FILE_TYPE_COLORS.video, cssClass: 'type-video' },
    audio:    { ...FILE_TYPE_COLORS.audio, cssClass: 'type-audio' },
    document: { ...FILE_TYPE_COLORS.document, cssClass: 'type-document' },
    archive:  { ...FILE_TYPE_COLORS.archive, cssClass: 'type-archive' },
    other:    { ...FILE_TYPE_COLORS.other, cssClass: 'type-other' }
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

// View mode (grid / list) and sort
const viewMode = ref<'grid' | 'list'>('grid')
const currentSort = ref<'time' | 'size' | 'name'>('time')

const sortOptions = [
    { value: 'time' as const, label: '上传时间' },
    { value: 'name' as const, label: '名称' },
    { value: 'size' as const, label: '大小' }
]

const setSort = (s: 'time' | 'size' | 'name') => {
    currentSort.value = s
}

// Client-side sort on top of the server-provided list
const sortedFiles = computed(() => {
    const list = [...fileList.value]
    if (currentSort.value === 'time') {
        list.sort((a, b) => (b.created_at || '').localeCompare(a.created_at || ''))
    } else if (currentSort.value === 'name') {
        list.sort((a, b) => (a.name || '').localeCompare(b.name || ''))
    } else if (currentSort.value === 'size') {
        list.sort((a, b) => (b.size || 0) - (a.size || 0))
    }
    return list
})

// Check if all shown files are selected
const allShownSelected = computed(() => {
    if (sortedFiles.value.length === 0) return false
    return sortedFiles.value.every(f => selectedIds.value.includes(f.id))
})

const toggleAll = () => {
    if (allShownSelected.value) {
        // Deselect all shown
        const shownIds = new Set(sortedFiles.value.map(f => f.id))
        selectedIds.value = selectedIds.value.filter(id => !shownIds.has(id))
    } else {
        // Select all shown
        const currentSet = new Set(selectedIds.value)
        for (const f of sortedFiles.value) {
            currentSet.add(f.id)
        }
        selectedIds.value = [...currentSet]
    }
}

// Type counts (computed from file list when possible, otherwise mock)
const typeCounts = computed(() => {
    const c: Record<string, number> = { '': total.value }
    for (const f of fileList.value) {
        const t = getFileType(f)
        c[t] = (c[t] || 0) + 1
    }
    // The total is the API total, not just current page
    return c
})


const isVideo = (file: any) => file.mime_type?.startsWith('video/')

// Determine a file's category type
const getFileType = (file: any): string => {
    const mime = file.mime_type || ''
    const ext = (file.extension || '').toLowerCase()
    if (mime.startsWith('image/')) return 'image'
    if (mime.startsWith('video/')) return 'video'
    if (mime.startsWith('audio/')) return 'audio'
    if (['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'md'].includes(ext))
        return 'document'
    if (['zip', 'rar', '7z', 'tar', 'gz', 'bz2'].includes(ext)) return 'archive'
    return 'other'
}


// Extension color map (per YDAdmin design)
const EXT_COLOR_MAP: Record<string, string> = {
    jpg: '#6366f1', jpeg: '#6366f1', png: '#6366f1', svg: '#6366f1', gif: '#6366f1', webp: '#6366f1',
    mp4: '#a855f7', mov: '#a855f7', webm: '#a855f7', avi: '#a855f7',
    pdf: '#ef4444', doc: '#2563eb', docx: '#2563eb',
    xls: '#16a34a', xlsx: '#16a34a', ppt: '#f97316', pptx: '#f97316',
    txt: '#64748b', csv: '#64748b', md: '#64748b',
    mp3: '#14b8a6', wav: '#14b8a6', flac: '#14b8a6',
    zip: '#d97444', rar: '#d97444', '7z': '#d97444', tar: '#d97444', gz: '#d97444'
}

const extColor = (file: any): string => {
    const ext = (file.extension || '').toLowerCase()
    return EXT_COLOR_MAP[ext] || '#64748b'
}

const tileBg = (file: any): string => {
    const c = extColor(file)
    return `linear-gradient(135deg, ${c}14 0%, ${c}0a 100%)`
}


const formatShortTime = (time: string): string => {
    if (!time) return ''
    return time.slice(5, 10).replace('T', ' ')
}


</script>

<style lang="scss" scoped>
/* ============ File Manager Layout ============ */
.file-page {
    /* layout via unocss class */
}

.fm-layout {
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 16px;
    align-items: flex-start;
}

/* ---------- Left sidebar: type ---------- */
.fm-side {
    background: var(--el-bg-color);
    border: 1px solid var(--ink-100);
    border-radius: 8px;
    padding: 14px 12px;
    position: sticky;
    top: 0;
}

.fm-side-t {
    font-size: 11px;
    color: var(--ink-400);
    font-weight: 500;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 0 6px 8px;
}

.fm-side-item {
    background: transparent;
    border: none;
    cursor: pointer;
    color: var(--ink-700);
    font-size: 13px;
    transition: background 0.12s;
    text-align: left;
    width: 100%;

    &:hover {
        background: var(--ink-50);
    }

    &.on {
        background: var(--brand-50);
        color: var(--brand-600);
        font-weight: 500;

        .ic {
            color: var(--brand-500);
        }

        .c {
            color: var(--brand-500);
        }
    }

    .ic {
        color: var(--ink-400);
        display: inline-flex;
        width: 14px;
        height: 14px;
        flex-shrink: 0;

        :deep(svg) {
            width: 100%;
            height: 100%;
        }
    }

    .l {
        flex: 1;
    }

    .c {
        font-size: 11.5px;
        color: var(--ink-400);
    }
}

/* ---------- Right main area ---------- */
.fm-main {
    background: var(--el-bg-color);
    border: 1px solid var(--ink-100);
    border-radius: 8px;
    min-height: 640px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Toolbar */
.fm-toolbar {
    padding: 10px 14px;
    border-bottom: 1px solid var(--ink-100);
    background: var(--el-bg-color);
}

.fm-chkall {
    font-size: 12.5px;
    color: var(--ink-600);
    cursor: pointer;

    b {
        color: var(--ink-900);
        font-weight: 600;
    }

    input {
        cursor: pointer;
    }
}

.fm-sep {
    width: 1px;
    height: 16px;
    background: var(--ink-200);
}

.fm-sort {
    .lb {
        font-size: 12px;
        color: var(--ink-400);
        margin-right: 6px;
    }

    button {
        padding: 3px 10px;
        border-radius: 4px;
        font-size: 12px;
        color: var(--ink-600);
        background: transparent;
        border: 1px solid transparent;
        cursor: pointer;

        &:hover {
            color: var(--ink-800);
        }

        &.on {
            background: var(--ink-50);
            color: var(--brand-600);
            border-color: var(--ink-200);
        }
    }
}

.fm-link {
    background: transparent;
    border: none;
    padding: 0;
    color: var(--brand-500);
    font-size: 12px;
    cursor: pointer;

    &:hover {
        text-decoration: underline;
    }
}

.fm-view {
    display: inline-flex;
    border: 1px solid var(--ink-200);
    border-radius: 4px;
    overflow: hidden;
    background: var(--el-bg-color);

    button {
        width: 30px;
        height: 26px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--el-bg-color);
        border: none;
        color: var(--ink-500);
        cursor: pointer;

        & + button {
            border-left: 1px solid var(--ink-200);
        }

        &:hover {
            color: var(--ink-800);
        }

        &.on {
            background: var(--brand-50);
            color: var(--brand-500);
        }
    }
}

/* Loading / empty states */
.fm-loading {
    flex: 1;
    min-height: 300px;
}

.fm-empty {
    /* layout via unocss class */
}

/* ---------- Grid view ---------- */
.fm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(168px, 1fr));
    gap: 14px;
    padding: 16px;
}

.fm-tile {
    background: var(--el-bg-color);
    border: 1px solid var(--ink-100);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s;
    display: flex;
    flex-direction: column;
    position: relative;

    &:hover {
        border-color: var(--ink-200);
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
        transform: translateY(-1px);

        .fm-tile-chk,
        .fm-tile-more-wrap {
            opacity: 1;
        }
    }

    &.on {
        border-color: var(--brand-500);
        box-shadow:
            0 0 0 2px var(--brand-100),
            0 4px 14px rgba(79, 107, 255, 0.12);

        .fm-tile-chk {
            opacity: 1;
        }
    }
}

.fm-tile-thumb {
    height: 120px;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 8px 8px 0 0;
}

.fm-thumb-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;

    :deep(img) {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
}

.fm-play {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    padding-left: 3px;
    z-index: 1;
}

.fm-big-ic {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;

    :deep(svg) {
        width: 36px;
        height: 36px;
    }
}

.fm-ext-tag {
    position: absolute;
    left: 8px;
    bottom: 8px;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 9.5px;
    font-weight: 700;
    color: #fff;
    letter-spacing: 0.04em;
    z-index: 2;
}

.fm-tile-chk {
    position: absolute;
    left: 8px;
    top: 8px;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 4px;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.15s;
    z-index: 2;

    input {
        cursor: pointer;
        margin: 0;
    }
}

.fm-tile-more-wrap {
    position: absolute;
    right: 6px;
    top: 6px;
    opacity: 0;
    transition: opacity 0.15s;
    z-index: 2;
}

.fm-tile-more {
    width: 24px;
    height: 24px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    color: var(--ink-600);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s;

    &:hover {
        background: var(--card-bg);
        color: var(--ink-900);
    }
}

.fm-tile-meta {
    padding: 8px 10px 10px;
    border-top: 1px solid var(--ink-100);
    border-radius: 0 0 8px 8px;

    .nm {
        font-size: 12.5px;
        color: var(--ink-800);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        margin-bottom: 2px;
    }

    .info {
        font-size: 11px;
        color: var(--ink-400);
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .dot {
        color: var(--ink-300);
    }
}

/* ---------- List view ---------- */
.fm-list {
    padding: 0;
}

.fm-list-head {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    background: var(--ink-50);
    border-bottom: 1px solid var(--ink-100);
    font-size: 11.5px;
    color: var(--ink-500);
    font-weight: 500;
}

.fm-list-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    border-bottom: 1px solid var(--ink-100);
    font-size: 13px;
    color: var(--ink-700);
    cursor: pointer;
    transition: background 0.1s;

    &:hover {
        background: var(--ink-50);
    }

    &.on {
        background: var(--brand-50);
    }

    input {
        cursor: pointer;
        margin: 0;
    }
}

.fm-list-ext {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 22px;
    padding: 0 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 700;
    color: #fff;
    letter-spacing: 0.04em;
    flex-shrink: 0;
}

.fm-list-nm {
    font-size: 13px;
    color: var(--ink-800);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.fm-list-more {
    width: 24px;
    height: 24px;
    border-radius: 4px;
    background: transparent;
    border: none;
    color: var(--ink-500);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;

    &:hover {
        background: var(--ink-100);
        color: var(--ink-800);
    }
}

/* Pagination */
.fm-pagination {
    margin-top: auto;
    padding: 12px 16px;
    display: flex;
    justify-content: flex-end;
    border-top: 1px solid var(--ink-100);
}

@media (max-width: 768px) {
    .fm-layout {
        flex-direction: column;
    }
    .fm-side {
        width: 100%;
    }
    .fm-side-list {
        flex-direction: row;
        flex-wrap: wrap;
    }
    .fm-toolbar {
        flex-wrap: wrap;
    }
    .fm-pagination .el-pagination {
        flex-wrap: wrap;
        row-gap: 8px;
    }
}
</style>
