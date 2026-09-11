<!-- tenant/src/views/diy/editor/components/LinkPicker.vue -->
<template>
    <div class="link-picker">
        <el-input v-model="localValue" placeholder="输入或选择链接" @change="emitUpdate">
            <template #append>
                <el-button @click="openDialog"
                    ><el-icon><Link /></el-icon
                ></el-button>
            </template>
        </el-input>

        <el-dialog v-model="dialogVisible" title="选择链接" class="dlg-md" append-to-body>
            <div class="link-picker__body">
                <el-tabs v-model="activeTab" tab-position="left">
                    <el-tab-pane
                        v-for="g in groups"
                        :key="g.category"
                        :label="g.category"
                        :name="g.category"
                    >
                        <div class="link-picker__items">
                            <div
                                v-for="item in g.items"
                                :key="item.path"
                                class="link-picker__item"
                                :class="{
                                    'link-picker__item--active': selected?.path === item.path
                                }"
                                @click="pick(item)"
                            >
                                <span>{{ item.label }}</span>
                                <span class="link-picker__path">{{ item.path }}</span>
                            </div>
                        </div>
                    </el-tab-pane>
                    <el-tab-pane label="自定义" name="__custom">
                        <el-input
                            v-model="customDraft"
                            type="textarea"
                            :rows="3"
                            placeholder="输入自定义链接，如 /pages/product-detail/index?id=1 或外链 https://..."
                        />
                    </el-tab-pane>
                </el-tabs>

                <!-- 带参链接：动态参数表单 -->
                <div v-if="selected && selected.params_schema.length" class="link-picker__params">
                    <div class="link-picker__params-title">填写参数</div>
                    <div
                        v-for="f in selected.params_schema"
                        :key="f.key"
                        class="link-picker__param-row"
                    >
                        <span class="link-picker__param-label"
                            >{{ f.label }}<i v-if="f.required">*</i></span
                        >
                        <el-input v-model="paramValues[f.key]" :placeholder="f.label" />
                    </div>
                </div>
            </div>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="confirmSelect">确定</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { Link } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { reactive, ref, watch } from 'vue'

import type { CatalogLink } from '@/api/diy'

import { applyLinkParams, useLinkCatalog } from '../useLinkCatalog'

const props = defineProps<{ modelValue: string }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>()

const { groups, load } = useLinkCatalog()

const localValue = ref(props.modelValue || '')
const dialogVisible = ref(false)
const activeTab = ref('__custom')
const customDraft = ref('')
const selected = ref<CatalogLink | null>(null)
const paramValues = reactive<Record<string, string>>({})

watch(
    () => props.modelValue,
    (v) => {
        localValue.value = v || ''
    }
)

async function openDialog() {
    await load()
    customDraft.value = localValue.value
    selected.value = null
    for (const k of Object.keys(paramValues)) delete paramValues[k]
    activeTab.value = groups.value[0]?.category || '__custom'
    dialogVisible.value = true
}

function pick(item: CatalogLink) {
    selected.value = item
    for (const k of Object.keys(paramValues)) delete paramValues[k]
}

function emitUpdate() {
    emit('update:modelValue', localValue.value)
}

function confirmSelect() {
    let value: string
    if (activeTab.value === '__custom') {
        value = customDraft.value
    } else if (selected.value) {
        // 校验 required 参数
        const missing = selected.value.params_schema
            .filter((f) => f.required && !paramValues[f.key])
            .map((f) => f.label)
        if (missing.length) {
            ElMessage.error(`请填写参数：${missing.join('、')}`)
            return
        }
        value = applyLinkParams(selected.value.path, { ...paramValues })
    } else {
        ElMessage.error('请选择一个链接')
        return
    }
    localValue.value = value
    emitUpdate()
    dialogVisible.value = false
}
</script>

<style scoped lang="scss">
// 作为 flex item（config-card__link-row）时不设宽度会收缩为内容宽，与同卡片的标题输入框错位
.link-picker {
    width: 100%;
}
.link-picker__body {
    min-height: 320px;
}
.link-picker__items {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.link-picker__item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 12px;
    border-radius: var(--radius-md);
    cursor: pointer;
    &:hover {
        background: var(--color-surface-sunken);
    }
    &--active {
        background: var(--el-color-primary-light-9);
        color: var(--el-color-primary);
    }
}
.link-picker__path {
    font-size: var(--font-size-small);
    color: var(--color-text-tertiary);
}
.link-picker__params {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--color-border);
}
.link-picker__params-title {
    font-size: 12.5px;
    font-weight: 600;
    margin-bottom: 8px;
}
.link-picker__param-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}
.link-picker__param-label {
    width: 80px;
    font-size: 12.5px;
    i {
        color: var(--el-color-danger);
    }
}
</style>
