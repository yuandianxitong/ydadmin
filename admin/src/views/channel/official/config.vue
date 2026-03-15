<template>
    <div class="channel-config">
        <!-- 公众号信息 -->
        <el-card shadow="never" class="config-card">
            <template #header>
                <div class="card-header">
                    <el-icon><InfoFilled /></el-icon>
                    <span>{{ $t('channel.official.title') }}</span>
                </div>
            </template>
            <el-form
                :model="formData"
                label-width="150px"
                label-position="left"
                style="max-width: 600px"
            >
                <el-form-item :label="$t('channel.official.name')">
                    <el-input
                        v-model="formData.wechat_official_name"
                        :placeholder="$t('channel.official.namePlaceholder')"
                    />
                </el-form-item>
                <el-form-item :label="$t('channel.official.originalId')">
                    <el-input
                        v-model="formData.wechat_official_original_id"
                        :placeholder="$t('channel.official.originalIdPlaceholder')"
                    />
                </el-form-item>
                <el-form-item :label="$t('channel.official.qrcode')">
                    <div class="qrcode-upload">
                        <el-upload
                            class="qrcode-uploader"
                            :action="uploadUrl"
                            :headers="uploadHeaders"
                            :show-file-list="false"
                            accept="image/*"
                            :on-success="
                                (res: any) => onUploadSuccess(res, 'wechat_official_qrcode')
                            "
                        >
                            <el-image
                                v-if="formData.wechat_official_qrcode"
                                :src="getImageUrl(formData.wechat_official_qrcode)"
                                fit="contain"
                                class="qrcode-preview"
                            />
                            <div v-else class="qrcode-placeholder">
                                <el-icon :size="24"><Plus /></el-icon>
                                <span>{{ $t('channel.official.uploadQrcode') }}</span>
                            </div>
                        </el-upload>
                        <div class="upload-tip">{{ $t('channel.official.qrcodeTip') }}</div>
                    </div>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 开发信息 -->
        <el-card shadow="never" class="config-card">
            <template #header>
                <div class="card-header">
                    <el-icon><Key /></el-icon>
                    <span>{{ $t('channel.official.devInfo') }}</span>
                </div>
            </template>
            <el-form
                :model="formData"
                label-width="150px"
                label-position="left"
                style="max-width: 600px"
            >
                <el-form-item label="AppID">
                    <el-input
                        v-model="formData.wechat_official_app_id"
                        :placeholder="$t('channel.official.appIdPlaceholder')"
                    />
                </el-form-item>
                <el-form-item label="AppSecret">
                    <el-input
                        v-model="formData.wechat_official_app_secret"
                        type="password"
                        show-password
                        :placeholder="$t('channel.official.appSecretPlaceholder')"
                    />
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 服务器配置 -->
        <el-card shadow="never" class="config-card">
            <template #header>
                <div class="card-header">
                    <el-icon><Monitor /></el-icon>
                    <span>{{ $t('channel.official.serverConfig') }}</span>
                </div>
            </template>
            <el-alert type="info" :closable="false" show-icon class="server-alert">
                <template #title>{{ $t('channel.official.serverAlertTitle') }}</template>
                <template #default>
                    <div class="server-url">
                        {{ $t('channel.official.serverUrlLabel') }}<el-text type="primary" tag="code">{{
                            serverUrl
                        }}</el-text>
                        <el-button type="primary" text size="small" @click="copyUrl"
                            >{{ $t('common.copy') }}</el-button
                        >
                    </div>
                </template>
            </el-alert>
            <el-form
                :model="formData"
                label-width="150px"
                label-position="left"
                style="max-width: 600px"
                class="mt-16"
            >
                <el-form-item label="Token">
                    <el-input
                        v-model="formData.wechat_official_token"
                        :placeholder="$t('channel.official.tokenPlaceholder')"
                    />
                </el-form-item>
                <el-form-item label="EncodingAESKey">
                    <el-input
                        v-model="formData.wechat_official_aes_key"
                        :placeholder="$t('channel.official.aesKeyPlaceholder')"
                    />
                    <div class="form-tip">{{ $t('channel.official.aesKeyTip') }}</div>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 保存按钮 -->
        <div class="save-bar">
            <el-button type="primary" :loading="loading" @click="handleSave">{{ $t('channel.saveConfig') }}</el-button>
        </div>
    </div>
</template>

<script setup lang="ts" name="ChannelOfficialConfig">
import { InfoFilled, Key, Monitor, Plus } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { batchUpdateConfigs, getConfigsByGroup } from '@/api/system/config'
import useAppStore from '@/store/modules/app.store'
import { getToken } from '@/utils/auth'

const { t } = useI18n()
const loading = ref(false)
const appStore = useAppStore()

const formData = reactive<Record<string, string>>({
    wechat_official_name: '',
    wechat_official_original_id: '',
    wechat_official_qrcode: '',
    wechat_official_app_id: '',
    wechat_official_app_secret: '',
    wechat_official_token: '',
    wechat_official_aes_key: ''
})

const serverUrl = computed(() => {
    const siteUrl = appStore.config?.site_url || window.location.origin
    return `${siteUrl}/api/wechat/serve`
})

const uploadUrl = computed(() => `${import.meta.env.VITE_APP_API_URL || ''}/adminapi/upload/image`)
const uploadHeaders = computed(() => ({ Authorization: `Bearer ${getToken()}` }))

const getImageUrl = (path: string) => {
    if (!path) return ''
    if (path.startsWith('http')) return path
    const siteUrl = appStore.config?.site_url || window.location.origin
    return `${siteUrl}${path}`
}

const onUploadSuccess = (res: any, key: string) => {
    if (res.code === 200) {
        formData[key] = res.data?.url || res.data?.path || ''
    } else {
        ElMessage.error(res.message || t('message.uploadFailed'))
    }
}

const copyUrl = () => {
    navigator.clipboard.writeText(serverUrl.value).then(() => {
        ElMessage.success(t('message.copySuccess'))
    })
}

onMounted(async () => {
    try {
        loading.value = true
        const res = await getConfigsByGroup('wechat_official')
        const configs = res.data || []
        configs.forEach((c: any) => {
            if (c.config_key in formData) {
                formData[c.config_key] = c.config_value || ''
            }
        })
    } catch {
        ElMessage.error(t('message.fetchFailed'))
    } finally {
        loading.value = false
    }
})

const handleSave = async () => {
    try {
        loading.value = true
        const configs = Object.entries(formData).map(([key, value]) => ({
            config_key: key,
            config_value: value
        }))
        await batchUpdateConfigs(configs)
        ElMessage.success(t('message.saveSuccess'))
    } catch {
        ElMessage.error(t('message.configSaveFailed'))
    } finally {
        loading.value = false
    }
}
</script>

<style lang="scss" scoped>
.channel-config {
    .config-card {
        margin-bottom: 16px;
    }
    .card-header {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 15px;
        font-weight: 600;
    }
    .server-alert {
        margin-bottom: 0;
    }
    .mt-16 {
        margin-top: 16px;
    }
    .server-url {
        display: flex;
        align-items: center;
        gap: 8px;
        line-height: 1.8;
        code {
            padding: 2px 6px;
            background: var(--el-fill-color-light);
            border-radius: 3px;
        }
    }
    .form-tip {
        font-size: 12px;
        color: var(--el-text-color-placeholder);
        margin-top: 4px;
    }
    .qrcode-upload {
        .qrcode-uploader {
            :deep(.el-upload) {
                border: 1px dashed var(--el-border-color);
                border-radius: 8px;
                cursor: pointer;
                overflow: hidden;
                transition: border-color 0.2s;
                &:hover {
                    border-color: var(--el-color-primary);
                }
            }
        }
        .qrcode-preview {
            width: 120px;
            height: 120px;
            display: block;
        }
        .qrcode-placeholder {
            width: 120px;
            height: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: var(--el-text-color-placeholder);
            font-size: 12px;
        }
        .upload-tip {
            font-size: 12px;
            color: var(--el-text-color-placeholder);
            margin-top: 6px;
        }
    }
    .save-bar {
        padding: 12px 0;
        display: flex;
        justify-content: flex-start;
    }
}
</style>
