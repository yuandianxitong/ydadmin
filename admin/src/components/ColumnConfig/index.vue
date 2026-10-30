<template>
    <el-dialog
        :model-value="modelValue"
        title=""
        width="560px"
        :show-close="false"
        :close-on-click-modal="true"
        append-to-body
        class="col-cfg-dialog"
        @update:model-value="emit('update:modelValue', $event)"
        @close="handleClose"
    >
        <template #header>
            <div class="modal-head">
                <div class="modal-title">
                    列配置
                    <span class="sub">勾选显示列、拖动调整顺序、设置固定</span>
                </div>
                <button class="modal-close" @click="handleClose">
                    <i class="i-svg:x" />
                </button>
            </div>
        </template>

        <div class="col-cfg-head">
            <label class="col-cfg-all">
                <input type="checkbox" :checked="allChecked" @change="toggleAll" />
                <span>全选</span>
            </label>
            <div class="col-cfg-stat">
                已显示 <b>{{ checkedCount }}</b> / {{ localColumns.length }} 列
            </div>
        </div>

        <div class="col-cfg-list">
            <div
                v-for="(col, index) in localColumns"
                :key="col.key"
                class="col-cfg-row"
                :class="{
                    dragging: dragIndex === index,
                    over: overIndex === index && dragIndex !== null && dragIndex !== index
                }"
                :draggable="!col.required"
                @dragstart="onDragStart(index)"
                @dragover.prevent="onDragOver(index)"
                @drop="onDrop(index)"
                @dragend="onDragEnd"
            >
                <span class="drag-h" :title="col.required ? '必选列' : '拖动排序'">
                    <i v-if="col.required" class="i-svg:lock" />
                    <i v-else class="i-svg:grip-vertical" />
                </span>
                <label class="col-cfg-name">
                    <input
                        type="checkbox"
                        :checked="col.visible"
                        :disabled="col.required"
                        @change="toggleColumn(index)"
                    />
                    <span>{{ col.label }}</span>
                    <em v-if="col.required" class="tag-req">必选</em>
                </label>
                <span class="col-cfg-width">
                    {{ col.width ? `${col.width}px` : '自适应' }}
                </span>
                <div class="col-cfg-fix">
                    <button
                        class="fix-btn"
                        :class="{ on: col.fixed === 'left' }"
                        title="固定左侧"
                        @click="setFixed(index, 'left')"
                    >
                        <i class="i-svg:arrow-left" />
                    </button>
                    <button
                        class="fix-btn"
                        :class="{ on: col.fixed === 'right' }"
                        title="固定右侧"
                        @click="setFixed(index, 'right')"
                    >
                        <i class="i-svg:arrow-right" />
                    </button>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="modal-foot">
                <el-button @click="handleReset">恢复默认</el-button>
                <div style="flex: 1" />
                <el-button @click="handleClose">取消</el-button>
                <el-button type="primary" @click="handleConfirm">应用</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import type { ColumnConfigItem } from '@/components/ProTable/types'

export type { ColumnConfigItem }

const props = defineProps<{
    modelValue: boolean
    columns: ColumnConfigItem[]
}>()

const emit = defineEmits<{
    'update:modelValue': [value: boolean]
    'update:columns': [columns: ColumnConfigItem[]]
}>()

const localColumns = ref<ColumnConfigItem[]>([])
const dragIndex = ref<number | null>(null)
const overIndex = ref<number | null>(null)

// Sync from props when dialog opens
watch(
    () => props.modelValue,
    (val) => {
        if (val) {
            localColumns.value = props.columns.map((c) => ({ ...c }))
        }
    },
    { immediate: true }
)

const checkedCount = computed(() => localColumns.value.filter((c) => c.visible).length)
const allChecked = computed(() => checkedCount.value === localColumns.value.length)

function toggleAll() {
    const newVal = !allChecked.value
    localColumns.value = localColumns.value.map((c) => ({
        ...c,
        visible: newVal || !!c.required,
    }))
}

function toggleColumn(index: number) {
    localColumns.value[index].visible = !localColumns.value[index].visible
}

function setFixed(index: number, dir: 'left' | 'right') {
    const col = localColumns.value[index]
    col.fixed = col.fixed === dir ? false : dir
}

// Drag and drop
function onDragStart(index: number) {
    dragIndex.value = index
}
function onDragOver(index: number) {
    overIndex.value = index
}
function onDrop(index: number) {
    if (dragIndex.value === null || dragIndex.value === index) {
        dragIndex.value = null
        overIndex.value = null
        return
    }
    const next = [...localColumns.value]
    const [moved] = next.splice(dragIndex.value, 1)
    next.splice(index, 0, moved)
    localColumns.value = next
    dragIndex.value = null
    overIndex.value = null
}
function onDragEnd() {
    dragIndex.value = null
    overIndex.value = null
}

function handleReset() {
    localColumns.value = props.columns.map((c) => ({ ...c }))
}

function handleClose() {
    emit('update:modelValue', false)
}

function handleConfirm() {
    emit('update:columns', localColumns.value)
    emit('update:modelValue', false)
}
</script>

<style lang="scss" scoped>
/* Modal head */
.modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.modal-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 15px;
    font-weight: 600;
    color: var(--ink-900);

    &::before {
        content: '';
        width: 3px;
        height: 14px;
        background: var(--brand-500);
        border-radius: 2px;
    }

    .sub {
        font-size: 12px;
        font-weight: 400;
        color: var(--ink-400);
        margin-left: 4px;
    }
}

.modal-close {
    width: 26px;
    height: 26px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ink-400);
    background: transparent;
    border: none;
    cursor: pointer;

    &:hover {
        background: var(--ink-100);
        color: var(--ink-700);
    }
}

/* Modal foot */
.modal-foot {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
}

/* Column config styles */
.col-cfg-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 4px 12px;
    border-bottom: 1px dashed var(--ink-200);
    margin-bottom: 10px;
}

.col-cfg-all {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--ink-700);
    cursor: pointer;
}

.col-cfg-stat {
    font-size: 12px;
    color: var(--ink-500);

    b {
        color: var(--brand-600);
        font-weight: 700;
    }
}

.col-cfg-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
    max-height: 380px;
    overflow-y: auto;
    padding: 2px;
}

.col-cfg-row {
    display: grid;
    grid-template-columns: 22px 1fr 70px 60px;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border: 1px solid var(--ink-100);
    border-radius: 4px;
    background: #fff;
    transition: all 0.12s;

    &:hover {
        border-color: var(--brand-200, var(--ink-200));
        background: var(--ink-50);
    }

    &.dragging {
        opacity: 0.4;
    }

    &.over {
        border-color: var(--brand-500);
        background: var(--brand-50);
        box-shadow: inset 0 -2px 0 var(--brand-500);
    }

    .drag-h {
        color: var(--ink-300);
        cursor: grab;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    &[draggable='false'] .drag-h {
        color: var(--ink-400);
        cursor: not-allowed;
    }

    &:hover .drag-h {
        color: var(--ink-500);
    }
}

.col-cfg-name {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--ink-800);
    cursor: pointer;
    user-select: none;

    input[type='checkbox'] {
        margin: 0;
    }

    .tag-req {
        font-style: normal;
        font-size: 10.5px;
        padding: 1px 6px;
        border-radius: 2px;
        background: var(--ink-100);
        color: var(--ink-500);
        margin-left: 2px;
    }
}

.col-cfg-width {
    font-size: 11.5px;
    color: var(--ink-400);
    text-align: right;
}

.col-cfg-fix {
    display: inline-flex;
    gap: 4px;
    justify-content: flex-end;
}

.fix-btn {
    width: 22px;
    height: 22px;
    border-radius: 3px;
    border: 1px solid var(--ink-200);
    background: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--ink-500);
    transition: all 0.12s;
    cursor: pointer;

    &:hover {
        color: var(--brand-500);
        border-color: var(--brand-400);
    }

    &.on {
        background: var(--brand-50);
        color: var(--brand-600);
        border-color: var(--brand-400);
    }
}
</style>

<style lang="scss">
/* Unscoped overrides for el-dialog */
.col-cfg-dialog {
    .el-dialog__header {
        padding: 14px 20px;
        margin: 0;
        border-bottom: 1px solid var(--ink-100);
    }

    .el-dialog__body {
        padding: 20px 24px 4px;
    }

    .el-dialog__footer {
        padding: 14px 20px;
        border-top: 1px solid var(--ink-100);
        background: var(--ink-50);
    }
}
</style>
