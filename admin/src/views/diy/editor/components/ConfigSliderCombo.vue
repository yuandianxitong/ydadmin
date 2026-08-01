<template>
    <div class="config-slider-combo">
        <el-slider
            v-model="inner"
            :min="min"
            :max="max"
            :step="step"
            @change="
                (v: number | number[]) => {
                    $emit('begin')
                    $emit('update:modelValue', v as number)
                }
            "
        />
        <el-input-number
            :model-value="modelValue"
            :min="min"
            :max="max"
            :step="step"
            :controls="false"
            @focus="$emit('begin')"
            @change="(v: number | undefined) => $emit('update:modelValue', v ?? min)"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'

// 滑块 + 数字框组合（对齐 Shop config-slider-combo）。
// 撤销时序：滑块在 change 回调内先 begin 再抛值；数字框在 focus 时 begin（v-model 类控件约定）。
// 滑块必须 v-model 绑本地 ref 而非只读 :model-value：EP 滑块的 change 抛的是自身 props.modelValue，
// 受控不回写会在松手时带回旧值（表现为"滑动后弹回"）。拖动只更新本地值，不触数据模型。
const props = withDefaults(
    defineProps<{ modelValue: number; min?: number; max?: number; step?: number }>(),
    {
        min: 0,
        max: 100,
        step: 1
    }
)
defineEmits<{ (e: 'begin'): void; (e: 'update:modelValue', v: number): void }>()

const inner = ref(props.modelValue)
watch(
    () => props.modelValue,
    (v) => (inner.value = v)
)
</script>

<style scoped lang="scss">
.config-slider-combo {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;

    :deep(.el-slider) {
        flex: 1;
    }

    :deep(.el-input-number) {
        width: 64px;
        flex-shrink: 0;
        --el-component-size: 32px;
    }
}
</style>
