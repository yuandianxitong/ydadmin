<template>
    <div class="miniapp-config">
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('channel.miniAppConfig') }}</span>
                </div>
            </template>

            <el-form
                ref="formRef"
                :model="formData"
                label-width="140px"
                label-position="left"
                style="max-width: 600px"
            >
                <el-form-item label="AppID">
                    <el-input v-model="formData.wechat_mini_app_id" :placeholder="$t('channel.miniAppIdPlaceholder')" />
                </el-form-item>
                <el-form-item label="AppSecret">
                    <el-input
                        v-model="formData.wechat_mini_app_secret"
                        type="password"
                        show-password
                        :placeholder="$t('channel.miniAppSecretPlaceholder')"
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

<script setup lang="ts" name="WechatMiniAppConfig">
import { ElMessage } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { batchUpdateConfigs, getConfigsByGroup } from '@/api/system/config'

const { t } = useI18n()
const loading = ref(false)

const formData = reactive<Record<string, string>>({
    wechat_mini_app_id: '',
    wechat_mini_app_secret: ''
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
.miniapp-config {
    .card-header {
        font-size: 16px;
        font-weight: 600;
    }
}
</style>
