<template>
    <div class="detail-desc-item" :style="{ gridColumn: `span ${span}` }">
        <div class="detail-desc-item__label" :style="{ width: labelWidth }">
            <slot name="label">{{ label }}</slot>
        </div>
        <div class="detail-desc-item__content">
            <slot>{{ content }}</slot>
        </div>
    </div>
</template>

<script lang="ts" setup>
interface Props {
    label?: string
    content?: string | number
    span?: number
    labelWidth?: string
}

withDefaults(defineProps<Props>(), {
    span: 1,
    labelWidth: '120px',
})
</script>

<style scoped lang="scss">
.detail-desc-item {
    display: flex;
    line-height: 1.5;

    &__label {
        flex-shrink: 0;
        padding: 12px 16px;
        color: var(--el-text-color-secondary);
        background: var(--el-fill-color-light);
        font-size: 14px;
        border-right: 1px solid var(--el-border-color-lighter);
        border-bottom: 1px solid var(--el-border-color-lighter);
    }

    &__content {
        flex: 1;
        padding: 12px 16px;
        font-size: 14px;
        color: var(--el-text-color-primary);
        border-right: 1px solid var(--el-border-color-lighter);
        border-bottom: 1px solid var(--el-border-color-lighter);
        word-break: break-all;
    }
}
</style>
