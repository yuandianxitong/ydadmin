<template>
    <el-popover
        placement="bottom-end"
        :width="200"
        trigger="click"
        :persistent="false"
        popper-class="table-column-setting-popover"
    >
        <template #reference>
            <el-button :icon="Setting" circle />
        </template>
        <div class="table-column-setting">
            <div class="table-column-setting__header">
                <el-checkbox
                    v-model="checkAll"
                    :indeterminate="indeterminate"
                    @change="handleCheckAllChange"
                >
                    {{ $t('common.checkAll') }}
                </el-checkbox>
                <el-button type="primary" link @click="handleReset">
                    {{ $t('common.reset') }}
                </el-button>
            </div>
            <el-divider style="margin: 8px 0" />
            <div class="table-column-setting__body">
                <el-checkbox
                    v-for="col in columnState"
                    :key="col.prop"
                    v-model="col.visible"
                    :label="col.label"
                    @change="handleColumnChange"
                />
            </div>
        </div>
    </el-popover>
</template>

<script lang="ts" setup>
import { Setting } from '@element-plus/icons-vue'
import type { PropType } from 'vue'

interface ColumnConfig {
    prop: string
    label: string
    visible?: boolean
}

const props = defineProps({
    columns: {
        type: Array as PropType<ColumnConfig[]>,
        required: true
    }
})

const emit = defineEmits<{
    change: [visibleProps: string[]]
}>()

interface ColumnState {
    prop: string
    label: string
    visible: boolean
    defaultVisible: boolean
}

const columnState = ref<ColumnState[]>([])
const checkAll = ref(true)
const indeterminate = ref(false)

const initColumnState = () => {
    columnState.value = props.columns.map((col) => ({
        prop: col.prop,
        label: col.label,
        visible: col.visible !== false,
        defaultVisible: col.visible !== false
    }))
    updateCheckAllState()
}

const updateCheckAllState = () => {
    const visibleCount = columnState.value.filter((col) => col.visible).length
    const total = columnState.value.length
    checkAll.value = visibleCount === total
    indeterminate.value = visibleCount > 0 && visibleCount < total
}

const getVisibleProps = (): string[] => {
    return columnState.value.filter((col) => col.visible).map((col) => col.prop)
}

const handleCheckAllChange = (val: boolean) => {
    columnState.value.forEach((col) => {
        col.visible = val
    })
    indeterminate.value = false
    emit('change', getVisibleProps())
}

const handleColumnChange = () => {
    updateCheckAllState()
    emit('change', getVisibleProps())
}

const handleReset = () => {
    columnState.value.forEach((col) => {
        col.visible = col.defaultVisible
    })
    updateCheckAllState()
    emit('change', getVisibleProps())
}

watch(
    () => props.columns,
    () => {
        initColumnState()
    },
    { immediate: true, deep: true }
)
</script>

<style scoped lang="scss">
.table-column-setting {
    &__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    &__body {
        display: flex;
        flex-direction: column;
        gap: 4px;
        max-height: 300px;
        overflow-y: auto;
    }
}
</style>
