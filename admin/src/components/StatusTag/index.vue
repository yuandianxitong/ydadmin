<template>
    <el-tag :type="tagType" :effect="effect" :size="size" :round="round">
        <slot>{{ label }}</slot>
    </el-tag>
</template>

<script lang="ts" setup>
type TagType = 'primary' | 'success' | 'warning' | 'danger' | 'info'

interface StatusConfig {
    label: string
    type: TagType
}

interface Props {
    status: number | string
    map?: Record<string | number, StatusConfig>
    effect?: 'dark' | 'light' | 'plain'
    size?: '' | 'large' | 'default' | 'small'
    round?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    effect: 'light',
    size: 'default',
    round: false
})

// Default status mapping
const defaultMap: Record<string | number, StatusConfig> = {
    0: { label: '禁用', type: 'danger' },
    1: { label: '启用', type: 'success' },
    2: { label: '待审核', type: 'warning' },
    3: { label: '已驳回', type: 'info' }
}

const config = computed<StatusConfig>(() => {
    const map = props.map || defaultMap
    return map[props.status] || { label: String(props.status), type: 'info' as TagType }
})

const tagType = computed(() => config.value.type)
const label = computed(() => config.value.label)
</script>
