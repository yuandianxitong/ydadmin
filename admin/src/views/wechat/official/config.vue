<template>
    <div class="wechat-config">
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('channel.officialAccountConfig') }}</span>
                </div>
            </template>

            <el-alert type="info" :closable="false" show-icon class="mb-16">
                <template #title>
                    {{ $t('channel.serverConfigAlert') }}
                </template>
                <template #default>
                    <div class="server-info">
                        <div>
                            {{ $t('channel.serverUrl') }}<el-text type="primary" tag="code">{{
                                serverUrl
                            }}</el-text>
                        </div>
                        <div>{{ $t('channel.tokenAndAesKeyTip') }}</div>
                    </div>
                </template>
            </el-alert>

            <el-form
                ref="formRef"
                :model="formData"
                label-width="160px"
                label-position="left"
                style="max-width: 600px"
            >
                <el-form-item label="AppID">
                    <el-input
                        v-model="formData.wechat_official_app_id"
                        :placeholder="$t('channel.simpleAppIdPlaceholder')"
                    />
                </el-form-item>
                <el-form-item label="AppSecret">
                    <el-input
                        v-model="formData.wechat_official_app_secret"
                        type="password"
                        show-password
                        :placeholder="$t('channel.simpleAppSecretPlaceholder')"
                    />
                </el-form-item>
                <el-form-item label="Token">
                    <el-input
                        v-model="formData.wechat_official_token"
                        :placeholder="$t('channel.simpleTokenPlaceholder')"
                    />
                </el-form-item>
                <el-form-item label="EncodingAESKey">
                    <el-input
                        v-model="formData.wechat_official_aes_key"
                        :placeholder="$t('channel.simpleAesKeyPlaceholder')"
                    />
                </el-form-item>

                <el-form-item>
                    <el-button type="primary" :loading="loading" @click="handleSave"
                        >{{ $t('channel.saveConfig') }}</el-button
                    >
                </el-form-item>
            </el-form>
        </el-card>
    </div>
</template>

<script setup lang="ts" name="WechatOfficialConfig">
import { ElMessage } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { batchUpdateConfigs, getConfigsByGroup } from '@/api/system/config'
import useAppStore from '@/store/modules/app.store'

const { t } = useI18n()
const loading = ref(false)
const appStore = useAppStore()

const formData = reactive<Record<string, string>>({
    wechat_official_app_id: '',
    wechat_official_app_secret: '',
    wechat_official_token: '',
    wechat_official_aes_key: ''
})

const serverUrl = computed(() => {
    const siteUrl = appStore.config?.site_url || window.location.origin
    return `${siteUrl}/api/wechat/serve`
})

onMounted(async () => {
    try {
        loading.value = true
        const res = await getConfigsByGroup('wechat')
        const configs = res.data || []
        configs.forEach((c: any) => {
            if (c.config_key in formData) {
                formData[c.config_key] = c.config_value || ''
            }
        })
    } catch {
        ElMessage.error(t('channel.fetchConfigFailed'))
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
        ElMessage.success(t('channel.saveSuccess'))
    } catch {
        ElMessage.error(t('channel.saveFailed'))
    } finally {
        loading.value = false
    }
}
</script>

<style lang="scss" scoped>
.wechat-config {
    .mb-16 {
        margin-bottom: 16px;
    }
    .server-info {
        line-height: 1.8;
        code {
            padding: 2px 6px;
            background: var(--el-fill-color-light);
            border-radius: 3px;
        }
    }
    .card-header {
        font-size: 16px;
        font-weight: 600;
    }
}
</style>
