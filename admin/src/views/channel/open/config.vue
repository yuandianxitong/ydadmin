<template>
    <div class="channel-config">
        <!-- 开放平台配置 -->
        <el-card shadow="never" class="config-card">
            <template #header>
                <div class="card-header">
                    <el-icon><Key /></el-icon>
                    <span>微信开放平台</span>
                </div>
            </template>
            <el-alert type="info" :closable="false" show-icon style="margin-bottom: 16px">
                <template #title>用于 PC 端微信扫码登录，需在<el-link type="primary" href="https://open.weixin.qq.com" target="_blank">微信开放平台</el-link>创建「网站应用」并通过审核</template>
            </el-alert>
            <el-form
                :model="formData"
                label-width="150px"
                label-position="left"
                style="max-width: 600px"
            >
                <el-form-item label="AppID">
                    <el-input
                        v-model="formData.wechat_open_app_id"
                        placeholder="请输入微信开放平台网站应用AppID"
                    />
                </el-form-item>
                <el-form-item label="AppSecret">
                    <el-input
                        v-model="formData.wechat_open_app_secret"
                        type="password"
                        show-password
                        placeholder="请输入微信开放平台网站应用AppSecret"
                    />
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 回调域名信息 -->
        <el-card shadow="never" class="config-card">
            <template #header>
                <div class="card-header">
                    <el-icon><Link /></el-icon>
                    <span>回调配置</span>
                </div>
            </template>
            <el-alert type="warning" :closable="false" show-icon style="margin-bottom: 16px">
                <template #title>请将以下回调域名配置到微信开放平台「网站应用 > 授权回调域」</template>
            </el-alert>
            <el-form label-width="150px" label-position="left" style="max-width: 600px">
                <el-form-item label="授权回调域">
                    <el-input :model-value="callbackDomain" disabled>
                        <template #append>
                            <el-button @click="copyText(callbackDomain)">复制</el-button>
                        </template>
                    </el-input>
                    <div class="form-tip">不包含 http:// 或 https:// 前缀</div>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 保存按钮 -->
        <div class="save-bar">
            <el-button type="primary" :loading="loading" @click="handleSave">保存配置</el-button>
        </div>
    </div>
</template>

<script setup lang="ts" name="ChannelOpenConfig">
import { Key, Link } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'

import { batchUpdateConfigs, getConfigsByGroup } from '@/api/system/config'
import useAppStore from '@/store/modules/app.store'

const loading = ref(false)
const appStore = useAppStore()

const formData = reactive<Record<string, string>>({
    wechat_open_app_id: '',
    wechat_open_app_secret: '',
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
        ElMessage.success('复制成功')
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
        ElMessage.error('获取配置失败')
    } finally {
        loading.value = false
    }
})

const handleSave = async () => {
    try {
        loading.value = true
        const configs = Object.entries(formData).map(([key, value]) => ({
            config_key: key,
            config_value: value,
        }))
        await batchUpdateConfigs(configs)
        ElMessage.success('保存成功')
    } catch {
        ElMessage.error('保存失败')
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
    .form-tip {
        font-size: 12px;
        color: var(--el-text-color-placeholder);
        margin-top: 4px;
    }
    .save-bar {
        padding: 12px 0;
        display: flex;
        justify-content: flex-start;
    }
}
</style>
