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

import { Editor, Toolbar } from '@wangeditor/editor-for-vue'
import type { IDomEditor, IEditorConfig, IToolbarConfig } from '@wangeditor/editor'
import { onBeforeUnmount, ref, shallowRef, watch } from 'vue'

import { getToken } from '@/utils/auth'

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
            server: '/adminapi/upload/image',
            fieldName: 'file',
            maxFileSize: 5 * 1024 * 1024,
            maxNumberOfFiles: 20,
            allowedFileTypes: ['image/*'],
            headers: {
                Authorization: `Bearer ${getToken()}`
            },
            // 自定义插入图片
            customInsert(res: any, insertFn: (url: string, alt?: string, href?: string) => void) {
                const url = res?.data?.url || res?.data?.path || ''
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
