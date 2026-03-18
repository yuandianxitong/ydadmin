<template>
    <el-drawer
        :model-value="modelValue"
        :title="formData.id ? '编辑文章' : '新增文章'"
        size="60%"
        :close-on-click-modal="false"
        @update:model-value="$emit('update:modelValue', $event)"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <el-form-item label="文章标题" prop="title">
                <el-input v-model="form.title" placeholder="请输入文章标题" maxlength="200" show-word-limit />
            </el-form-item>

            <el-form-item label="文章分类" prop="category_id">
                <el-tree-select
                    v-model="form.category_id"
                    :data="categoryTreeData"
                    :props="{ label: 'name', value: 'id' }"
                    placeholder="请选择文章分类"
                    check-strictly
                    clearable
                    style="width: 100%"
                />
            </el-form-item>

            <el-form-item label="封面图片" prop="cover">
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
                        alt="封面"
                    />
                    <el-icon v-else class="cover-uploader-icon"><Plus /></el-icon>
                </el-upload>
                <div class="upload-tip">建议尺寸 800x450，支持 jpg/png/gif，不超过 2MB</div>
            </el-form-item>

            <el-form-item label="文章摘要" prop="summary">
                <el-input
                    v-model="form.summary"
                    type="textarea"
                    :rows="3"
                    placeholder="请输入文章摘要"
                    maxlength="500"
                    show-word-limit
                />
            </el-form-item>

            <el-form-item label="文章内容" prop="content">
                <WangEditor v-model="form.content" :height="400" />
            </el-form-item>

            <el-form-item label="标签" prop="tags">
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
                    <el-button
                        v-else
                        size="small"
                        @click="showTagInput"
                    >
                        + 添加标签
                    </el-button>
                </div>
            </el-form-item>

            <el-row :gutter="20">
                <el-col :span="12">
                    <el-form-item label="作者" prop="author">
                        <el-input v-model="form.author" placeholder="请输入作者" />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item label="发布时间" prop="publish_at">
                        <el-date-picker
                            v-model="form.publish_at"
                            type="datetime"
                            placeholder="请选择发布时间"
                            value-format="YYYY-MM-DD HH:mm:ss"
                            style="width: 100%"
                        />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item :label="$t('common.status')" prop="status">
                <el-radio-group v-model="form.status">
                    <el-radio :value="0">草稿</el-radio>
                    <el-radio :value="1">已发布</el-radio>
                </el-radio-group>
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button @click="$emit('update:modelValue', false)">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="submitting" @click="handleSubmit">{{ $t('common.confirm') }}</el-button>
        </template>
    </el-drawer>
</template>

<script setup lang="ts">
import { Plus } from '@element-plus/icons-vue'
import type { FormInstance, FormRules } from 'element-plus'
import { ElMessage } from 'element-plus'
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { articleApi } from '@/api/article'
import WangEditor from '@/components/WangEditor/index.vue'
import { useAppStore } from '@/store'
import { getToken } from '@/utils/auth'

const { t } = useI18n()
const appStore = useAppStore()

const props = defineProps<{
    modelValue: boolean
    formData: Record<string, any>
    categoryOptions: any[]
}>()

const emit = defineEmits<{
    'update:modelValue': [value: boolean]
    success: []
}>()

const formRef = ref<FormInstance>()
const submitting = ref(false)

// 上传请求头
const uploadHeaders = {
    Authorization: `Bearer ${getToken()}`
}

const form = reactive({
    id: undefined as number | undefined,
    title: '',
    category_id: undefined as number | undefined,
    cover: '',
    summary: '',
    content: '',
    tags: [] as string[],
    author: '',
    status: 0,
    publish_at: ''
})

// 标签输入相关
const tagInputVisible = ref(false)
const tagInputValue = ref('')
const tagInputRef = ref<InstanceType<typeof import('element-plus')['ElInput']>>()

// 分类树形数据
const categoryTreeData = computed(() => {
    return props.categoryOptions || []
})

const rules = computed<FormRules>(() => ({
    title: [{ required: true, message: '请输入文章标题', trigger: 'blur' }]
}))

watch(
    () => props.modelValue,
    (val) => {
        if (val && props.formData) {
            Object.assign(form, {
                id: props.formData.id || undefined,
                title: props.formData.title || '',
                category_id: props.formData.category_id || undefined,
                cover: props.formData.cover || '',
                summary: props.formData.summary || '',
                content: props.formData.content || '',
                tags: props.formData.tags || [],
                author: props.formData.author || '',
                status: props.formData.status ?? 0,
                publish_at: props.formData.publish_at || ''
            })
        }
    }
)

// 封面上传成功
const handleCoverSuccess = (response: any) => {
    if (response.code === 200 || response.code === 0) {
        form.cover = response.data?.url || response.data?.path || response.data
        ElMessage.success('封面上传成功')
    } else {
        ElMessage.error(response.message || '上传失败')
    }
}

// 封面上传前校验
const beforeCoverUpload = (file: File) => {
    const isImage = file.type.startsWith('image/')
    if (!isImage) {
        ElMessage.error('只能上传图片文件')
        return false
    }
    const isLt2M = file.size / 1024 / 1024 < 2
    if (!isLt2M) {
        ElMessage.error('图片大小不能超过 2MB')
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

const resetForm = () => {
    formRef.value?.resetFields()
    Object.assign(form, {
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
    })
}

const handleSubmit = async () => {
    if (!formRef.value) return
    await formRef.value.validate()

    submitting.value = true
    try {
        const submitData = { ...form }
        if (form.id) {
            await articleApi.update(form.id, submitData)
            ElMessage.success(t('message.updateSuccess'))
        } else {
            await articleApi.create(submitData)
            ElMessage.success(t('message.createSuccess'))
        }
        emit('update:modelValue', false)
        emit('success')
    } catch (error: any) {
        ElMessage.error(error?.message || t('common.error'))
    } finally {
        submitting.value = false
    }
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
