<template>
    <div class="channel-config">
        <!-- 页头 -->
        <div class="page-head">
            <div>
                <div class="page-title">{{ t('channel.open.wechatOpenTitle') }}</div>
                <div class="page-desc">{{ t('channel.open.wechatOpenAlert') }}</div>
            </div>
            <div class="page-actions">
                <el-button :loading="loading" type="primary" @click="handleSave">{{
                    t('channel.open.saveBtn')
                }}</el-button>
            </div>
        </div>

        <div class="set-sections">
            <!-- 开放平台配置 -->
            <div class="set-card">
                <div class="set-card-head">
                    <h3>{{ t('channel.open.wechatOpenTitle') }}</h3>
                    <span class="dsc">
                        {{ t('channel.open.wechatOpenAlert') }}
                        <el-link type="primary" href="https://open.weixin.qq.com" target="_blank">{{
                            t('channel.open.wechatOpenTitle')
                        }}</el-link>
                    </span>
                </div>
                <div class="set-card-body">
                    <div class="set-item">
                        <div>
                            <div class="set-item-label">AppID</div>
                        </div>
                        <div class="set-item-ctrl">
                            <el-input
                                style="width: 320px"
                                v-model="formData.wechat_open_app_id"
                                :placeholder="t('channel.open.appIdPlaceholder')"
                            />
                        </div>
                    </div>
                    <div class="set-item">
                        <div>
                            <div class="set-item-label">AppSecret</div>
                        </div>
                        <div class="set-item-ctrl">
                            <el-input
                                style="width: 320px"
                                v-model="formData.wechat_open_app_secret"
                                type="password"
                                show-password
                                :placeholder="t('channel.open.appSecretPlaceholder')"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- 回调域名信息 -->
            <div class="set-card">
                <div class="set-card-head">
                    <h3>{{ t('channel.open.callbackTitle') }}</h3>
                    <span class="dsc">{{ t('channel.open.callbackAlert') }}</span>
                </div>
                <div class="set-card-body">
                    <div class="set-item">
                        <div>
                            <div class="set-item-label">{{ t('channel.open.callbackDomain') }}</div>
                        </div>
                        <div class="set-item-ctrl">
                            <el-input :model-value="callbackDomain" style="width: 320px" disabled>
                                <template #append>
                                    <el-button @click="copyText(callbackDomain)">{{
                                        t('channel.open.copyBtn')
                                    }}</el-button>
                                </template>
                            </el-input>
                            <div class="set-item-tip">
                                {{ t('channel.open.callbackDomainTip') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts" name="ChannelOpenConfig">

import { ElMessage } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { batchUpdateConfigs, getConfigsByGroup } from '@/api/system/config'
import useAppStore from '@/store/modules/app.store'

const { t } = useI18n()
const loading = ref(false)
const appStore = useAppStore()

const formData = reactive<Record<string, string>>({
    wechat_open_app_id: '',
    wechat_open_app_secret: ''
})

const callbackDomain = computed(() => {
    const siteUrl = appStore.config?.site_url || window.location.origin
    try {
        return new URL(siteUrl).hostname
    } catch {
        return siteUrl.replace(/^https?:\/\//, '').replace(/\/.*$/, '')
    }
})

const copyText = (text: string) => {
    navigator.clipboard.writeText(text).then(() => {
        ElMessage.success(t('channel.open.copySuccess'))
    })
}

onMounted(async () => {
    try {
        loading.value = true
        const res = await getConfigsByGroup('wechat_open')
        const configs = res.data || []
        configs.forEach((c: any) => {
            if (c.config_key in formData) {
                formData[c.config_key] = c.config_value || ''
            }
        })
    } catch {
        ElMessage.error(t('channel.open.loadFailed'))
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
        ElMessage.success(t('channel.open.saveSuccess'))
    } catch {
        ElMessage.error(t('channel.open.saveFailed'))
    } finally {
        loading.value = false
    }
}
</script>

<style lang="scss" scoped>
.set-item-tip {
    font-size: 12px;
    color: var(--ink-400);
    margin-top: 6px;
}
</style>
