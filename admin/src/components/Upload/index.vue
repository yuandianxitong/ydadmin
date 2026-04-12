<template>
    <div class="upload">
        <el-upload
            ref="uploadRefs"
            v-model:file-list="fileList"
            :action="action"
            :multiple="multiple"
            :limit="limit"
            :show-file-list="false"
            :headers="headers"
            :data="data"
            :on-progress="handleProgress"
            :on-success="handleSuccess"
            :on-exceed="handleExceed"
            :on-error="handleError"
            :accept="getAccept"
        >
            <slot />
        </el-upload>
        <el-dialog
            v-if="showProgress && fileList.length"
            v-model="visible"
            :title="$t('component.upload.progress')"
            :close-on-click-modal="false"
            width="500px"
            :modal="false"
            @close="handleClose"
        >
            <div class="file-list p-4">
                <template v-for="(item, index) in fileList" :key="index">
                    <div class="mb-5">
                        <div>{{ item.name }}</div>
                        <div class="flex-1">
                            <el-progress :percentage="parseInt(item.percentage)" />
                        </div>
                    </div>
                </template>
            </div>
        </el-dialog>
    </div>
</template>

<script lang="ts">
import type { ElUpload } from 'element-plus'
import { computed, defineComponent, ref, shallowRef } from 'vue'
import { useI18n } from 'vue-i18n'

import { appConfig } from '@/constants/appSetting'
import { RequestCodeEnum } from '@/constants/request'
import useUserStore from '@/store/modules/user.store'
import feedback from '@/utils/feedback'

export default defineComponent({
    components: {},
    props: {
        // 上传文件类型
        type: {
            type: String,
            default: 'image'
        },
        // 是否支持多选
        multiple: {
            type: Boolean,
            default: true
        },
        // 多选时最多选择几条
        limit: {
            type: Number,
            default: 10
        },
        // 上传时的额外参数
        data: {
            type: Object,
            default: () => ({})
        },
        // 是否显示上传进度
        showProgress: {
            type: Boolean,
            default: false
        }
    },
    emits: ['change', 'error', 'success', 'allSuccess'],
    setup(props, { emit }) {
        const { t } = useI18n()
        const userStore = useUserStore()
        const uploadRefs = shallowRef<InstanceType<typeof ElUpload>>()
        const action = ref(`${appConfig.baseUrl}/${appConfig.urlPrefix}/upload/${props.type}`)
        const headers = computed(() => ({
            Authorization: `Bearer ${userStore.token}`
        }))
        const visible = ref(false)
        const fileList = ref<any[]>([])

        let uploadLen = 0
        let uploadTotal = 0
        const handleProgress = () => {
            visible.value = true
            // 每次 progress 触发时刷新总数（el-upload 在 progress 阶段已将文件加入 fileList）
            uploadTotal = fileList.value.length
        }
        const checkAllDone = () => {
            if (uploadTotal > 0 && uploadLen >= uploadTotal) {
                uploadLen = 0
                uploadTotal = 0
                fileList.value = []
                emit('allSuccess')
            }
        }
        const handleSuccess = (response: any, file: any) => {
            uploadLen++
            checkAllDone()
            emit('change', file)
            if (response.code == RequestCodeEnum.SUCCESS) {
                emit('success', response)
            } else if (response.message) {
                feedback.msgError(response.message)
            }
        }
        const handleError = (event: any, file: any) => {
            uploadLen++
            checkAllDone()
            feedback.msgError(t('component.upload.fileFailed', { name: file.name }))
            visible.value = false
            emit('change', file)
            emit('error', file)
        }
        const handleExceed = () => {
            feedback.msgError(t('component.upload.exceedLimit', { limit: props.limit }))
        }
        const handleClose = () => {
            fileList.value = []
            visible.value = false
        }

        const getAccept = computed(() => {
            switch (props.type) {
                case 'image':
                    return '.jpg,.png,.gif,.jpeg,.ico'
                case 'video':
                    return '.wmv,.avi,.mpg,.mpeg,.3gp,.mov,.mp4,.flv,.rmvb,.mkv'
                default:
                    return '*'
            }
        })
        return {
            uploadRefs,
            action,
            headers,
            visible,
            fileList,
            getAccept,
            handleProgress,
            handleSuccess,
            handleError,
            handleExceed,
            handleClose
        }
    }
})
</script>

<style lang="scss"></style>
