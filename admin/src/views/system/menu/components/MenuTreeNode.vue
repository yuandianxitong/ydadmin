<template>
    <div>
        <div
            class="tree-node"
            :class="{ on: selectedId === node.id }"
            :style="{ paddingLeft: 8 + depth * 16 + 'px' }"
            @click="$emit('select', node)"
        >
            <span
                v-if="hasChildren"
                class="tw"
                :style="{
                    transform: `rotate(${isOpen ? 90 : 0}deg)`
                }"
                @click.stop="$emit('toggle', node.id)"
            >
                <i class="i-svg:chevron-right" />
            </span>
            <span v-else class="tw" />
            <i class="ic" :class="iconClass" />
            <span class="nm">{{ node.title }}</span>
            <el-tag
                v-if="node.type"
                :type="typeTagType"
                size="small"
                style="font-size: 11px; padding: 0 5px; height: 18px; line-height: 18px"
            >
                {{ typeText }}
            </el-tag>
            <span v-if="hasChildren" class="cnt">{{ node.children!.length }}</span>
        </div>
        <div v-if="hasChildren && isOpen" class="tree-child">
            <MenuTreeNode
                v-for="child in node.children"
                :key="child.id"
                :node="child"
                :depth="depth + 1"
                :selected-id="selectedId"
                :open-map="openMap"
                @select="$emit('select', $event)"
                @toggle="$emit('toggle', $event)"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import type { MenuInfo } from '@/types/system'

interface Props {
    node: MenuInfo
    depth: number
    selectedId: number | null
    openMap: Record<number, boolean>
}

const props = defineProps<Props>()

defineEmits<{
    (e: 'select', node: MenuInfo): void
    (e: 'toggle', nodeId: number): void
}>()

const hasChildren = computed(() => props.node.children && props.node.children.length > 0)
const isOpen = computed(() => !!props.openMap[props.node.id])

const iconClass = computed(() => {
    switch (props.node.type) {
        case 1:
            return isOpen.value ? 'i-svg:folder-open' : 'i-svg:folder-closed'
        case 2:
            return 'i-svg:file-text'
        case 3:
            return 'i-svg:square-x'
        default:
            return 'i-svg:file-text'
    }
})

const typeText = computed(() => {
    const map: Record<number, string> = { 1: '目录', 2: '菜单', 3: '按钮' }
    return map[props.node.type] || ''
})

const typeTagType = computed(() => {
    const map: Record<number, 'info' | 'success' | 'warning'> = {
        1: 'info',
        2: 'success',
        3: 'warning'
    }
    return map[props.node.type] || 'info'
})
</script>
