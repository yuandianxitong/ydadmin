<template>
    <div class="wang-editor-container">
        <Toolbar
            :editor="editorRef"
            :default-config="toolbarConfig"
            :mode="mode"
            style="border-bottom: 1px solid #ccc"
        />
        <Editor
            v-model="valueHtml"
            :default-config="editorConfig"
            :mode="mode"
            :style="{ height: height + 'px', overflowY: 'hidden' }"
            @on-created="handleCreated"
            @on-change="handleChange"
        />
    </div>
</template>

<script setup lang="ts">
import '@wangeditor/editor/dist/css/style.css'

import type { IDomEditor, IEditorConfig, IToolbarConfig } from '@wangeditor/editor'
import { Editor, Toolbar } from '@wangeditor/editor-for-vue'
import { onBeforeUnmount, ref, shallowRef, watch } from 'vue'

import request from '@/utils/request'

interface Props {
    modelValue?: string
    height?: number
    mode?: 'default' | 'simple'
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    height: 400,
    mode: 'default'
})

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

const editorRef = shallowRef<IDomEditor>()
const valueHtml = ref(props.modelValue)

const toolbarConfig: Partial<IToolbarConfig> = {}

const editorConfig: Partial<IEditorConfig> = {
    placeholder: '请输入内容...',
    MENU_CONF: {
        uploadImage: {
            maxFileSize: 5 * 1024 * 1024,
            maxNumberOfFiles: 20,
            allowedFileTypes: ['image/*'],
            // 通过 axios 实例上传，自动携带最新 Token
            async customUpload(
                file: File,
                insertFn: (url: string, alt?: string, href?: string) => void
            ) {
                const formData = new FormData()
                formData.append('file', file)
                const res = await request.post('/adminapi/upload/image', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                })
                const url = res?.data?.data?.url || res?.data?.data?.path || ''
                if (url) {
                    insertFn(url, '', '')
                }
            }
        }
    }
}

// 监听外部 modelValue 变化
watch(
    () => props.modelValue,
    (val) => {
        if (val !== valueHtml.value) {
            valueHtml.value = val
        }
    }
)

const handleCreated = (editor: IDomEditor) => {
    editorRef.value = editor
}

const handleChange = (editor: IDomEditor) => {
    const html = editor.getHtml()
    emit('update:modelValue', html)
}

// 组件销毁时，及时销毁编辑器
onBeforeUnmount(() => {
    const editor = editorRef.value
    if (editor == null) return
    editor.destroy()
})
</script>

<style lang="scss" scoped>
.wang-editor-container {
    border: 1px solid #ccc;
    border-radius: 4px;
    overflow: hidden;
    width: 100%;
    z-index: 100;
}
</style>
