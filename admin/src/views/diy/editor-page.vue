<template>
    <div class="diy-editor-page">
        <div class="editor-topbar">
            <div class="topbar-left">
                <el-button text class="back-btn" @click="handleBack">
                    <el-icon><ArrowLeft /></el-icon>
                    {{ $t('diyEditor.back') }}
                </el-button>
                <span class="page-name">{{ pageTitle }}</span>
            </div>
            <div class="topbar-right">
                <el-button :disabled="!undoStack.length" @click="editor.undo">
                    <el-icon><RefreshLeft /></el-icon>
                    {{ $t('diyEditor.undo') }}
                </el-button>
                <el-button :disabled="!redoStack.length" @click="editor.redo">
                    <el-icon><RefreshRight /></el-icon>
                    {{ $t('diyEditor.redo') }}
                </el-button>
                <el-button @click="versionVisible = true">
                    <el-icon><Clock /></el-icon>
                    {{ $t('diyEditor.versions') }}
                </el-button>
                <el-button :loading="saving" :disabled="!loaded" @click="handleSave">
                    <el-icon><Document /></el-icon>
                    {{ $t('diyEditor.save') }}
                </el-button>
                <el-button type="primary" :disabled="!loaded" :loading="publishing" @click="handlePublish">
                    <el-icon><Upload /></el-icon>
                    {{ $t('diyEditor.publish') }}
                </el-button>
            </div>
        </div>
        <div class="editor-body">
            <div class="panel-left"><ComponentPanel :page-key="pageKey" @add="onAdd" /></div>
            <div class="panel-center">
                <SimulatorPreview
                    :components="components"
                    :selected-id="selectedId"
                    :page-title="pageTitle"
                    :page-background="pageSettings.background_color"
                    @select="editor.select"
                    @move="editor.move"
                    @remove="editor.remove"
                    @reorder="onReorder"
                    @duplicate="editor.duplicate"
                    @toggle-hidden="editor.toggleHidden"
                />
            </div>
            <div class="panel-right">
                <PropertyPanel :component="selected" @begin="editor.beginChange" />
            </div>
        </div>
        <VersionDrawer v-model="versionVisible" :page-key="pageKey" @restored="reload" />
    </div>
</template>
<script setup lang="ts">
import { ArrowLeft, Clock, Document, RefreshLeft, RefreshRight, Upload } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'

import { diyApi } from '@/api/diy'

import ComponentPanel from './editor/ComponentPanel.vue'
import PropertyPanel from './editor/PropertyPanel.vue'
import SimulatorPreview from './editor/SimulatorPreview.vue'
import { useEditor } from './editor/useEditor'
import { usePluginWidgets } from './editor/usePluginWidgets'
import VersionDrawer from './editor/VersionDrawer.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const pageKey = computed(() => (route.query.key as string) || 'home')
const pageTitle = computed(() => {
    if (pageKey.value === 'home') return t('diyEditor.homeTitle')
    return (typeof route.query.title === 'string' && route.query.title) || pageKey.value
})

const editor = useEditor()
// 解构为顶层 ref，模板自动解包，避免 editor.xxx.value 响应式跟踪异常
const { components, selectedId, selected, pageSettings, undoStack, redoStack } = editor
const { metaOf } = usePluginWidgets()
function onAdd(type: string) {
    const meta = metaOf(type)
    editor.addWidget(type, meta ? structuredClone(meta.default_props) : undefined)
}
const loaded = ref(false)
const saving = ref(false)
const publishing = ref(false)
const versionVisible = ref(false)

// ───── 未保存修改守卫 ─────
const savedSnapshot = ref('')
const currentSnapshot = () =>
    JSON.stringify({ components: components.value, pageSettings: pageSettings.value })
const isDirty = () => loaded.value && currentSnapshot() !== savedSnapshot.value

async function reload() {
    try {
        const res = await diyApi.getPageDraft(pageKey.value)
        const comps = Array.isArray(res.data?.components) ? res.data.components : []
        const settings =
            res.data?.page_settings && typeof res.data.page_settings === 'object'
                ? res.data.page_settings
                : { background_color: '' }
        editor.setState(comps, settings)
        loaded.value = true
        savedSnapshot.value = currentSnapshot()
    } catch {
        // 错误已由响应拦截器提示；保持 loaded=false 禁用保存，避免空数据覆盖
    }
}
onMounted(reload)
watch(pageKey, () => reload())

function onReorder(ids: string[]) {
    editor.reorder(ids)
}

async function handleSave() {
    if (!loaded.value) return
    saving.value = true
    try {
        await diyApi.savePageDraft(pageKey.value, {
            components: components.value,
            page_settings: pageSettings.value
        })
        ElMessage.success(t('diyEditor.saved'))
        savedSnapshot.value = currentSnapshot()
    } catch {
        // request 拦截器已 ElMessage；勿让 reject 冒泡到 ErrorBoundary
    } finally {
        saving.value = false
    }
}
async function handlePublish() {
    if (!loaded.value) return
    publishing.value = true
    try {
        await diyApi.savePageDraft(pageKey.value, {
            components: components.value,
            page_settings: pageSettings.value
        })
        await diyApi.publishPage(pageKey.value)
        ElMessage.success(t('diyEditor.published'))
        savedSnapshot.value = currentSnapshot()
    } catch {
        // request 拦截器已 ElMessage；勿让 reject 冒泡到 ErrorBoundary
    } finally {
        publishing.value = false
    }
}

onBeforeRouteLeave(async () => {
    if (!isDirty()) return true
    try {
        await ElMessageBox.confirm(t('diyEditor.unsavedLeave'), t('diyEditor.unsavedTitle'), {
            type: 'warning',
            confirmButtonText: t('diyEditor.leave'),
            cancelButtonText: t('common.cancel')
        })
        return true
    } catch {
        return false
    }
})

function onBeforeUnload(e: BeforeUnloadEvent) {
    if (isDirty()) e.preventDefault()
}
onMounted(() => window.addEventListener('beforeunload', onBeforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload))

function handleBack() {
    router.push('/diy/home') // 页面装修列表（路由 path 未变）
}
</script>
<style scoped lang="scss">
.diy-editor-page {
    display: flex;
    flex-direction: column;
    height: 100vh;
    background: var(--color-bg-page);
}

.editor-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 60px; // 对齐 Shop 编辑器（66px 是 layout 外层头部，不是这里）
    padding: 0 16px;
    background: var(--color-brand);
    color: #fff;
    flex: none;

    // 按钮图标与文字间距（EP 只对 el-icon+span 自动加距，裸文本节点不生效）
    :deep(.el-button .el-icon) {
        margin-right: 4px;
    }

    .topbar-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .page-name {
        font-size: 15px;
        font-weight: 600;
    }
    .topbar-right {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    // 返回等 text 按钮
    :deep(.el-button.is-text) {
        color: #fff;
        &:hover,
        &:focus {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
        }
    }
    // 撤销/重做/历史/保存：白色透明边框幽灵按钮
    :deep(.el-button:not(.is-text):not(.el-button--primary)) {
        background: rgba(255, 255, 255, 0.12);
        border-color: rgba(255, 255, 255, 0.35);
        color: #fff;
        &:hover:not(.is-disabled) {
            background: rgba(255, 255, 255, 0.22);
            border-color: rgba(255, 255, 255, 0.55);
            color: #fff;
        }
        &.is-disabled {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.18);
            color: rgba(255, 255, 255, 0.45);
        }
    }
    // 发布：反白实心（品牌字用 tenant 主色）
    :deep(.el-button--primary) {
        background: #fff;
        border-color: #fff;
        color: var(--color-brand);
        &:hover:not(.is-disabled) {
            background: #fff;
            color: var(--color-brand-active);
            opacity: 0.92;
        }
        &.is-disabled {
            background: rgba(255, 255, 255, 0.6);
            color: color-mix(in srgb, var(--color-brand) 55%, transparent);
        }
    }
}

.editor-body {
    flex: 1;
    display: flex;
    min-height: 0;

    .panel-left {
        width: 220px;
        flex: none;
        background: var(--color-surface);
        border-right: 1px solid var(--color-divider);
        overflow: auto;
    }
    .panel-center {
        // 对齐 Shop：中栏不滚动，手机固定高度居中，滚动发生在机身内部（.sim-list）
        flex: 1;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: var(--color-bg-page);
    }
    .panel-right {
        width: 400px;
        flex: none;
        background: var(--color-surface);
        border-left: 1px solid var(--color-divider);
        overflow: auto;
    }
}
</style>
