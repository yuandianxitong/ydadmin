<template>
    <el-form :model="modelValue" inline class="search-form" @submit.prevent="$emit('search')">
        <slot />
        <el-form-item class="search-form__actions">
            <el-button type="primary" @click="$emit('search')">
                <el-icon><Search /></el-icon>搜索
            </el-button>
            <el-button @click="$emit('reset')">
                <el-icon><Refresh /></el-icon>重置
            </el-button>
            <el-button
                v-if="collapsible && slotCount > showCount"
                link
                type="primary"
                @click="collapsed = !collapsed"
            >
                {{ collapsed ? '展开' : '收起' }}
                <el-icon><ArrowDown v-if="collapsed" /><ArrowUp v-else /></el-icon>
            </el-button>
        </el-form-item>
    </el-form>
</template>

<script lang="ts">
import type { InjectionKey, Ref } from 'vue'

export interface SearchFormContext {
    collapsed: Ref<boolean>
    showCount: Ref<number>
}

export interface SearchFormProps {
    modelValue: Record<string, any>
    collapsible?: boolean
    showCount?: number
}

export const searchFormContextKey: InjectionKey<SearchFormContext> = Symbol('searchFormContext')
</script>

<script lang="ts" setup>
import { ArrowDown, ArrowUp, Refresh, Search } from '@element-plus/icons-vue'
import { Fragment } from 'vue'

type Props = SearchFormProps

const props = withDefaults(defineProps<Props>(), {
    collapsible: true,
    showCount: 3
})

defineEmits<{
    (event: 'search'): void
    (event: 'reset'): void
}>()

const collapsed = ref(true)
const slots = useSlots()

const slotCount = computed(() => {
    const defaultSlot = slots.default?.()
    if (!defaultSlot) return 0
    let count = 0
    for (const vnode of defaultSlot) {
        if (vnode.type === Fragment) {
            count += (vnode.children as any[])?.length ?? 0
        } else {
            count++
        }
    }
    return count
})

provide(searchFormContextKey, {
    collapsed,
    showCount: computed(() => props.showCount)
})
</script>

<style scoped lang="scss">
.search-form {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    margin-bottom: 16px;

    &__actions {
        margin-left: auto;
    }
}
</style>
