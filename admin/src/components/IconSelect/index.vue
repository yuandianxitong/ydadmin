<template>
    <div ref="iconSelectRef" :style="{ width, maxWidth: '100%' }">
        <el-popover
            :visible="popoverVisible"
            :width="popoverWidth"
            :popper-style="{ maxWidth: 'calc(100vw - 32px)' }"
            :popper-options="{ modifiers: [{ name: 'preventOverflow', options: { padding: 16 } }] }"
            placement="bottom-end"
        >
            <template #reference>
                <div @click="popoverVisible = !popoverVisible">
                    <slot>
                        <el-input
                            v-model="selectedIcon"
                            readonly
                            :placeholder="$t('component.iconSelect.placeholder')"
                            class="reference"
                        >
                            <template #prepend>
                                <!-- 统一用 Icon 组件渲染 -->
                                <Icon
                                    v-if="selectedIcon"
                                    :name="selectedIcon"
                                    :size="iconSize"
                                    color="currentColor"
                                />
                            </template>
                            <template #suffix>
                                <!-- 清空按钮 -->
                                <i
                                    v-if="selectedIcon"
                                    class="i-svg:x text-14px cursor-pointer mr-8px text-ink-400"
                                    @click.stop="clearSelectedIcon"
                                />

                                <!-- 下拉箭头 -->
                                <i
                                    class="i-svg:chevron-down text-14px text-ink-400 transition-transform duration-300"
                                    :class="{ 'rotate-180': popoverVisible }"
                                    @click.stop="togglePopover"
                                />
                            </template>
                        </el-input>
                    </slot>
                </div>
            </template>

            <!-- 图标选择弹窗 -->
            <div ref="popoverContentRef" class="icon-select-panel">
                <el-input
                    v-model="filterText"
                    :placeholder="$t('component.iconSelect.searchPlaceholder')"
                    clearable
                    @input="filterIcons"
                    ><template #prefix><i class="i-svg:search text-14px" /></template
                ></el-input>

                <el-tabs v-model="activeTab" @tab-click="handleTabClick">
                    <el-tab-pane name="svg"
                        ><template #label
                            >{{ $t('component.iconSelect.svgIcons') }}
                            <span class="tab-count">{{ svgIcons.length }}</span></template
                        >
                        <el-scrollbar :wrap-style="{ maxHeight: '300px' }">
                            <ul class="icon-grid">
                                <li
                                    v-for="icon in filteredSvgIcons"
                                    :key="icon"
                                    class="icon-grid-item"
                                    :class="{ active: selectedIcon === `i-svg:${icon}` }"
                                    :title="icon"
                                    @click="selectIcon(icon, 'svg')"
                                >
                                    <Icon :name="`i-svg:${icon}`" :size="iconSize" />
                                </li>
                            </ul>
                        </el-scrollbar>
                    </el-tab-pane>

                    <el-tab-pane name="element"
                        ><template #label
                            >{{ $t('component.iconSelect.elementIcons') }}
                            <span class="tab-count">{{ elementIcons.length }}</span></template
                        >
                        <el-scrollbar :wrap-style="{ maxHeight: '300px' }">
                            <ul class="icon-grid">
                                <li
                                    v-for="icon in filteredElementIcons"
                                    :key="icon"
                                    class="icon-grid-item"
                                    :class="{ active: selectedIcon === `el-icon-${icon}` }"
                                    :title="icon"
                                    @click="selectIcon(icon, 'element')"
                                >
                                    <Icon :name="`el-icon-${icon}`" :size="iconSize" />
                                </li>
                            </ul>
                        </el-scrollbar>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </el-popover>
    </div>
</template>

<script setup lang="ts">
import * as ElementPlusIconsVue from '@element-plus/icons-vue'
import { onClickOutside } from '@vueuse/core'
import { computed, onMounted, ref, watch } from 'vue'

const props = defineProps({
    modelValue: String,
    /** 输入框外层宽度（可为 "100%"、"500px" 等） */
    width: { type: String, default: '500px' },
    /** 图标选择弹窗的宽度（必须是像素值，不能用 "100%"，否则会撑满整个视口） */
    popoverWidth: { type: [String, Number], default: 400 }
})
const emit = defineEmits(['update:modelValue'])

const iconSelectRef = ref<HTMLElement>()
const popoverContentRef = ref<HTMLElement>()
const popoverVisible = ref(false)
const activeTab = ref<'svg' | 'element'>('svg')

const svgIcons = ref<string[]>([])
const elementIcons = ref<string[]>(Object.keys(ElementPlusIconsVue))
const selectedIcon = ref(props.modelValue || '')

const filterText = ref('')
const filteredSvgIcons = ref<string[]>([])
const filteredElementIcons = ref<string[]>([])

const width = props.width
const popoverWidth = props.popoverWidth
const iconSize = '1.2em'

const isElementIcon = computed(() => selectedIcon.value.startsWith('el-icon-'))

function loadIcons() {
    // IconSelect 位于 src/components/IconSelect/，SVG 图标位于 src/assets/icons/
    // 相对路径需要向上两级到 src/，再进入 assets/icons/
    const modules = import.meta.glob('../../assets/icons/*.svg', { eager: true })
    svgIcons.value = Object.keys(modules).map((path) => path.replace(/.*\/(.*)\.svg$/, '$1'))
    filteredSvgIcons.value = svgIcons.value
    filteredElementIcons.value = elementIcons.value
}

function handleTabClick(tab: any) {
    activeTab.value = tab.props.name
    filterIcons()
}

function filterIcons() {
    if (activeTab.value === 'svg') {
        filteredSvgIcons.value = filterText.value
            ? svgIcons.value.filter((i) => i.includes(filterText.value.toLowerCase()))
            : svgIcons.value
    } else {
        filteredElementIcons.value = filterText.value
            ? elementIcons.value.filter((i) =>
                  i.toLowerCase().includes(filterText.value.toLowerCase())
              )
            : elementIcons.value
    }
}

function selectIcon(icon: string, type: 'svg' | 'element') {
    const val = type === 'element' ? `el-icon-${icon}` : `i-svg:${icon}`
    emit('update:modelValue', val)
    selectedIcon.value = val
    popoverVisible.value = false
}

function togglePopover() {
    popoverVisible.value = !popoverVisible.value
}

function clearSelectedIcon() {
    selectedIcon.value = ''
    emit('update:modelValue', '')
}

onClickOutside(iconSelectRef, () => (popoverVisible.value = false), {
    ignore: [popoverContentRef]
})

// 同步外部 modelValue 变化到本地显示（编辑弹窗复用同一组件实例时必要）
watch(
    () => props.modelValue,
    (val) => {
        selectedIcon.value = val || ''
        if (val) {
            activeTab.value = val.startsWith('el-icon-') ? 'element' : 'svg'
        }
    }
)

onMounted(() => {
    loadIcons()
    if (props.modelValue) {
        selectedIcon.value = props.modelValue
        activeTab.value = isElementIcon.value ? 'element' : 'svg'
    }
})
</script>

<style scoped lang="scss">
.reference :deep(.el-input__inner) {
    cursor: pointer;
}

.icon-select-panel {
    padding: 4px;
}

.tab-count {
    font-size: 11px;
    color: var(--ink-400);
    margin-left: 4px;
}

.icon-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding: 0;
    margin: 0;
    list-style: none;
}

.icon-grid-item {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    cursor: pointer;
    border: 1px solid var(--ink-100);
    border-radius: 4px;
    transition: all 0.15s;
    color: var(--ink-600);

    &:hover {
        border-color: var(--brand-400);
        color: var(--brand-500);
        transform: scale(1.08);
    }

    &.active {
        border-color: var(--brand-500);
        background: var(--brand-50);
        color: var(--brand-500);
    }
}

.icon-empty {
    text-align: center;
    padding: 24px;
    color: var(--ink-400);
    font-size: 13px;
}
</style>
