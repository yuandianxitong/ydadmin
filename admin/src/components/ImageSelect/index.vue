<template>
    <div class="image-select">
        <template v-if="!multiple">
            <div class="image-select-box" @click="openPicker">
                <template v-if="modelValue">
                    <img :src="displayUrl(modelValue as string)" class="preview-img" alt="" />
                    <div class="remove-btn" @click.stop="handleRemove(0)">
                        <el-icon><Close /></el-icon>
                    </div>
                </template>
                <el-icon v-else class="add-icon"><Plus /></el-icon>
            </div>
        </template>
        <template v-else>
            <div class="image-select-multi">
                <div
                    v-for="(url, idx) in multiValues"
                    :key="url + idx"
                    class="image-select-box is-item"
                >
                    <img :src="displayUrl(url)" class="preview-img" alt="" />
                    <div class="remove-btn" @click.stop="handleRemove(idx)">
                        <el-icon><Close /></el-icon>
                    </div>
                </div>
                <div
                    v-if="!limit || multiValues.length < limit"
                    class="image-select-box"
                    @click="openPicker"
                >
                    <el-icon class="add-icon"><Plus /></el-icon>
                </div>
            </div>
        </template>

        <el-dialog v-model="visible" title="选择图片" width="720px" destroy-on-close>
            <div class="picker-toolbar">
                <Upload type="image" :multiple="multiple" :limit="uploadLimit" @success="onUploaded">
                    <el-button type="primary">上传图片</el-button>
                </Upload>
                <el-button @click="loadFiles">刷新</el-button>
            </div>
            <div v-loading="loading" class="picker-grid">
                <div
                    v-for="f in files"
                    :key="f.id"
                    class="picker-item"
                    :class="{ active: selected.has(f.url) }"
                    @click="toggle(f.url)"
                >
                    <img :src="displayUrl(f.url)" alt="" />
                </div>
                <el-empty v-if="!loading && !files.length" description="暂无图片" />
            </div>
            <template #footer>
                <el-button @click="visible = false">取消</el-button>
                <el-button type="primary" @click="confirm">确定</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { Close, Plus } from '@element-plus/icons-vue'
import { computed, ref } from 'vue'

import { fileApi } from '@/api/file'
import Upload from '@/components/Upload/index.vue'
import useAppStore from '@/store/modules/app.store'
import type { FileInfo } from '@/types/system'

interface Props {
    modelValue: string | string[]
    multiple?: boolean
    limit?: number
}

const props = withDefaults(defineProps<Props>(), {
    multiple: false,
    limit: 0
})
const emit = defineEmits<{ (e: 'update:modelValue', value: string | string[]): void }>()

const appStore = useAppStore()
const visible = ref(false)
const loading = ref(false)
const files = ref<FileInfo[]>([])
const selected = ref<Set<string>>(new Set())

const multiValues = computed(() => (Array.isArray(props.modelValue) ? props.modelValue : []))

const uploadLimit = computed(() => {
    if (!props.multiple) return 1
    if (!props.limit) return 10
    return Math.max(props.limit - multiValues.value.length, 1)
})

function displayUrl(url: string) {
    return appStore.getImageUrl?.(url) || url
}

async function loadFiles() {
    loading.value = true
    try {
        const res: any = await fileApi.getList({ page_no: 1, page_size: 60, type: 'image' } as any)
        files.value = (res?.data?.list || res?.list || []) as FileInfo[]
    } catch {
        files.value = []
    } finally {
        loading.value = false
    }
}

function openPicker() {
    selected.value = new Set()
    visible.value = true
    loadFiles()
}

function toggle(url: string) {
    if (!props.multiple) {
        selected.value = new Set([url])
        return
    }
    const next = new Set(selected.value)
    if (next.has(url)) next.delete(url)
    else {
        if (props.limit && next.size >= uploadLimit.value) return
        next.add(url)
    }
    selected.value = next
}

function onUploaded(response: any) {
    const url = response?.data?.url || response?.url || ''
    if (!url) return
    if (!props.multiple) {
        emit('update:modelValue', url)
        visible.value = false
        return
    }
    const current = Array.isArray(props.modelValue) ? [...props.modelValue] : []
    current.push(url)
    emit('update:modelValue', props.limit ? current.slice(0, props.limit) : current)
    loadFiles()
}

function confirm() {
    const urls = [...selected.value]
    if (!urls.length) {
        visible.value = false
        return
    }
    if (!props.multiple) {
        emit('update:modelValue', urls[0])
    } else {
        const current = Array.isArray(props.modelValue) ? [...props.modelValue] : []
        const merged = [...current, ...urls]
        emit('update:modelValue', props.limit ? merged.slice(0, props.limit) : merged)
    }
    visible.value = false
}

function handleRemove(idx: number) {
    if (!props.multiple) emit('update:modelValue', '')
    else {
        const arr = Array.isArray(props.modelValue) ? [...props.modelValue] : []
        arr.splice(idx, 1)
        emit('update:modelValue', arr)
    }
}
</script>

<style scoped lang="scss">
.image-select-box {
    width: 72px;
    height: 72px;
    border: 1px dashed var(--el-border-color);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    background: var(--el-fill-color-lighter);
}
.image-select-multi {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.preview-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.add-icon {
    font-size: 22px;
    color: var(--el-text-color-secondary);
}
.remove-btn {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.55);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}
.picker-toolbar {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
}
.picker-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 8px;
    min-height: 200px;
    max-height: 420px;
    overflow: auto;
}
.picker-item {
    aspect-ratio: 1;
    border: 2px solid transparent;
    border-radius: 6px;
    overflow: hidden;
    cursor: pointer;
    img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    &.active {
        border-color: var(--el-color-primary);
    }
}
</style>
