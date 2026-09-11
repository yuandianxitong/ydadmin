<template>
    <div class="config-lock">
        <div class="style-slider-lock">
            <el-slider
                v-model="innerMain"
                :min="min"
                :max="max"
                @change="
                    (v: number | number[]) => {
                        $emit('begin')
                        onMainChange(v as number)
                    }
                "
            />
            <el-input-number
                :model-value="modelValue[keys[0]]"
                :min="min"
                :max="max"
                :controls="false"
                @focus="$emit('begin')"
                @change="(v: number | undefined) => onMainChange(v ?? min)"
            />
            <span
                class="style-lock-btn"
                :class="{ 'style-lock-btn--active': linked }"
                @click="toggleLinked"
            >
                <el-icon>
                    <Lock v-if="linked" />
                    <Unlock v-else />
                </el-icon>
            </span>
        </div>
        <div v-if="!linked" class="style-grid-4">
            <div v-for="(k, i) in keys" :key="k" class="style-grid-4__item">
                <el-icon class="style-grid-4__icon"><component :is="icons[i]" /></el-icon>
                <el-input-number
                    :model-value="values[k]"
                    :min="min"
                    :max="max"
                    :controls="false"
                    @focus="$emit('begin')"
                    @change="(v: number | undefined) => (values[k] = v ?? min)"
                />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import {
    Back,
    Bottom,
    BottomLeft,
    BottomRight,
    Lock,
    Right,
    Top,
    TopLeft,
    TopRight,
    Unlock
} from '@element-plus/icons-vue'
import { computed, ref, watch } from 'vue'

// 滑块 + 锁定联动 + 四向展开网格（对齐 Shop style-slider-lock / style-grid-4）。
// 锁定态为纯 UI 状态，不写入页面数据（避免污染跨端渲染协议 JSON）。
const props = withDefaults(
    defineProps<{
        modelValue: Record<string, number>
        variant: 'sides' | 'corners'
        min?: number
        max?: number
    }>(),
    { min: 0, max: 200 }
)
const emit = defineEmits<{ (e: 'begin'): void }>()

// prop 对象本身就是编辑器的响应式状态（与 PropertyPanel/StyleConfig 同一约定：面板就地修改），
// 经 computed 取引用避免 vue/no-mutating-props 误报
const values = computed(() => props.modelValue)

const SIDE_KEYS = ['top', 'right', 'bottom', 'left'] as const
const CORNER_KEYS = ['topLeft', 'topRight', 'bottomLeft', 'bottomRight'] as const
const keys = computed<readonly string[]>(() =>
    props.variant === 'sides' ? SIDE_KEYS : CORNER_KEYS
)
const icons = computed(() =>
    props.variant === 'sides'
        ? [Top, Right, Bottom, Back]
        : [TopLeft, TopRight, BottomLeft, BottomRight]
)

const linked = ref(true)
// 仅在切换选中组件（modelValue 对象引用变化）时重算锁定态；编辑取值不重置
watch(
    () => props.modelValue,
    (val) => {
        const first = val[keys.value[0]]
        linked.value = keys.value.every((k) => val[k] === first)
    },
    { immediate: true }
)

// 主滑块的本地值：EP 滑块的 change 抛的是自身 props.modelValue，受控不回写会在松手时带回旧值，
// 故用 v-model 绑本地 ref（拖动只动本地状态），change 时统一提交到数据模型。
const innerMain = ref(props.modelValue[keys.value[0]])
watch(
    () => props.modelValue[keys.value[0]],
    (v) => (innerMain.value = v)
)

function setAll(v: number) {
    for (const k of keys.value) values.value[k] = v
}

// 对齐 Shop：sides（边距）主滑块始终四向同步；corners（圆角）解锁后主滑块只改第一角，
// 不覆盖网格里已独立设置的其余三角
function onMainChange(v: number) {
    if (props.variant === 'corners' && !linked.value) values.value[keys.value[0]] = v
    else setAll(v)
}

function toggleLinked() {
    linked.value = !linked.value
    if (linked.value) {
        emit('begin')
        setAll(values.value[keys.value[0]])
    }
}
</script>

<style scoped lang="scss">
.config-lock {
    display: flex;
    flex-direction: column;
    gap: 14px; // 对齐 Shop：主滑块行与四向网格之间为一个 config-row 的 margin-bottom
    width: 100%;
}

.style-slider-lock {
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

.style-lock-btn {
    width: 28px;
    height: 28px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    cursor: pointer;
    color: var(--color-text-tertiary);
    font-size: 16px;
    transition: all 0.2s;

    &:hover {
        color: var(--el-color-primary);
        background: var(--el-color-primary-light-9);
    }

    &--active {
        color: var(--el-color-primary);
    }
}

.style-grid-4 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;

    &__item {
        display: flex;
        align-items: center;
        gap: 4px;

        :deep(.el-input-number) {
            width: 100%;
            --el-component-size: 32px;
        }

        :deep(.el-input-number .el-input__inner) {
            text-align: left;
        }
    }

    &__icon {
        font-size: 14px;
        color: var(--color-text-tertiary);
        flex-shrink: 0;
    }
}
</style>
