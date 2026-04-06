<template>
    <el-drawer
        v-model="visible"
        :title="form.id ? t('articleMgmt.editArticle') : t('articleMgmt.addArticle')"
        size="60%"
        :close-on-click-modal="false"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-form-item :label="t('articleMgmt.articleTitle')" prop="title">
                <el-input
                    v-model="form.title"
                    :placeholder="t('articleMgmt.titlePlaceholder')"
                    maxlength="200"
                    show-word-limit
                />
            </el-form-item>

            <el-form-item :label="t('articleMgmt.category')" prop="category_id">
                <el-tree-select
                    v-model="form.category_id"
                    :data="categoryTreeData"
                    node-key="id"
                    :props="{ label: 'name' }"
                    :placeholder="t('articleMgmt.selectCategory')"
                    check-strictly
                    clearable
                    style="width: 100%"
                />
            </el-form-item>

            <el-form-item :label="t('articleMgmt.cover')" prop="cover">
                <div>
                    <el-upload
                        class="cover-uploader"
                        :show-file-list="false"
                        action="/adminapi/upload/image"
                        :headers="uploadHeaders"
                        :on-success="handleCoverSuccess"
                        :before-upload="beforeCoverUpload"
                    >
                        <img
                            v-if="form.cover"
                            :src="appStore.getImageUrl(form.cover)"
                            class="cover-image"
                            :alt="t('articleMgmt.coverAlt')"
                        />
                        <el-icon v-else class="cover-uploader-icon"><Plus /></el-icon>
                    </el-upload>
                    <div class="upload-tip">{{ t('articleMgmt.coverUploadTip') }}</div>
                </div>
            </el-form-item>

            <el-form-item :label="t('articleMgmt.summary')" prop="summary">
                <el-input
                    v-model="form.summary"
                    type="textarea"
                    :rows="3"
                    :placeholder="t('articleMgmt.summaryPlaceholder')"
                    maxlength="500"
                    show-word-limit
                />
            </el-form-item>

            <el-form-item :label="t('articleMgmt.content')" prop="content">
                <WangEditor v-model="form.content" :height="400" />
            </el-form-item>

            <el-form-item :label="t('articleMgmt.tags')" prop="tags">
                <div class="tags-container">
                    <el-tag
                        v-for="tag in form.tags"
                        :key="tag"
                        closable
                        :disable-transitions="false"
                        @close="handleTagRemove(tag)"
                    >
                        {{ tag }}
                    </el-tag>
                    <el-input
                        v-if="tagInputVisible"
                        ref="tagInputRef"
                        v-model="tagInputValue"
                        size="small"
                        style="width: 100px"
                        @keyup.enter="handleTagConfirm"
                        @blur="handleTagConfirm"
                    />
                    <el-button v-else size="small" @click="showTagInput">
                        + {{ t('articleMgmt.addTag') }}
                    </el-button>
                </div>
            </el-form-item>

            <el-row :gutter="20">
                <el-col :span="12">
                    <el-form-item :label="t('articleMgmt.author')" prop="author">
                        <el-input
                            v-model="form.author"
                            :placeholder="t('articleMgmt.authorPlaceholder')"
                        />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="t('articleMgmt.publishAt')" prop="publish_at">
                        <el-date-picker
                            v-model="form.publish_at"
                            type="datetime"
                            :placeholder="t('articleMgmt.publishTimePlaceholder')"
                            value-format="YYYY-MM-DD HH:mm:ss"
                            style="width: 100%"
                        />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item :label="t('common.status')" prop="status">
                <el-radio-group v-model="form.status">
                    <el-radio :value="0">{{ t('articleMgmt.draft') }}</el-radio>
                    <el-radio :value="1">{{ t('articleMgmt.published') }}</el-radio>
                </el-radio-group>
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button @click="handleClose">{{ t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="submitting" @click="handleSubmit">{{
                t('common.confirm')
            }}</el-button>
        </template>
    </el-drawer>
</template>

<script setup lang="ts">
import { Plus } from '@element-plus/icons-vue'
import type { FormRules } from 'element-plus'
import { ElMessage } from 'element-plus'
import { computed, nextTick, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { articleApi } from '@/api/article'
import WangEditor from '@/components/WangEditor/index.vue'
import { useFormDialog } from '@/hooks/useFormDialog'
import { useAppStore } from '@/store'
import { getToken } from '@/utils/auth'

const { t } = useI18n()
const appStore = useAppStore()

interface ArticleFormData {
    id?: number
    title: string
    category_id?: number
    cover: string
    summary: string
    content: string
    tags: string[]
    author: string
    status: number
    publish_at: string
}

const props = defineProps<{
    modelValue: boolean
    formData: Record<string, any>
    categoryOptions: any[]
}>()

const emit = defineEmits<{
    'update:modelValue': [value: boolean]
    success: []
}>()

// 上传请求头（computed 确保 Token 刷新后仍有效）
const uploadHeaders = computed(() => ({
    Authorization: `Bearer ${getToken()}`
}))

const { form, formRef, submitting, visible, handleSubmit, handleClose, resetForm } =
    useFormDialog<ArticleFormData>({
        defaultForm: {
            id: undefined,
            title: '',
            category_id: undefined,
            cover: '',
            summary: '',
            content: '',
            tags: [],
            author: '',
            status: 0,
            publish_at: ''
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => articleApi.create(data),
        updateFn: (id, data) => articleApi.update(id, data),
        sourceData: () => props.formData as Partial<ArticleFormData>
    })

// 标签输入相关
const tagInputVisible = ref(false)
const tagInputValue = ref('')
const tagInputRef = ref<InstanceType<(typeof import('element-plus'))['ElInput']>>()

// 分类树形数据
const categoryTreeData = computed(() => {
    return props.categoryOptions || []
})

const rules = computed<FormRules>(() => ({
    title: [{ required: true, message: t('articleMgmt.validate.titleRequired'), trigger: 'blur' }]
}))

// 封面上传成功
const handleCoverSuccess = (response: any) => {
    if (response.code === 200 || response.code === 0) {
        form.cover = response.data?.url || response.data?.path || response.data
        ElMessage.success(t('articleMgmt.coverUploadSuccess'))
    } else {
        ElMessage.error(response.message || t('articleMgmt.uploadFailed'))
    }
}

// 封面上传前校验
const beforeCoverUpload = (file: File) => {
    const isImage = file.type.startsWith('image/')
    if (!isImage) {
        ElMessage.error(t('articleMgmt.onlyImageAllowed'))
        return false
    }
    const isLt2M = file.size / 1024 / 1024 < 2
    if (!isLt2M) {
        ElMessage.error(t('articleMgmt.imageSizeLimit'))
        return false
    }
    return true
}

// 标签操作
const handleTagRemove = (tag: string) => {
    form.tags = form.tags.filter((t) => t !== tag)
}

const showTagInput = () => {
    tagInputVisible.value = true
    nextTick(() => {
        tagInputRef.value?.input?.focus()
    })
}

const handleTagConfirm = () => {
    if (tagInputValue.value) {
        const val = tagInputValue.value.trim()
        if (val && !form.tags.includes(val)) {
            form.tags.push(val)
        }
    }
    tagInputVisible.value = false
    tagInputValue.value = ''
}
</script>

<style lang="scss" scoped>
.cover-uploader {
    :deep(.el-upload) {
        border: 1px dashed var(--color-border);
        border-radius: 6px;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        transition: border-color 0.2s;
        width: 178px;
        height: 120px;
        display: flex;
        align-items: center;
        justify-content: center;

        &:hover {
            border-color: var(--el-color-primary);
        }
    }

    .cover-image {
        width: 178px;
        height: 120px;
        object-fit: cover;
        display: block;
    }

    .cover-uploader-icon {
        font-size: 28px;
        color: var(--color-text-disabled);
    }
}

.upload-tip {
    font-size: 12px;
    color: var(--color-text-tertiary);
    margin-top: 8px;
    line-height: 1.4;
}

.tags-container {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
}
</style>
