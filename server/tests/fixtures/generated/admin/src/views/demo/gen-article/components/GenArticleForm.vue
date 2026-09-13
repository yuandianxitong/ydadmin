<!-- 由代码生成器生成，可按需修改。 -->
<template>
    <el-dialog
        v-model="visible"
        :title="form.id ? '编辑生成器夹具表' : '新增生成器夹具表'"
        width="600px"
        destroy-on-close
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-form-item label="标题" prop="title">
                <el-input v-model="form.title" placeholder="请输入标题" maxlength="200" clearable />
            </el-form-item>

            <el-form-item label="摘要" prop="summary">
                <el-input v-model="form.summary" placeholder="请输入摘要" maxlength="500" clearable />
            </el-form-item>

            <el-form-item label="正文" prop="content">
                <el-input v-model="form.content" type="textarea" :rows="4" placeholder="请输入正文" />
            </el-form-item>

            <el-form-item label="封面图" prop="cover_image">
                <div class="image-uploader">
                    <el-upload
                        :show-file-list="false"
                        action="/adminapi/upload/image"
                        :headers="uploadHeaders"
                        :on-success="(response: any) => handleUploadSuccess(response, 'cover_image')"
                        :before-upload="beforeImageUpload"
                    >
                        <img v-if="form.cover_image" :src="appStore.getImageUrl(form.cover_image)" alt="封面图" />
                        <el-icon v-else class="uploader-icon"><Plus /></el-icon>
                    </el-upload>
                </div>
            </el-form-item>

            <el-form-item label="分类" prop="category">
                <el-select v-model="form.category" placeholder="请选择分类" clearable style="width: 100%">
                    <el-option label="news" value="news" />
                    <el-option label="tech" value="tech" />
                    <el-option label="life" value="life" />
                </el-select>
            </el-form-item>

            <el-form-item label="价格" prop="price">
                <el-input-number
                    v-model="form.price"
                    :min="0"
                    :precision="2"
                    :step="0.01"
                    style="width: 100%"
                />
            </el-form-item>

            <el-form-item label="浏览量" prop="view_count">
                <el-input-number
                    v-model="form.view_count"
                    :min="0"
                    style="width: 100%"
                />
            </el-form-item>

            <el-form-item label="别名" prop="slug">
                <el-input v-model="form.slug" placeholder="请输入别名" maxlength="100" clearable />
            </el-form-item>

            <el-form-item label="发布时间" prop="published_at">
                <el-date-picker
                    v-model="form.published_at"
                    type="datetime"
                    value-format="YYYY-MM-DD HH:mm:ss"
                    placeholder="请选择发布时间"
                    style="width: 100%"
                />
            </el-form-item>

            <el-form-item label="状态" prop="status">
                <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
            </el-form-item>

            <el-form-item label="排序" prop="sort">
                <el-input-number
                    v-model="form.sort"
                    :min="0"
                    style="width: 100%"
                />
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button @click="handleClose">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="submitting" @click="handleSubmit">{{
                $t('common.confirm')
            }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { Plus } from '@element-plus/icons-vue'
import type { FormRules } from 'element-plus'
import { ElMessage } from 'element-plus'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { genArticleApi } from '@/api/gen-article'
import { useFormDialog } from '@/hooks/useFormDialog'
import { useAppStore } from '@/store'
import { getToken } from '@/utils/auth'

const { t } = useI18n()
const appStore = useAppStore()

interface GenArticleFormData {
    id?: number
    title: string
    summary?: string
    content?: string
    cover_image?: string
    category?: 'news' | 'tech' | 'life'
    price?: number
    view_count?: number
    slug: string
    published_at?: string
    status?: number
    sort?: number
}

interface Props {
    modelValue: boolean
    formData: Record<string, any>
}

interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { form, formRef, submitting, visible, handleSubmit, handleClose, resetForm } =
    useFormDialog<GenArticleFormData>({
        defaultForm: {
            id: undefined,
            title: '',
            summary: '',
            content: '',
            cover_image: '',
            category: 'news',
            price: 0,
            view_count: 0,
            slug: '',
            published_at: '',
            status: 1,
            sort: 0
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => genArticleApi.create(data),
        updateFn: (id, data) => genArticleApi.update(id, data),
        sourceData: () => props.formData as Partial<GenArticleFormData>
    })

const rules = computed<FormRules>(() => ({
    title: [{ required: true, message: t('message.required'), trigger: 'blur' }],
    slug: [{ required: true, message: t('message.required'), trigger: 'blur' }]
}))

const uploadHeaders = computed(() => ({
    Authorization: `Bearer ${getToken()}`
}))

function handleUploadSuccess(response: any, field: string) {
    if (response.code === 200 || response.code === 0) {
        ;(form as Record<string, any>)[field] = response.data?.url || response.data?.path || response.data
    } else {
        ElMessage.error(response.message || t('message.uploadFailed'))
    }
}

function beforeImageUpload(file: File) {
    const isImage = file.type.startsWith('image/')
    if (!isImage) {
        ElMessage.error(t('message.imageOnly'))
        return false
    }
    const isLt2M = file.size / 1024 / 1024 < 2
    if (!isLt2M) {
        ElMessage.error(t('message.fileSizeLimit'))
        return false
    }
    return true
}
</script>

<style lang="scss" scoped>
.image-uploader {
    :deep(.el-upload) {
        border: 1px dashed var(--color-border);
        border-radius: 6px;
        cursor: pointer;
        overflow: hidden;
        width: 120px;
        height: 120px;
        display: flex;
        align-items: center;
        justify-content: center;

        &:hover {
            border-color: var(--el-color-primary);
        }
    }

    img {
        width: 120px;
        height: 120px;
        object-fit: cover;
        display: block;
    }

    .uploader-icon {
        font-size: 24px;
        color: var(--color-text-disabled);
    }
}
</style>
