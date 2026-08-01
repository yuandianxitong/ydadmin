<template>
    <div class="config-color">
        <el-color-picker
            :model-value="modelValue"
            :show-alpha="showAlpha"
            @change="
                (v: string | null) => {
                    $emit('begin')
                    $emit('update:modelValue', v ?? '')
                }
            "
        />
        <el-input
            :model-value="modelValue"
            @focus="$emit('begin')"
            @change="(v: string) => $emit('update:modelValue', v)"
        />
        <span v-if="resetValue !== undefined" class="config-color__reset" @click="onReset"
            >重置</span
        >
    </div>
</template>

<script setup lang="ts">
// 色板 + hex 输入 + 重置链接（对齐 Shop config-color）。
// resetValue 未传时不渲染重置链接（如插件 schema 的 color 字段无默认值可回退）。
const props = defineProps<{ modelValue: string; showAlpha?: boolean; resetValue?: string }>()
const emit = defineEmits<{ (e: 'begin'): void; (e: 'update:modelValue', v: string): void }>()

function onReset() {
    emit('begin')
    emit('update:modelValue', props.resetValue!)
}
</script>

<style scoped lang="scss">
.config-color {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;

    :deep(.el-input) {
        flex: 1;
        min-width: 0;
        --el-component-size: 32px;
    }

    &__reset {
        font-size: var(--font-size-caption);
        color: var(--el-color-primary);
        cursor: pointer;
        white-space: nowrap;

        &:hover {
            text-decoration: underline;
        }
    }
}
</style>
