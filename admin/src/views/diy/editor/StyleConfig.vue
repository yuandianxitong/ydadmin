<template>
    <div v-if="model" class="config-panel">
        <!-- 背景 -->
        <div class="config-section">背景设置</div>
        <div class="config-row">
            <span class="config-label">背景类型</span>
            <div class="config-control">
                <el-radio-group
                    :model-value="model.background.type"
                    @change="
                        (v: any) => {
                            $emit('begin')
                            model!.background.type = v
                        }
                    "
                >
                    <el-radio value="color">颜色</el-radio>
                    <el-radio value="gradient">渐变</el-radio>
                    <el-radio value="image">图片</el-radio>
                </el-radio-group>
            </div>
        </div>
        <template v-if="model.background.type === 'color'">
            <div class="config-row">
                <span class="config-label">背景颜色</span>
                <div class="config-control">
                    <ConfigColorField
                        :model-value="model.background.color"
                        show-alpha
                        reset-value=""
                        @begin="$emit('begin')"
                        @update:model-value="(v) => (model!.background.color = v)"
                    />
                </div>
            </div>
        </template>
        <template v-else-if="model.background.type === 'gradient'">
            <div class="config-row">
                <span class="config-label">起始色</span>
                <div class="config-control">
                    <ConfigColorField
                        :model-value="model.background.gradientStart"
                        reset-value="#ffffff"
                        @begin="$emit('begin')"
                        @update:model-value="(v) => (model!.background.gradientStart = v)"
                    />
                </div>
            </div>
            <div class="config-row">
                <span class="config-label">结束色</span>
                <div class="config-control">
                    <ConfigColorField
                        :model-value="model.background.gradientEnd"
                        reset-value="#000000"
                        @begin="$emit('begin')"
                        @update:model-value="(v) => (model!.background.gradientEnd = v)"
                    />
                </div>
            </div>
            <div class="config-row">
                <span class="config-label">渐变方向</span>
                <div class="config-control">
                    <el-radio-group
                        :model-value="model.background.gradientDirection"
                        @change="
                            (v: any) => {
                                $emit('begin')
                                model!.background.gradientDirection = v
                            }
                        "
                    >
                        <el-radio value="to right">横向</el-radio>
                        <el-radio value="to bottom">纵向</el-radio>
                        <el-radio value="to bottom right">左斜</el-radio>
                        <el-radio value="to top right">右斜</el-radio>
                    </el-radio-group>
                </div>
            </div>
        </template>
        <template v-else>
            <div class="config-row config-row--top">
                <span class="config-label">背景图</span>
                <div class="config-control">
                    <ImageSelect
                        :model-value="model.background.image"
                        @update:model-value="
                            (v: string | string[]) => {
                                $emit('begin')
                                model!.background.image = v as string
                            }
                        "
                    />
                </div>
            </div>
        </template>

        <!-- 圆角 -->
        <div class="config-section">圆角设置</div>
        <div class="config-row">
            <span class="config-label">背景圆角</span>
            <div class="config-control">
                <ConfigSliderLock
                    :model-value="model.borderRadius"
                    variant="corners"
                    :min="0"
                    :max="100"
                    @begin="$emit('begin')"
                />
            </div>
        </div>

        <!-- 外边距 -->
        <div class="config-section">外边距</div>
        <div class="config-row">
            <span class="config-label">外边距</span>
            <div class="config-control">
                <ConfigSliderLock
                    :model-value="model.margin"
                    variant="sides"
                    :min="0"
                    :max="200"
                    @begin="$emit('begin')"
                />
            </div>
        </div>

        <!-- 内边距 -->
        <div class="config-section">内边距</div>
        <div class="config-row">
            <span class="config-label">内边距</span>
            <div class="config-control">
                <ConfigSliderLock
                    :model-value="model.padding"
                    variant="sides"
                    :min="0"
                    :max="200"
                    @begin="$emit('begin')"
                />
            </div>
        </div>

        <!-- 边框 -->
        <div class="config-section">边框设置</div>
        <div class="config-row">
            <span class="config-label">边框</span>
            <div class="config-control">
                <el-radio-group :model-value="borderVisible" @change="onBorderVisibleChange">
                    <el-radio :value="false">隐藏</el-radio>
                    <el-radio :value="true">显示</el-radio>
                </el-radio-group>
            </div>
        </div>
        <template v-if="borderVisible">
            <div class="config-row">
                <span class="config-label">边框宽度</span>
                <div class="config-control">
                    <ConfigSliderCombo
                        :model-value="model.border.width"
                        :min="1"
                        :max="20"
                        @begin="$emit('begin')"
                        @update:model-value="(v) => (model!.border.width = v)"
                    />
                </div>
            </div>
            <div class="config-row">
                <span class="config-label">边框样式</span>
                <div class="config-control">
                    <el-radio-group
                        :model-value="model.border.style"
                        @change="
                            (v: any) => {
                                $emit('begin')
                                model!.border.style = v
                            }
                        "
                    >
                        <el-radio value="solid">实线</el-radio>
                        <el-radio value="dashed">虚线</el-radio>
                        <el-radio value="dotted">点线</el-radio>
                    </el-radio-group>
                </div>
            </div>
            <div class="config-row">
                <span class="config-label">边框颜色</span>
                <div class="config-control">
                    <ConfigColorField
                        :model-value="model.border.color"
                        reset-value="#e0e0e0"
                        @begin="$emit('begin')"
                        @update:model-value="(v) => (model!.border.color = v)"
                    />
                </div>
            </div>
        </template>

        <!-- 阴影 -->
        <div class="config-section">阴影设置</div>
        <div class="config-row">
            <span class="config-label">阴影</span>
            <div class="config-control">
                <el-radio-group :model-value="shadowVisible" @change="onShadowVisibleChange">
                    <el-radio :value="false">隐藏</el-radio>
                    <el-radio :value="true">显示</el-radio>
                </el-radio-group>
            </div>
        </div>
        <template v-if="shadowVisible">
            <div class="config-row">
                <span class="config-label">阴影颜色</span>
                <div class="config-control">
                    <ConfigColorField
                        :model-value="model.boxShadow.color"
                        show-alpha
                        reset-value="rgba(0,0,0,0.1)"
                        @begin="$emit('begin')"
                        @update:model-value="(v) => (model!.boxShadow.color = v)"
                    />
                </div>
            </div>
            <div class="config-row">
                <span class="config-label">X轴偏移</span>
                <div class="config-control">
                    <ConfigSliderCombo
                        :model-value="model.boxShadow.x"
                        :min="-50"
                        :max="50"
                        @begin="$emit('begin')"
                        @update:model-value="(v) => (model!.boxShadow.x = v)"
                    />
                </div>
            </div>
            <div class="config-row">
                <span class="config-label">Y轴偏移</span>
                <div class="config-control">
                    <ConfigSliderCombo
                        :model-value="model.boxShadow.y"
                        :min="-50"
                        :max="50"
                        @begin="$emit('begin')"
                        @update:model-value="(v) => (model!.boxShadow.y = v)"
                    />
                </div>
            </div>
            <div class="config-row">
                <span class="config-label">模糊半径</span>
                <div class="config-control">
                    <ConfigSliderCombo
                        :model-value="model.boxShadow.blur"
                        :min="0"
                        :max="100"
                        @begin="$emit('begin')"
                        @update:model-value="(v) => (model!.boxShadow.blur = v)"
                    />
                </div>
            </div>
        </template>

        <!-- 透明度 -->
        <div class="config-section">透明度</div>
        <div class="config-row">
            <span class="config-label">透明度</span>
            <div class="config-control">
                <ConfigSliderCombo
                    :model-value="model.opacity"
                    :min="0"
                    :max="100"
                    @begin="$emit('begin')"
                    @update:model-value="(v) => (model!.opacity = v)"
                />
            </div>
        </div>
    </div>
    <div v-else class="empty">选择一个组件后可设置样式</div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import ConfigColorField from './components/ConfigColorField.vue'
import ConfigSliderCombo from './components/ConfigSliderCombo.vue'
import ConfigSliderLock from './components/ConfigSliderLock.vue'
import { defaultComponentStyle } from './styleUtils'

type RequiredStyle = Required<ReturnType<typeof defaultComponentStyle>>

const props = defineProps<{ component: { props: Record<string, any> } | null }>()
const emit = defineEmits<{ (e: 'begin'): void }>()

// 选中组件时深合并补齐缺失键；缺省背景按类型：普通组件白底，分割线/悬浮按钮透明。
function ensureFull(cs: any, type?: string) {
    const d = defaultComponentStyle(type)
    if (!cs.background) cs.background = { ...d.background }
    else {
        if (cs.background.type === undefined) cs.background.type = 'color'
        for (const k of [
            'color',
            'gradientStart',
            'gradientEnd',
            'gradientDirection',
            'image'
        ] as const) {
            if (cs.background[k] === undefined) cs.background[k] = (d.background as any)[k]
        }
    }
    if (!cs.boxShadow) cs.boxShadow = { ...d.boxShadow }
    if (!cs.border) cs.border = { ...d.border }
    if (cs.opacity === undefined) cs.opacity = d.opacity
    if (!cs.margin) cs.margin = { ...d.margin }
    if (!cs.padding) cs.padding = { ...d.padding }
    if (!cs.borderRadius) cs.borderRadius = { ...d.borderRadius }
    return cs
}

watch(
    () => props.component,
    (c) => {
        if (!c) return
        const type = (c as { type?: string }).type
        c.props.componentStyle = ensureFull(c.props.componentStyle || defaultComponentStyle(type), type)
    },
    { immediate: true }
)

const model = computed<RequiredStyle | null>(() => {
    return (props.component?.props.componentStyle ?? null) as RequiredStyle | null
})

// 边框/阴影的显示隐藏为本地 UI 态（对齐 Shop）：仅在切换选中组件时按数据重算，
// 编辑期间把值调回 0 不会自动折叠表单。
const borderVisible = ref(false)
const shadowVisible = ref(false)
watch(
    model,
    (m) => {
        if (!m) return
        borderVisible.value = m.border.width > 0 && m.border.style !== 'none'
        shadowVisible.value = m.boxShadow.blur > 0 || m.boxShadow.x !== 0 || m.boxShadow.y !== 0
    },
    { immediate: true }
)

function onBorderVisibleChange(v: any) {
    emit('begin')
    borderVisible.value = !!v
    if (v) {
        model.value!.border.width = 1
        if (model.value!.border.style === 'none') model.value!.border.style = 'solid'
    } else {
        model.value!.border.width = 0
    }
}

function onShadowVisibleChange(v: any) {
    emit('begin')
    shadowVisible.value = !!v
    if (v) {
        model.value!.boxShadow.blur = 10
    } else {
        model.value!.boxShadow = { x: 0, y: 0, blur: 0, color: 'rgba(0,0,0,0.1)' }
    }
}
</script>

<style scoped lang="scss">
@import './config-ui.scss';

.empty {
    color: var(--color-text-tertiary);
    font-size: var(--font-size-body);
    padding: 16px;
    text-align: center;
}
</style>
