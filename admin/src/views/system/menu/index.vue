<template>
    <div class="menu-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('menu.menuName')">
                    <el-input
                        v-model="searchForm.title"
                        :placeholder="$t('menu.searchPlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('menu.statusPlaceholder')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('common.normal')" :value="1" />
                        <el-option :label="$t('common.disable')" :value="0" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('menu.menuType')">
                    <el-select
                        v-model="searchForm.type"
                        :placeholder="$t('menu.typePlaceholder')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('menu.typeOptions.directory')" :value="1" />
                        <el-option :label="$t('menu.typeOptions.menu')" :value="2" />
                        <el-option :label="$t('menu.typeOptions.button')" :value="3" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="getMenuList">
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
                <div class="table-title">{{ $t('menu.title') }}</div>
                <div class="table-actions">
                    <el-button @click="expandAll">
                        {{ isExpandAll ? $t('common.collapseAll') : $t('common.expandAll') }}
                    </el-button>
                    <el-button
                        v-has-perm="['system.menu.create']"
                        type="primary"
                        @click="handleAdd()"
                    >
                        <el-icon><Plus /></el-icon>
                        {{ $t('menu.addMenu') }}
                    </el-button>
                    <el-button
                        v-has-perm="['system.menu.delete']"
                        type="danger"
                        :disabled="!multipleSelection.length"
                        @click="handleBatchDelete"
                    >
                        <el-icon><Delete /></el-icon>
                        {{ $t('common.batchDelete') }}
                    </el-button>
                </div>
            </div>

            <!-- 表格 -->
            <el-table
                :key="tableKey"
                ref="tableRef"
                v-loading="loading"
                :data="menuList"
                row-key="id"
                :tree-props="{ children: 'children', hasChildren: 'hasChildren' }"
                :default-expand-all="isExpandAll"
                @selection-change="handleSelectionChange"
            >
                <el-table-column type="selection" width="55" />

                <el-table-column :label="$t('menu.menuName')" prop="title" min-width="200">
                    <template #default="{ row }">
                        <div class="menu-title-cell" :data-row-id="row.id">
                            <span>{{ row.title }}</span>
                            <span v-if="row.permission" class="ml-2 text-xs text-gray-400">{{
                                row.permission
                            }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('menu.menuType')" prop="type" width="90">
                    <template #default="{ row }">
                        <el-tag :type="getTypeTagType(row.type)" size="small">
                            {{ getTypeText(row.type) }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column
                    :label="$t('menu.componentPath')"
                    prop="component"
                    width="200"
                    show-overflow-tooltip
                />

                <el-table-column :label="$t('common.sort')" prop="sort" width="80" />

                <el-table-column :label="$t('common.status')" prop="status" width="100">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.status"
                            :active-value="1"
                            :inactive-value="0"
                            :disabled="!userStore.hasPermission('system.menu.update')"
                            @change="handleStatusChange(row)"
                        />
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.createdAt')" prop="created_at" width="160" />

                <el-table-column :label="$t('common.operation')" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-has-perm="['system.menu.create']"
                            type="primary"
                            size="small"
                            text
                            @click="handleAdd(row)"
                        >
                            {{ $t('common.add') }}
                        </el-button>
                        <el-button
                            v-has-perm="['system.menu.update']"
                            type="primary"
                            size="small"
                            text
                            @click="handleEdit(row)"
                        >
                            {{ $t('common.edit') }}
                        </el-button>
                        <el-button
                            v-has-perm="['system.menu.delete']"
                            type="danger"
                            size="small"
                            text
                            @click="handleDelete(row)"
                        >
                            {{ $t('common.delete') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 新增/编辑弹窗 -->
        <MenuForm
            v-model="formVisible"
            :form-data="formData"
            :parent-options="parentOptions"
            @success="getMenuList"
        />
    </div>
</template>

<script setup lang="ts" name="MenuList">
import { Delete, Edit, Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import Sortable from 'sortablejs'
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { menuApi } from '@/api/menu'
import { useUserStore } from '@/store'
import type { MenuInfo, MenuQuery } from '@/types/system'

import MenuForm from './components/MenuForm.vue'

const { t } = useI18n()
const userStore = useUserStore()

// 表格引用
const tableRef = ref()

// 搜索表单
const searchForm = reactive<MenuQuery>({
    title: '',
    status: undefined,
    type: undefined
})

// 菜单列表
const menuList = ref<MenuInfo[]>([])
const loading = ref(false)

// 表格选择
const multipleSelection = ref<MenuInfo[]>([])

// 展开状态
const isExpandAll = ref(false)
const tableKey = ref(0)

// 弹窗相关
const formVisible = ref(false)
const formData = ref<Partial<MenuInfo>>({})
// 父级菜单选项：后端返回的是带 children 的树形结构（ArrayHelper::toTree），
// 直接传给 MenuForm 的 el-tree-select 即可
const parentOptions = ref<any[]>([])
const idMap = ref<Record<number, any>>({})
let sortable: Sortable | null = null

// 获取菜单列表
const getMenuList = async () => {
    try {
        loading.value = true
        const params: MenuQuery = {}
        if (searchForm.title?.trim()) params.title = searchForm.title.trim()
        if (searchForm.status !== undefined) params.status = searchForm.status
        if (searchForm.type) params.type = searchForm.type

        const response = await menuApi.getMenuList(params)
        menuList.value = response.data
        buildIdMap(menuList.value)

        await nextTick()
        setTimeout(() => {
            initRowDrag()
        }, 300)
    } catch (error) {
        ElMessage.error(t('message.fetchFailed'))
    } finally {
        loading.value = false
    }
}

// 获取父级菜单选项
const getParentOptions = async (excludeId?: number) => {
    try {
        const response = await menuApi.getMenuOptions(excludeId)
        parentOptions.value = (response.data || []) as any[]
    } catch (error) {
        ElMessage.error(t('message.fetchFailed'))
    }
}

// 重置搜索
const resetSearch = () => {
    Object.assign(searchForm, {
        title: '',
        status: undefined,
        type: undefined
    })
    getMenuList()
}

// 展开/折叠
const expandAll = () => {
    isExpandAll.value = !isExpandAll.value
    tableKey.value++
    nextTick().then(() => {
        setTimeout(() => {
            initRowDrag()
        }, 300)
    })
}

// 表格选择变化
const handleSelectionChange = (selection: MenuInfo[]) => {
    multipleSelection.value = selection
}

// 状态变更
const handleStatusChange = async (row: MenuInfo) => {
    try {
        await menuApi.updateMenuStatus(row.id, { status: row.status })
        ElMessage.success(t('message.statusUpdateSuccess'))
    } catch (error) {
        row.status = row.status === 1 ? 0 : 1
        ElMessage.error(t('message.statusUpdateFailed'))
    }
}

// 新增菜单
const handleAdd = (parent?: MenuInfo) => {
    formData.value = {
        parent_id: parent?.id || 0,
        status: 1,
        sort: 100
    }
    getParentOptions()
    formVisible.value = true
}

// 编辑菜单
const handleEdit = (row: MenuInfo) => {
    formData.value = { ...row }
    getParentOptions(row.id)
    formVisible.value = true
}

// 删除菜单
const handleDelete = async (row: MenuInfo) => {
    try {
        await ElMessageBox.confirm(
            t('message.deleteConfirmName', { name: row.title }),
            t('message.confirmDelete'),
            {
                confirmButtonText: t('common.confirm'),
                cancelButtonText: t('common.cancel'),
                type: 'warning'
            }
        )

        await menuApi.deleteMenu(row.id)
        ElMessage.success(t('message.deleteSuccess'))
        getMenuList()
    } catch (error) {
        if (error !== 'cancel') {
            ElMessage.error(t('common.error'))
        }
    }
}

// 批量删除
const handleBatchDelete = async () => {
    try {
        await ElMessageBox.confirm(
            t('message.batchDeleteConfirmCount', {
                count: multipleSelection.value.length,
                type: t('menu.title')
            }),
            t('message.confirmBatchDelete'),
            {
                confirmButtonText: t('common.confirm'),
                cancelButtonText: t('common.cancel'),
                type: 'warning'
            }
        )

        const ids = multipleSelection.value.map((item) => item.id)
        await menuApi.batchDeleteMenu({ ids })
        ElMessage.success(t('message.batchDeleteSuccess'))
        getMenuList()
    } catch (error) {
        if (error !== 'cancel') {
            ElMessage.error(t('common.error'))
        }
    }
}

const buildIdMap = (list: any[]) => {
    const map: Record<number, any> = {}
    const dfs = (arr: any[], level = 0, parentId = 0) => {
        for (const it of arr) {
            map[it.id] = { ...it, level, parent_id: it.parent_id ?? parentId }
            if (it.children?.length) dfs(it.children, level + 1, it.id)
        }
    }
    dfs(list)
    idMap.value = map
}

// 获取表格主体
const getTbody = () => {
    const root = (tableRef.value as any)?.$el as HTMLElement | undefined
    if (!root) return null

    const tbody = root.querySelector(
        '.el-table__body-wrapper:not(.el-table__fixed) tbody'
    ) as HTMLTableSectionElement | null
    return tbody
}

// 获取所有数据行
const getAllDataRowsInDom = (): HTMLTableRowElement[] => {
    const tbody = getTbody()
    if (!tbody) return []
    return Array.from(tbody.querySelectorAll('tr.el-table__row')) as HTMLTableRowElement[]
}

// 获取行ID - 多种方法尝试
const getRowIdFromTr = (tr: HTMLTableRowElement) => {
    // 方法1: 从 data-row-key 获取
    let val = tr.getAttribute('data-row-key')
    if (val) return Number(val)

    // 方法2: 从菜单名称单元格的 data-row-id 获取
    const cell = tr.querySelector('.menu-title-cell[data-row-id]')
    if (cell) {
        val = cell.getAttribute('data-row-id')
        if (val) return Number(val)
    }

    // 方法3: 通过行索引从数据获取
    const tbody = tr.parentElement as HTMLTableSectionElement
    if (tbody) {
        const rows = Array.from(tbody.querySelectorAll('tr.el-table__row'))
        const index = rows.indexOf(tr)
        if (index >= 0) {
            const flatData = getFlatMenuData()
            if (flatData[index]?.id) {
                return Number(flatData[index].id)
            }
        }
    }

    return undefined
}

// 获取扁平化的菜单数据（按表格显示顺序）
const getFlatMenuData = () => {
    const result: any[] = []
    const flatten = (list: any[]) => {
        for (const item of list) {
            result.push(item)
            if (item.children?.length) {
                flatten(item.children)
            }
        }
    }
    flatten(menuList.value)
    return result
}

// 读层级
const getLevelFromTr = (tr: HTMLTableRowElement) => {
    const m = tr.className.match(/el-table__row--level-(\d+)/)
    return m ? Number(m[1]) : 0
}

// 初始化拖拽
const initRowDrag = async () => {
    if (sortable) {
        sortable.destroy()
        sortable = null
    }

    await nextTick()

    const tbody = getTbody()
    if (!tbody) return

    const rows = getAllDataRowsInDom()
    if (rows.length === 0) return

    sortable = Sortable.create(tbody, {
        draggable: 'tr.el-table__row',
        delay: 300,
        delayOnTouchOnly: false,
        animation: 200,
        ghostClass: 'drag-ghost',
        chosenClass: 'drag-chosen',
        dragClass: 'drag-moving',
        fallbackOnBody: false,
        fallbackTolerance: 3,

        onStart: () => {
            ;(document.body as any).style.userSelect = 'none'
        },

        onEnd: async (evt) => {
            ;(document.body as any).style.userSelect = ''

            try {
                if (evt.oldIndex === evt.newIndex) return

                const draggedElement = evt.item as HTMLTableRowElement
                const targetId = getRowIdFromTr(draggedElement)

                if (targetId == null) {
                    ElMessage.warning(t('menu.dragError'))
                    await getMenuList()
                    return
                }

                const target = idMap.value[targetId]
                if (!target) {
                    ElMessage.warning(t('menu.dragRowNotFound'))
                    await getMenuList()
                    return
                }

                const parentId = target.parent_id
                const level = getLevelFromTr(draggedElement)

                const currentRows = getAllDataRowsInDom()
                const siblingTrs = currentRows.filter((tr) => {
                    const id = getRowIdFromTr(tr)
                    if (id == null) return false
                    const row = idMap.value[id]
                    return row && row.parent_id === parentId && getLevelFromTr(tr) === level
                })

                if (siblingTrs.length === 0) {
                    ElMessage.warning(t('menu.noSiblings'))
                    return
                }

                const payload = siblingTrs.map((tr, idx) => {
                    const id = getRowIdFromTr(tr)!
                    return { id, parent_id: parentId, sort: (idx + 1) * 10 }
                })

                await menuApi.batchUpdateSort(payload)
                ElMessage.success(t('message.sortSuccess'))
                await getMenuList()
            } catch (e) {
                ElMessage.error(t('message.sortFailed'))
                await getMenuList()
            }
        },

        onMove: (evt) => {
            const fromTr = evt.dragged as HTMLTableRowElement
            const toTr = evt.related as HTMLTableRowElement

            if (!fromTr || !toTr) return false

            const fromId = getRowIdFromTr(fromTr)
            const toId = getRowIdFromTr(toTr)

            if (fromId == null || toId == null) return true

            const from = idMap.value[fromId]
            const to = idMap.value[toId]

            if (!from || !to) return true

            const sameParent = from.parent_id === to.parent_id
            const sameLevel = getLevelFromTr(fromTr) === getLevelFromTr(toTr)

            return sameParent && sameLevel
        }
    })
}

// 获取类型文本
const getTypeText = (type: number) => {
    const typeMap: Record<number, string> = {
        1: t('menu.typeOptions.directory'),
        2: t('menu.typeOptions.menu'),
        3: t('menu.typeOptions.button')
    }
    return typeMap[type] || type
}

// 获取类型标签类型
const getTypeTagType = (type: number): 'primary' | 'success' | 'warning' | 'info' | 'danger' => {
    const typeMap: Record<number, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
        1: 'info',
        2: 'success',
        3: 'warning'
    }
    return typeMap[type] || 'info'
}

onMounted(async () => {
    await getMenuList()
})
</script>

<style lang="scss" scoped>
.menu-container {
    .search-card {
        margin-bottom: 16px;

        .search-form {
            margin: 0;
        }
    }

    .table-card {
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

            .table-actions {
                display: flex;
                gap: 8px;
            }
        }
    }

    .menu-title-cell {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        vertical-align: middle;
    }
}

:deep(.drag-ghost) {
    opacity: 0.3;
    background-color: var(--el-color-primary-light-8);
}

:deep(.drag-chosen) {
    background-color: var(--el-color-primary-light-9);
    border: 1px dashed var(--el-color-primary);
}

:deep(.drag-moving) {
    background-color: var(--el-color-primary-light-7);
    transform: rotate(2deg);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}
</style>
