<template>
    <div class="detail-desc" :class="{ 'is-border': border }">
        <div v-if="title || $slots.title" class="detail-desc__header">
            <slot name="title">
                <span class="detail-desc__title">{{ title }}</span>
            </slot>
            <slot name="extra" />
        </div>
        <div class="detail-desc__body" :style="{ gridTemplateColumns: `repeat(${column}, 1fr)` }">
            <slot />
        </div>
    </div>
</template>

<script lang="ts" setup>
interface Props {
    title?: string
    column?: number
    border?: boolean
}

withDefaults(defineProps<Props>(), {
    column: 3,
    border: true
})
</script>

<style scoped lang="scss">
.detail-desc {
    &__header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    &__title {
        font-size: 16px;
        font-weight: 600;
        color: var(--el-text-color-primary);
    }

    &__body {
        display: grid;
        gap: 0;
    }

    &.is-border .detail-desc__body {
        border-top: 1px solid var(--el-border-color-lighter);
        border-left: 1px solid var(--el-border-color-lighter);
    }
}
</style>
