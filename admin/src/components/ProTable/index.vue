<template>
    <el-card class="table-card" shadow="never">
        <!-- Table Header: dual-state -->
        <div class="table-header">
            <template v-if="selectionCount === 0">
                <div class="table-title">
                    {{ title }}
                    <span class="table-count">共 {{ total }} 条</span>
                </div>
                <div class="table-actions">
                    <slot name="headerExtra" />
                    <el-button v-if="onExport !== undefined" @click="handleExport">导出</el-button>
                    <el-button @click="colCfgVisible = true">
                        <i class="i-svg:sliders-horizontal" />
                        列配置
                    </el-button>
                </div>
            </template>
            <template v-else>
                <div class="table-sel-info">
                    已选 <b>{{ selectionCount }}</b> 项
                    <button class="table-sel-clear" @click="clearSelection">取消选择</button>
                </div>
                <div class="table-actions">
                    <slot
                        name="batchActions"
                        :selected-ids="selectedIds"
                        :selected-rows="selectedRows"
                        :clear-selection="clearSelection"
                    />
                    <el-button
                        v-if="batchDeleteFn !== undefined"
                        size="small"
                        type="danger"
                        @click="onBatchDelete"
                    >
                        <i class="i-svg:trash-2" />
                        批量删除
                    </el-button>
                </div>
            </template>
        </div>

        <!-- Table -->
        <el-table
            ref="tableRef"
            v-bind="$attrs"
            v-loading="loading"
            :data="data"
            :row-key="rowKey"
            @selection-change="onSelectionChange"
        >
            <el-table-column
                v-if="selection === true || batchDeleteFn !== undefined"
                v-bind="selectionProps"
                type="selection"
                width="50"
            />
            <el-table-column
                v-for="col in visibleColumns"
                :key="col.key"
                :prop="col.prop || col.key"
                :label="col.label"
                :width="col.width"
                :min-width="col.minWidth"
                :fixed="col.fixed || undefined"
                :show-overflow-tooltip="col.showOverflowTooltip"
                :align="col.align"
                :sortable="col.sortable"
                v-bind="col.extraProps"
            >
                <template #default="scope">
                    <!-- Element Plus probes columns with an empty row before rendering data. -->
                    <slot v-if="scope.$index >= 0" :name="col.key" v-bind="scope">
                        {{ scope.row[col.prop || col.key] }}
                    </slot>
                </template>
            </el-table-column>
        </el-table>

        <!-- Pagination Footer -->
        <div v-if="pagination || $slots.pagination" class="table-footer">
            <div class="table-footer-total">
                共 <b>{{ total }}</b> 条记录
            </div>
            <slot name="pagination">
                <el-pagination
                    v-if="pagination"
                    :current-page="pagination.page"
                    :page-size="pagination.limit"
                    :total="pagination.total"
                    :page-sizes="[10, 20, 50, 100]"
                    layout="prev, pager, next, sizes"
                    small
                    @current-change="$emit('page-change', $event)"
                    @size-change="$emit('size-change', $event)"
                />
            </slot>
        </div>

        <!-- Column Config Dialog -->
        <ColumnConfig
            v-model="colCfgVisible"
            :columns="columnConfigItems"
            @update:columns="onColumnConfigChange"
        />
    </el-card>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

import ColumnConfig from '@/components/ColumnConfig/index.vue'

import type { ProColumn } from './types'
import { useColumnConfig } from './useColumnConfig'

const props = withDefaults(
    defineProps<{
        title: string
        storageKey: string
        columns: ProColumn[]
        data: any[]
        loading?: boolean
        pagination?: { page: number; limit: number; total: number }
        selection?: boolean
        selectionProps?: Record<string, unknown>
        batchDeleteFn?: (ids: number[]) => Promise<any>
        onExport?: () => void
        rowKey?: string
    }>(),
    {
        loading: false,
        rowKey: 'id',
        batchDeleteFn: undefined,
        onExport: undefined,
    }
)

defineOptions({ inheritAttrs: false })
const total = computed(() => props.pagination?.total ?? props.data.length)

const emit = defineEmits<{
    'page-change': [page: number]
    'size-change': [size: number]
    'selection-change': [rows: any[]]
}>()

// Column config
const { colCfgVisible, visibleColumns, columnConfigItems, onColumnConfigChange } = useColumnConfig(
    props.storageKey,
    () => props.columns
)

// Selection state
const tableRef = ref<{ clearSelection(): void }>()
const selectedRows = ref<any[]>([])

const selectionCount = computed(() => selectedRows.value.length)
const selectedIds = computed(() => selectedRows.value.map((r) => r[props.rowKey]))

function onSelectionChange(rows: any[]) {
    selectedRows.value = rows
    emit('selection-change', rows)
}

function clearSelection() {
    tableRef.value?.clearSelection()
}

// Batch delete
function onBatchDelete() {
    if (!props.batchDeleteFn) return
    props.batchDeleteFn(selectedIds.value)
}

// Export
function handleExport() {
    props.onExport?.()
}
</script>
