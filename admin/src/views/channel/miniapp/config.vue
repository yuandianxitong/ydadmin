<template>
    <div class="channel-config">
        <!-- Page Head -->
        <div class="page-head">
            <div>
                <div class="page-title">{{ $t('channel.miniapp.title') }}</div>
                <div class="page-desc">微信小程序基础信息与开发配置</div>
            </div>
            <div class="page-actions">
                <el-button type="primary" :loading="loading" @click="handleSave">
                    {{ $t('channel.saveConfig') }}
                </el-button>
            </div>
        </div>

        <!-- Sections -->
        <div class="set-sections">
            <!-- 基础信息 -->
            <div class="set-card">
                <div class="set-card-head">
                    <h3>{{ $t('channel.miniapp.title') }}</h3>
                    <span class="dsc">小程序名称、原始 ID 与二维码等基本信息</span>
                </div>
                <div class="set-card-body">
                    <!-- 小程序名称 -->
                    <div class="set-item">
                        <div>
                            <div class="set-item-label">{{ $t('channel.miniapp.name') }}</div>
                            <div class="set-item-desc">
                                {{ $t('channel.miniapp.namePlaceholder') }}
                            </div>
                        </div>
                        <div class="set-item-ctrl">
                            <el-input
                                style="width: 320px"
                                v-model="formData.wechat_mini_name"
                                :placeholder="$t('channel.miniapp.namePlaceholder')"
                            />
                        </div>
                    </div>

                    <!-- 原始ID -->
                    <div class="set-item">
                        <div>
                            <div class="set-item-label">{{ $t('channel.miniapp.originalId') }}</div>
                            <div class="set-item-desc">
                                {{ $t('channel.miniapp.originalIdPlaceholder') }}
                            </div>
                        </div>
                        <div class="set-item-ctrl">
                            <el-input
                                style="width: 320px"
                                v-model="formData.wechat_mini_original_id"
                                :placeholder="$t('channel.miniapp.originalIdPlaceholder')"
                            />
                        </div>
                    </div>

                    <!-- 小程序码 -->
                    <div class="set-item">
                        <div>
                            <div class="set-item-label">{{ $t('channel.miniapp.qrcode') }}</div>
                            <div class="set-item-desc">{{ $t('channel.miniapp.qrcodeTip') }}</div>
                        </div>
                        <div class="set-item-ctrl">
                            <div class="qrcode-upload">
                                <el-upload
                                    class="qrcode-uploader"
                                    :action="uploadUrl"
                                    :headers="uploadHeaders"
                                    :show-file-list="false"
                                    accept="image/*"
                                    :on-success="
                                        (res: any) => onUploadSuccess(res, 'wechat_mini_qrcode')
                                    "
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
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 开发信息 -->
            <div class="set-card">
                <div class="set-card-head">
                    <h3>{{ $t('channel.miniapp.devInfo') }}</h3>
                    <span class="dsc">AppID 与 AppSecret，用于接口鉴权</span>
                </div>
                <div class="set-card-body">
                    <!-- AppID -->
                    <div class="set-item">
                        <div>
                            <div class="set-item-label">AppID</div>
                            <div class="set-item-desc">
                                {{ $t('channel.miniapp.appIdPlaceholder') }}
                            </div>
                        </div>
                        <div class="set-item-ctrl">
                            <el-input
                                style="width: 320px"
                                v-model="formData.wechat_mini_app_id"
                                :placeholder="$t('channel.miniapp.appIdPlaceholder')"
                            />
                        </div>
                    </div>

                    <!-- AppSecret -->
                    <div class="set-item">
                        <div>
                            <div class="set-item-label">AppSecret</div>
                            <div class="set-item-desc">
                                {{ $t('channel.miniapp.appSecretPlaceholder') }}
                            </div>
                        </div>
                        <div class="set-item-ctrl">
                            <el-input
                                style="width: 320px"
                                v-model="formData.wechat_mini_app_secret"
                                type="password"
                                show-password
                                :placeholder="$t('channel.miniapp.appSecretPlaceholder')"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- 消息推送配置 -->
            <div class="set-card">
                <div class="set-card-head">
                    <h3>{{ $t('channel.miniapp.messagePush') }}</h3>
                    <span class="dsc">配置微信小程序消息推送服务器地址与加密方式</span>
                </div>
                <div class="set-card-body">
                    <!-- 服务器地址提示 -->
                    <div class="set-item set-item--alert">
                        <div class="server-notice">
                            <span class="server-notice-label">{{
                                $t('channel.miniapp.serverAlertTitle')
                            }}</span>
                            <div class="server-url">
                                {{ $t('channel.miniapp.serverUrlLabel') }}
                                <el-text type="primary" tag="code">{{ serverUrl }}</el-text>
                                <el-button type="primary" text size="small" @click="copyUrl">
                                    {{ $t('common.copy') }}
                                </el-button>
                            </div>
                        </div>
                    </div>

                    <!-- Token -->
                    <div class="set-item">
                        <div>
                            <div class="set-item-label">Token</div>
                            <div class="set-item-desc">
                                {{ $t('channel.miniapp.tokenPlaceholder') }}
                            </div>
                        </div>
                        <div class="set-item-ctrl">
                            <el-input
                                style="width: 320px"
                                v-model="formData.wechat_mini_msg_token"
                                :placeholder="$t('channel.miniapp.tokenPlaceholder')"
                            />
                        </div>
                    </div>

                    <!-- EncodingAESKey -->
                    <div class="set-item">
                        <div>
                            <div class="set-item-label">EncodingAESKey</div>
                            <div class="set-item-desc">
                                {{ $t('channel.miniapp.aesKeyPlaceholder') }}
                            </div>
                        </div>
                        <div class="set-item-ctrl">
                            <el-input
                                style="width: 320px"
                                v-model="formData.wechat_mini_msg_aes_key"
                                :placeholder="$t('channel.miniapp.aesKeyPlaceholder')"
                            />
                        </div>
                    </div>

                    <!-- 数据格式 -->
                    <div class="set-item">
                        <div>
                            <div class="set-item-label">{{ $t('channel.miniapp.dataFormat') }}</div>
                            <div class="set-item-desc">消息数据包的序列化格式</div>
                        </div>
                        <div class="set-item-ctrl">
                            <el-select
                                v-model="formData.wechat_mini_msg_format"
                                style="width: 200px"
                            >
                                <el-option label="JSON" value="JSON" />
                                <el-option label="XML" value="XML" />
                            </el-select>
                        </div>
                    </div>

                    <!-- 消息加密方式 -->
                    <div class="set-item">
                        <div>
                            <div class="set-item-label">消息加密方式</div>
                            <div class="set-item-desc">
                                需与微信小程序后台「开发管理 > 开发设置」中的加密方式保持一致
                            </div>
                        </div>
                        <div class="set-item-ctrl">
                            <el-radio-group v-model="formData.wechat_mini_encrypt_type">
                                <el-radio value="1">明文模式</el-radio>
                                <el-radio value="2">兼容模式</el-radio>
                                <el-radio value="3">安全模式</el-radio>
                            </el-radio-group>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 域名信息（只读） -->
            <div class="set-card">
                <div class="set-card-head">
                    <h3>域名信息</h3>
                    <span class="dsc"
                        >请将以下域名配置到微信小程序后台「开发管理 > 开发设置 > 服务器域名」</span
                    >
                </div>
                <div class="set-card-body">
                    <!-- request合法域名 -->
                    <div class="set-item">
                        <div>
                            <div class="set-item-label">request 合法域名</div>
                        </div>
                        <div class="set-item-ctrl">
                            <el-input :model-value="siteUrlHttps" disabled style="width: 400px">
                                <template #append>
                                    <el-button @click="copyText(siteUrlHttps)">复制</el-button>
                                </template>
                            </el-input>
                        </div>
                    </div>

                    <!-- socket合法域名 -->
                    <div class="set-item">
                        <div>
                            <div class="set-item-label">socket 合法域名</div>
                        </div>
                        <div class="set-item-ctrl">
                            <el-input :model-value="siteUrlWss" disabled style="width: 400px">
                                <template #append>
                                    <el-button @click="copyText(siteUrlWss)">复制</el-button>
                                </template>
                            </el-input>
                        </div>
                    </div>

                    <!-- uploadFile合法域名 -->
                    <div class="set-item">
                        <div>
                            <div class="set-item-label">uploadFile 合法域名</div>
                        </div>
                        <div class="set-item-ctrl">
                            <el-input :model-value="siteUrlHttps" disabled style="width: 400px">
                                <template #append>
                                    <el-button @click="copyText(siteUrlHttps)">复制</el-button>
                                </template>
                            </el-input>
                        </div>
                    </div>

                    <!-- downloadFile合法域名 -->
                    <div class="set-item">
                        <div>
                            <div class="set-item-label">downloadFile 合法域名</div>
                        </div>
                        <div class="set-item-ctrl">
                            <el-input :model-value="siteUrlHttps" disabled style="width: 400px">
                                <template #append>
                                    <el-button @click="copyText(siteUrlHttps)">复制</el-button>
                                </template>
                            </el-input>
                        </div>
                    </div>

                    <!-- 业务域名 -->
                    <div class="set-item">
                        <div>
                            <div class="set-item-label">业务域名</div>
                        </div>
                        <div class="set-item-ctrl">
                            <el-input :model-value="siteDomain" disabled style="width: 400px">
                                <template #append>
                                    <el-button @click="copyText(siteDomain)">复制</el-button>
                                </template>
                            </el-input>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts" name="ChannelMiniAppConfig">
import { Plus } from '@element-plus/icons-vue'
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
    // Upload component styles
    .qrcode-upload {
        .qrcode-uploader {
            :deep(.el-upload) {
                border: 1px dashed var(--el-border-color);
                border-radius: 8px;
                cursor: pointer;
                overflow: hidden;
                transition: border-color 0.2s;

                &:hover {
                    border-color: var(--brand-6, var(--el-color-primary));
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
            color: var(--ink-3, var(--el-text-color-placeholder));
            font-size: 12px;
        }
    }

    // Server notice inside set-item
    .set-item--alert {
        background: var(--el-fill-color-light);
        border-radius: 8px;

        .server-notice {
            width: 100%;
            padding: 2px 0;

            .server-notice-label {
                display: block;
                font-size: 13px;
                font-weight: 500;
                color: var(--ink-1, var(--el-text-color-primary));
                margin-bottom: 6px;
            }

            .server-url {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 13px;
                color: var(--ink-2, var(--el-text-color-regular));

                code {
                    padding: 2px 6px;
                    background: var(--el-fill-color);
                    border-radius: 3px;
                    font-family: monospace;
                }
            }
        }
    }
}
</style>
