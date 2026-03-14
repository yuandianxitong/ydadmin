<template>
    <el-cascader
        v-model="selectedValue"
        :options="regionOptions"
        :props="cascaderProps"
        :placeholder="placeholder"
        :disabled="disabled"
        clearable
        filterable
        @change="handleChange"
    />
</template>

<script lang="ts" setup>
import { computed, onMounted, ref, watch } from 'vue'

import { myRequest } from '@/utils/request'

interface RegionItem {
    value: string | number
    label: string
    children?: RegionItem[]
}

const props = withDefaults(
    defineProps<{
        modelValue?: (string | number)[]
        placeholder?: string
        level?: 2 | 3
        disabled?: boolean
    }>(),
    {
        modelValue: () => [],
        placeholder: '请选择地区',
        level: 3,
        disabled: false
    }
)

const emit = defineEmits<{
    (e: 'update:modelValue', value: (string | number)[]): void
    (e: 'change', value: (string | number)[]): void
}>()

const regionOptions = ref<RegionItem[]>([])
const loading = ref(false)

const cascaderProps = computed(() => ({
    value: 'value',
    label: 'label',
    children: 'children',
    checkStrictly: false
}))

const selectedValue = computed({
    get: () => props.modelValue || [],
    set: (value: (string | number)[]) => {
        emit('update:modelValue', value || [])
    }
})

/**
 * Trim region tree to the specified level depth
 */
function trimToLevel(items: RegionItem[], maxLevel: number, currentLevel = 1): RegionItem[] {
    return items.map((item) => {
        const node: RegionItem = { value: item.value, label: item.label }
        if (currentLevel < maxLevel && item.children?.length) {
            node.children = trimToLevel(item.children, maxLevel, currentLevel + 1)
        }
        return node
    })
}

async function loadRegions() {
    loading.value = true
    try {
        const res = await myRequest.get<RegionItem[]>('/adminapi/common/regions')
        const data = res.data || []
        regionOptions.value = props.level < 3 ? trimToLevel(data, props.level) : data
    } catch {
        // API unavailable — fall back to empty options silently
        regionOptions.value = []
    } finally {
        loading.value = false
    }
}

function handleChange(value: (string | number)[]) {
    emit('change', value || [])
}

// Reload when level prop changes
watch(
    () => props.level,
    () => {
        loadRegions()
    }
)

onMounted(() => {
    loadRegions()
})
</script>
