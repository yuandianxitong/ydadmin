<template>
    <div>
        <!-- 页头 -->
        <div class="page-head">
            <div>
                <div class="page-title">公众号配置</div>
                <div class="page-desc">微信公众号基础信息与接口凭证配置</div>
            </div>
            <div class="page-actions">
                <el-button @click="handleReset">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="loading" @click="handleSave">{{
                    $t('channel.saveConfig')
                }}</el-button>
            </div>
        </div>

        <el-form :model="formData">
            <div class="set-sections">
                <!-- 公众号信息 -->
                <div class="set-card">
                    <div class="set-card-head">
                        <h3>{{ $t('channel.official.title') }}</h3>
                        <span class="dsc">公众号账号身份信息</span>
                    </div>
                    <div class="set-card-body">
                        <!-- 公众号名称 -->
                        <div class="set-item">
                            <div>
                                <div class="set-item-label">{{ $t('channel.official.name') }}</div>
                                <div class="set-item-desc">显示在文章底部与推送通知</div>
                            </div>
                            <div class="set-item-ctrl">
                                <el-input
                                    style="width: 320px"
                                    v-model="formData.wechat_official_name"
                                    :placeholder="$t('channel.official.namePlaceholder')"
                                />
                            </div>
                        </div>

                        <!-- 原始 ID -->
                        <div class="set-item">
                            <div>
                                <div class="set-item-label">
                                    {{ $t('channel.official.originalId') }}
                                </div>
                                <div class="set-item-desc">公众号唯一标识，格式为 gh_xxxxxxxx</div>
                            </div>
                            <div class="set-item-ctrl">
                                <el-input
                                    style="width: 320px"
                                    v-model="formData.wechat_official_original_id"
                                    :placeholder="$t('channel.official.originalIdPlaceholder')"
                                />
                            </div>
                        </div>

                        <!-- 公众号二维码 -->
                        <div class="set-item">
                            <div>
                                <div class="set-item-label">
                                    {{ $t('channel.official.qrcode') }}
                                </div>
                                <div class="set-item-desc">
                                    {{ $t('channel.official.qrcodeTip') }}
                                </div>
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
                                            (res: any) =>
                                                onUploadSuccess(res, 'wechat_official_qrcode')
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
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 开发信息 -->
                <div class="set-card">
                    <div class="set-card-head">
                        <h3>{{ $t('channel.official.devInfo') }}</h3>
                        <span class="dsc">用于调用微信公众号开放接口的凭证</span>
                    </div>
                    <div class="set-card-body">
                        <!-- AppID -->
                        <div class="set-item">
                            <div>
                                <div class="set-item-label">AppID</div>
                                <div class="set-item-desc">公众号开发者 ID</div>
                            </div>
                            <div class="set-item-ctrl">
                                <el-input
                                    style="width: 320px"
                                    v-model="formData.wechat_official_app_id"
                                    :placeholder="$t('channel.official.appIdPlaceholder')"
                                />
                            </div>
                        </div>

                        <!-- AppSecret -->
                        <div class="set-item">
                            <div>
                                <div class="set-item-label">AppSecret</div>
                                <div class="set-item-desc">公众号开发者密钥，请妥善保管</div>
                            </div>
                            <div class="set-item-ctrl">
                                <el-input
                                    style="width: 320px"
                                    v-model="formData.wechat_official_app_secret"
                                    type="password"
                                    show-password
                                    :placeholder="$t('channel.official.appSecretPlaceholder')"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 服务器配置 -->
                <div class="set-card">
                    <div class="set-card-head">
                        <h3>{{ $t('channel.official.serverConfig') }}</h3>
                        <span class="dsc">配置微信公众号消息接收服务器</span>
                    </div>
                    <div class="set-card-body">
                        <!-- 服务器地址提示 -->
                        <el-alert type="info" :closable="false" show-icon class="server-alert">
                            <template #title>{{
                                $t('channel.official.serverAlertTitle')
                            }}</template>
                            <template #default>
                                <div class="server-url">
                                    {{ $t('channel.official.serverUrlLabel')
                                    }}<el-text type="primary" tag="code">{{ serverUrl }}</el-text>
                                    <el-button type="primary" text size="small" @click="copyUrl">{{
                                        $t('common.copy')
                                    }}</el-button>
                                </div>
                            </template>
                        </el-alert>

                        <!-- Token -->
                        <div class="set-item">
                            <div>
                                <div class="set-item-label">Token</div>
                                <div class="set-item-desc">用于验证消息真实性的令牌</div>
                            </div>
                            <div class="set-item-ctrl">
                                <el-input
                                    style="width: 320px"
                                    v-model="formData.wechat_official_token"
                                    :placeholder="$t('channel.official.tokenPlaceholder')"
                                />
                            </div>
                        </div>

                        <!-- EncodingAESKey -->
                        <div class="set-item">
                            <div>
                                <div class="set-item-label">EncodingAESKey</div>
                                <div class="set-item-desc">
                                    {{ $t('channel.official.aesKeyTip') }}
                                </div>
                            </div>
                            <div class="set-item-ctrl">
                                <el-input
                                    style="width: 320px"
                                    v-model="formData.wechat_official_aes_key"
                                    :placeholder="$t('channel.official.aesKeyPlaceholder')"
                                />
                            </div>
                        </div>

                        <!-- 消息加密方式 -->
                        <div class="set-item">
                            <div>
                                <div class="set-item-label">消息加密方式</div>
                                <div class="set-item-desc">
                                    需与微信公众号后台「开发 > 基本配置」中的加密方式保持一致
                                </div>
                            </div>
                            <div class="set-item-ctrl">
                                <el-radio-group v-model="formData.wechat_official_encrypt_type">
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
                        <span class="dsc">需配置到微信公众号后台对应位置</span>
                    </div>
                    <div class="set-card-body">
                        <el-alert type="warning" :closable="false" show-icon class="domain-alert">
                            <template #title>请将以下域名配置到微信公众号后台对应位置</template>
                        </el-alert>

                        <!-- 业务域名 -->
                        <div class="set-item">
                            <div>
                                <div class="set-item-label">业务域名</div>
                                <div class="set-item-desc">在此域名下可使用微信 JS-SDK</div>
                            </div>
                            <div class="set-item-ctrl">
                                <el-input :model-value="siteDomain" disabled style="width: 320px">
                                    <template #append>
                                        <el-button @click="copyText(siteDomain)">复制</el-button>
                                    </template>
                                </el-input>
                            </div>
                        </div>

                        <!-- JS接口安全域名 -->
                        <div class="set-item">
                            <div>
                                <div class="set-item-label">JS接口安全域名</div>
                                <div class="set-item-desc">可调用微信 JS-SDK 接口的域名</div>
                            </div>
                            <div class="set-item-ctrl">
                                <el-input :model-value="siteDomain" disabled style="width: 320px">
                                    <template #append>
                                        <el-button @click="copyText(siteDomain)">复制</el-button>
                                    </template>
                                </el-input>
                            </div>
                        </div>

                        <!-- 网页授权域名 -->
                        <div class="set-item">
                            <div>
                                <div class="set-item-label">网页授权域名</div>
                                <div class="set-item-desc">用于网页授权获取用户信息的域名</div>
                            </div>
                            <div class="set-item-ctrl">
                                <el-input :model-value="siteDomain" disabled style="width: 320px">
                                    <template #append>
                                        <el-button @click="copyText(siteDomain)">复制</el-button>
                                    </template>
                                </el-input>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </el-form>
    </div>
</template>

<script setup lang="ts" name="ChannelOfficialConfig">
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
    wechat_official_name: '',
    wechat_official_original_id: '',
    wechat_official_qrcode: '',
    wechat_official_app_id: '',
    wechat_official_app_secret: '',
    wechat_official_token: '',
    wechat_official_aes_key: '',
    wechat_official_encrypt_type: '1'
})

const serverUrl = computed(() => {
    const siteUrl = appStore.config?.site_url || window.location.origin
    return `${siteUrl}/api/wechat/serve`
})

const siteDomain = computed(() => {
    const siteUrl = appStore.config?.site_url || window.location.origin
    try {
        return new URL(siteUrl).hostname
    } catch {
        return siteUrl.replace(/^https?:\/\//, '').replace(/\/.*$/, '')
    }
})

const copyText = (text: string) => {
    navigator.clipboard.writeText(text).then(() => {
        ElMessage.success(t('message.copySuccess'))
    })
}

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

const originalFormData = reactive<Record<string, any>>({})
const handleReset = () => Object.assign(formData, originalFormData)

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
        Object.assign(originalFormData, formData)
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
.server-alert {
    margin-bottom: 16px;
}

.domain-alert {
    margin-bottom: 16px;
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
}
</style>
