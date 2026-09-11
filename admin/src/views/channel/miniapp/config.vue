<template>
    <div class="channel-config">
        <!-- 小程序信息 -->
        <el-card shadow="never" class="config-card">
            <template #header>
                <div class="card-header">
                    <el-icon><InfoFilled /></el-icon>
                    <span>{{ $t('channel.miniapp.title') }}</span>
                </div>
            </template>
            <el-form
                :model="formData"
                label-width="150px"
                label-position="left"
                style="max-width: 600px"
            >
                <el-form-item :label="$t('channel.miniapp.name')">
                    <el-input
                        v-model="formData.wechat_mini_name"
                        :placeholder="$t('channel.miniapp.namePlaceholder')"
                    />
                </el-form-item>
                <el-form-item :label="$t('channel.miniapp.originalId')">
                    <el-input
                        v-model="formData.wechat_mini_original_id"
                        :placeholder="$t('channel.miniapp.originalIdPlaceholder')"
                    />
                </el-form-item>
                <el-form-item :label="$t('channel.miniapp.qrcode')">
                    <div class="qrcode-upload">
                        <el-upload
                            class="qrcode-uploader"
                            :action="uploadUrl"
                            :headers="uploadHeaders"
                            :show-file-list="false"
                            accept="image/*"
                            :on-success="(res: any) => onUploadSuccess(res, 'wechat_mini_qrcode')"
                        >
                            <el-image
                                v-if="formData.wechat_mini_qrcode"
                                :src="getImageUrl(formData.wechat_mini_qrcode)"
                                fit="contain"
                                class="qrcode-preview"
                            />
                            <div v-else class="qrcode-placeholder">
                                <el-icon :size="24"><Plus /></el-icon>
                                <span>{{ $t('channel.miniapp.uploadQrcode') }}</span>
                            </div>
                        </el-upload>
                        <div class="upload-tip">{{ $t('channel.miniapp.qrcodeTip') }}</div>
                    </div>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 开发信息 -->
        <el-card shadow="never" class="config-card">
            <template #header>
                <div class="card-header">
                    <el-icon><Key /></el-icon>
                    <span>{{ $t('channel.miniapp.devInfo') }}</span>
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
                        v-model="formData.wechat_mini_app_id"
                        :placeholder="$t('channel.miniapp.appIdPlaceholder')"
                    />
                </el-form-item>
                <el-form-item label="AppSecret">
                    <el-input
                        v-model="formData.wechat_mini_app_secret"
                        type="password"
                        show-password
                        :placeholder="$t('channel.miniapp.appSecretPlaceholder')"
                    />
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 消息推送配置 -->
        <el-card shadow="never" class="config-card">
            <template #header>
                <div class="card-header">
                    <el-icon><ChatDotSquare /></el-icon>
                    <span>{{ $t('channel.miniapp.messagePush') }}</span>
                </div>
            </template>
            <el-alert type="info" :closable="false" show-icon class="server-alert">
                <template #title>{{ $t('channel.miniapp.serverAlertTitle') }}</template>
                <template #default>
                    <div class="server-url">
                        {{ $t('channel.miniapp.serverUrlLabel')
                        }}<el-text type="primary" tag="code">{{ serverUrl }}</el-text>
                        <el-button type="primary" text size="small" @click="copyUrl">{{
                            $t('common.copy')
                        }}</el-button>
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
                        v-model="formData.wechat_mini_msg_token"
                        :placeholder="$t('channel.miniapp.tokenPlaceholder')"
                    />
                </el-form-item>
                <el-form-item label="EncodingAESKey">
                    <el-input
                        v-model="formData.wechat_mini_msg_aes_key"
                        :placeholder="$t('channel.miniapp.aesKeyPlaceholder')"
                    />
                </el-form-item>
                <el-form-item :label="$t('channel.miniapp.dataFormat')">
                    <el-select v-model="formData.wechat_mini_msg_format" style="width: 200px">
                        <el-option label="JSON" value="JSON" />
                        <el-option label="XML" value="XML" />
                    </el-select>
                </el-form-item>
                <el-form-item label="消息加密方式">
                    <el-radio-group v-model="formData.wechat_mini_encrypt_type">
                        <el-radio value="1">明文模式</el-radio>
                        <el-radio value="2">兼容模式</el-radio>
                        <el-radio value="3">安全模式</el-radio>
                    </el-radio-group>
                    <div class="form-tip">
                        需与微信小程序后台「开发管理 > 开发设置」中的加密方式保持一致
                    </div>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 域名信息（只读） -->
        <el-card shadow="never" class="config-card">
            <template #header>
                <div class="card-header">
                    <el-icon><Link /></el-icon>
                    <span>域名信息</span>
                </div>
            </template>
            <el-alert type="warning" :closable="false" show-icon style="margin-bottom: 16px">
                <template #title
                    >请将以下域名配置到微信小程序后台「开发管理 > 开发设置 > 服务器域名」</template
                >
            </el-alert>
            <el-form label-width="180px" label-position="left" style="max-width: 650px">
                <el-form-item label="request合法域名">
                    <el-input :model-value="siteUrlHttps" disabled>
                        <template #append>
                            <el-button @click="copyText(siteUrlHttps)">复制</el-button>
                        </template>
                    </el-input>
                </el-form-item>
                <el-form-item label="socket合法域名">
                    <el-input :model-value="siteUrlWss" disabled>
                        <template #append>
                            <el-button @click="copyText(siteUrlWss)">复制</el-button>
                        </template>
                    </el-input>
                </el-form-item>
                <el-form-item label="uploadFile合法域名">
                    <el-input :model-value="siteUrlHttps" disabled>
                        <template #append>
                            <el-button @click="copyText(siteUrlHttps)">复制</el-button>
                        </template>
                    </el-input>
                </el-form-item>
                <el-form-item label="downloadFile合法域名">
                    <el-input :model-value="siteUrlHttps" disabled>
                        <template #append>
                            <el-button @click="copyText(siteUrlHttps)">复制</el-button>
                        </template>
                    </el-input>
                </el-form-item>
                <el-form-item label="业务域名">
                    <el-input :model-value="siteDomain" disabled>
                        <template #append>
                            <el-button @click="copyText(siteDomain)">复制</el-button>
                        </template>
                    </el-input>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 保存按钮 -->
        <div class="save-bar">
            <el-button type="primary" :loading="loading" @click="handleSave">{{
                $t('channel.saveConfig')
            }}</el-button>
        </div>
    </div>
</template>

<script setup lang="ts" name="ChannelMiniAppConfig">
import { ChatDotSquare, InfoFilled, Key, Link, Plus } from '@element-plus/icons-vue'
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
    wechat_mini_name: '',
    wechat_mini_original_id: '',
    wechat_mini_qrcode: '',
    wechat_mini_app_id: '',
    wechat_mini_app_secret: '',
    wechat_mini_msg_token: '',
    wechat_mini_msg_aes_key: '',
    wechat_mini_msg_format: 'JSON',
    wechat_mini_encrypt_type: '1'
})

const serverUrl = computed(() => {
    const siteUrl = appStore.config?.site_url || window.location.origin
    return `${siteUrl}/api/wechat/mini/notify`
})

const siteDomain = computed(() => {
    const siteUrl = appStore.config?.site_url || window.location.origin
    try {
        return new URL(siteUrl).hostname
    } catch {
        return siteUrl.replace(/^https?:\/\//, '').replace(/\/.*$/, '')
    }
})

const siteUrlHttps = computed(() => {
    const siteUrl = appStore.config?.site_url || window.location.origin
    return siteUrl.replace(/^http:/, 'https:')
})

const siteUrlWss = computed(() => {
    const siteUrl = appStore.config?.site_url || window.location.origin
    return siteUrl.replace(/^https?:/, 'wss:')
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

const copyText = (text: string) => {
    navigator.clipboard.writeText(text).then(() => {
        ElMessage.success(t('message.copySuccess'))
    })
}

onMounted(async () => {
    try {
        loading.value = true
        const res = await getConfigsByGroup('wechat_mini')
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
    .form-tip {
        width: 100%;
        font-size: 12px;
        color: var(--el-text-color-placeholder);
        line-height: 1.6;
        margin-top: 4px;
    }
    .save-bar {
        padding: 12px 0;
        display: flex;
        justify-content: flex-start;
    }
}
</style>
