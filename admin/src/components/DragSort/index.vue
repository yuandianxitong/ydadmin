<template>
    <div class="drag-sort">
        <div
            v-for="(item, index) in modelValue"
            :key="getKey(item, index)"
            class="drag-sort-item"
            draggable="true"
            :class="{ 'is-dragging': dragIndex === index, 'is-over': overIndex === index }"
            @dragstart="onDragStart(index)"
            @dragover.prevent="onDragOver(index)"
            @dragleave="onDragLeave"
            @drop="onDrop(index)"
            @dragend="onDragEnd"
        >
            <slot :item="item" :index="index">
                <span>{{ item }}</span>
            </slot>
            <el-icon v-if="showHandle" class="drag-handle"><Rank /></el-icon>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { Rank } from '@element-plus/icons-vue'

interface Props {
    modelValue: any[]
    rowKey?: string
    showHandle?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    rowKey: 'id',
    showHandle: true,
})

const emit = defineEmits<{
    'update:modelValue': [value: any[]]
    change: [value: any[]]
}>()

const dragIndex = ref<number | null>(null)
const overIndex = ref<number | null>(null)

function getKey(item: any, index: number): string | number {
    if (typeof item === 'object' && item !== null && props.rowKey in item) {
        return item[props.rowKey]
    }
    return index
}

function onDragStart(index: number) {
    dragIndex.value = index
}

function onDragOver(index: number) {
    overIndex.value = index
}

function onDragLeave() {
    overIndex.value = null
}

function onDrop(index: number) {
    if (dragIndex.value === null || dragIndex.value === index) return
    const list = [...props.modelValue]
    const [removed] = list.splice(dragIndex.value, 1)
    list.splice(index, 0, removed)
    emit('update:modelValue', list)
    emit('change', list)
}

function onDragEnd() {
    dragIndex.value = null
    overIndex.value = null
}
</script>

<style scoped lang="scss">
.drag-sort-item {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    margin-bottom: 4px;
    background: var(--el-bg-color);
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 4px;
    cursor: move;
    transition: all 0.2s;

    &.is-dragging {
        opacity: 0.5;
    }

    &.is-over {
        border-color: var(--el-color-primary);
        background: var(--el-color-primary-light-9);
    }
}

.drag-handle {
    margin-left: auto;
    color: var(--el-text-color-placeholder);
    cursor: grab;
}
</style>
