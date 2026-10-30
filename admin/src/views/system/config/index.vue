<template>
    <div class="system-config">
        <div class="page-head">
            <div>
                <div class="page-title">{{ $t('proPage.configTitle') }}</div>
                <div class="page-desc">{{ $t('proPage.configDescription') }}</div>
            </div>
            <div class="page-actions">
                <el-button @click="handleReset">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="loading" @click="handleSave">{{
                    $t('config.saveConfig')
                }}</el-button>
            </div>
        </div>
        <div class="set-split">
            <nav class="set-nav" :aria-label="$t('proPage.configTitle')">
                <button
                    v-for="(label, key) in configGroups"
                    :key="key"
                    type="button"
                    class="set-nav-item"
                    :class="{ on: activeTab === key }"
                    @click="switchTab(key)"
                >
                    <i class="ic" :class="navIconClass[key] || 'i-svg:settings'" />
                    <span>{{ label }}</span>
                </button>
            </nav>
            <div class="set-sections">
                <div class="set-card" v-loading="loading">
                    <div class="set-card-head">
                        <h3>{{ configGroups[activeTab] || activeTab }}</h3>
                    </div>
                    <div class="set-card-body">
                        <el-form
                            ref="formRef"
                            :model="formData"
                            :rules="rules"
                            label-width="196px"
                            label-position="left"
                            class="pro-config-form"
                        >
                            <el-form-item
                                v-for="config in visibleConfigs"
                                :key="config.id"
                                :prop="config.config_key"
                                class="config-item"
                            >
                                <template #label>
                                    <span class="set-item-label">{{ getItemName(config) }}</span>
                                    <span
                                        v-if="config.config_desc && config.config_type !== 'file'"
                                        class="set-item-desc"
                                        >{{ getItemDesc(config) }}</span
                                    >
                                </template>
                                <div class="set-item-ctrl">
                                    <!-- select 下拉选择 -->
                                    <template v-if="config.config_type === 'select'">
                                        <el-select
                                            v-model="formData[config.config_key]"
                                            :placeholder="$t('config.selectPlaceholder')"
                                            style="width: 400px"
                                        >
                                            <el-option
                                                v-for="(optLabel, optValue) in parseOptions(
                                                    config.config_options
                                                )"
                                                :key="optValue"
                                                :label="
                                                    getOptionLabel(
                                                        config.config_key,
                                                        String(optValue),
                                                        String(optLabel)
                                                    )
                                                "
                                                :value="optValue"
                                            />
                                        </el-select>
                                    </template>

                                    <!-- boolean 开关 -->
                                    <template v-else-if="config.config_type === 'boolean'">
                                        <el-switch
                                            v-model="formData[config.config_key]"
                                            :active-value="1"
                                            :inactive-value="0"
                                        />
                                    </template>

                                    <!-- number 数字 -->
                                    <template v-else-if="config.config_type === 'number'">
                                        <el-input-number
                                            v-model="formData[config.config_key]"
                                            :min="0"
                                            style="width: 200px"
                                        />
                                    </template>

                                    <!-- file 文件上传 -->
                                    <template v-else-if="config.config_type === 'file'">
                                        <div>
                                            <el-upload
                                                class="image-uploader"
                                                :show-file-list="false"
                                                :on-success="
                                                    (response: any) =>
                                                        handleUploadSuccess(
                                                            response,
                                                            config.config_key
                                                        )
                                                "
                                                :before-upload="beforeUpload"
                                                action="/adminapi/upload/image"
                                                :headers="uploadHeaders"
                                            >
                                                <img
                                                    v-if="formData[config.config_key]"
                                                    :src="getImageUrl(formData[config.config_key])"
                                                    class="uploaded-image"
                                                    :alt="getItemName(config)"
                                                />
                                                <el-icon v-else class="image-uploader-icon">
                                                    <Plus />
                                                </el-icon>
                                            </el-upload>
                                            <div class="upload-tip">
                                                {{ getItemDesc(config) }}，{{
                                                    $t('config.fileTip')
                                                }}
                                            </div>
                                        </div>
                                    </template>

                                    <!-- string 默认文本 -->
                                    <template v-else>
                                        <el-input
                                            v-model="formData[config.config_key]"
                                            :type="
                                                config.config_key.includes('password') ||
                                                config.config_key.includes('secret') ||
                                                config.config_key.includes('private_key')
                                                    ? 'password'
                                                    : 'text'
                                            "
                                            :show-password="
                                                config.config_key.includes('password') ||
                                                config.config_key.includes('secret') ||
                                                config.config_key.includes('private_key')
                                            "
                                            :placeholder="getItemDesc(config)"
                                            style="width: 400px"
                                        />
                                    </template>
                                </div>
                                <div v-if="notifyAddress(config)" class="config-notify-address">
                                    {{ $t('config.notifyAddress', { url: notifyAddress(config) }) }}
                                </div>
                            </el-form-item>
                        </el-form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts" name="SystemConfig">
import { Plus } from '@element-plus/icons-vue'
import type { FormInstance, FormRules } from 'element-plus'
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { batchUpdateConfigs, getConfigGroups, getConfigsByGroup } from '@/api/system/config'
import useAppStore from '@/store/modules/app.store'
import { getToken } from '@/utils/auth'

const { t, te } = useI18n()

// 获取配置项翻译名称（优先 i18n，fallback 到数据库值）
const getItemName = (config: any): string => {
    const key = `config.items.${config.config_key}`
    return te(key) ? t(key) : config.config_name
}

// 获取配置项翻译描述
const getItemDesc = (config: any): string => {
    const key = `config.descs.${config.config_key}`
    return te(key) ? t(key) : config.config_desc
}

// 获取下拉选项翻译
const getOptionLabel = (configKey: string, optValue: string, fallback: string): string => {
    const key = `config.options.${configKey}.${optValue}`
    return te(key) ? t(key) : fallback
}

const NOTIFY_URL_DEFAULTS: Record<string, string> = {
    pay_alipay_notify_url: '/api/payment/notify/alipay',
    pay_wechat_notify_url: '/api/payment/notify/wechat'
}

const navIconClass: Record<string, string> = {
    basic: 'i-svg:settings', email: 'i-svg:mail', sms: 'i-svg:smartphone',
    storage: 'i-svg:server', payment: 'i-svg:wallet', wechat_official: 'i-svg:bot',
    wechat_open: 'i-svg:globe', wechat_mini: 'i-svg:message-square'
}

const switchTab = async (key: string) => {
    activeTab.value = key
    await handleTabChange(key)
}

const activeTab = ref('basic')
const loading = ref(false)
const formRef = ref<FormInstance>()
const appStore = useAppStore()

const configGroups = ref<Record<string, string>>({})
const configsData = reactive<Record<string, any[]>>({})
const formData = reactive<Record<string, any>>({})
const originalFormData = reactive<Record<string, any>>({})

/** 相对路径拼上网站地址，给管理员看到可直接复制的完整通知地址。完整 https 地址不再重复提示。 */
const notifyAddress = (config: { config_key: string }): string => {
    const fallback = NOTIFY_URL_DEFAULTS[config.config_key]
    if (!fallback) return ''

    const raw = String(formData[config.config_key] ?? '').trim()
    if (/^https?:\/\//i.test(raw)) return ''

    const path = raw.startsWith('/') ? raw : fallback
    const site = String(appStore.config.site_url || '').replace(/\/$/, '')
    const origin = site || (typeof window !== 'undefined' ? window.location.origin : '')

    return origin ? `${origin}${path}` : path
}

// 上传相关配置（computed 确保 Token 刷新后仍有效）
const uploadHeaders = computed(() => ({
    Authorization: `Bearer ${getToken()}`
}))

const rules: FormRules = {
    site_name: [
        { required: true, message: t('config.validate.siteNameRequired'), trigger: 'blur' }
    ],
    site_url: [
        { required: true, message: t('config.validate.siteUrlRequired'), trigger: 'blur' },
        { type: 'url', message: t('config.validate.siteUrlFormat'), trigger: 'blur' }
    ],
    site_phone: [
        { pattern: /^1[3-9]\d{9}$/, message: t('config.validate.mobileFormat'), trigger: 'blur' }
    ]
}

const currentConfigs = computed(() => {
    return configsData[activeTab.value] || []
})

/**
 * 解析 config_options JSON 为对象
 */
const parseOptions = (options: any): Record<string, string> => {
    if (!options) return {}
    if (typeof options === 'string') {
        try {
            return JSON.parse(options)
        } catch {
            return {}
        }
    }
    return options
}

/**
 * 判断配置项是否满足 depends 条件
 */
const checkDepends = (config: any): boolean => {
    if (!config.config_depends) return true

    let depends = config.config_depends
    if (typeof depends === 'string') {
        try {
            depends = JSON.parse(depends)
        } catch {
            return true
        }
    }

    const field = depends.field
    const expectedValue = depends.value
    const currentValue = String(formData[field] ?? '')

    if (Array.isArray(expectedValue)) {
        return expectedValue.map(String).includes(currentValue)
    }
    return currentValue === String(expectedValue)
}

/**
 * 过滤后的可见配置列表（根据 depends 条件）
 */
const visibleConfigs = computed(() => {
    return currentConfigs.value.filter(checkDepends)
})

// 获取图片完整URL
const getImageUrl = (url: string) => {
    return appStore.getImageUrl(url)
}

onMounted(async () => {
    await loadConfigGroups()
    await loadConfigs(activeTab.value)
})

const loadConfigGroups = async () => {
    try {
        const res = await getConfigGroups()
        configGroups.value = res.data || {}
    } catch (error) {
        console.error(t('message.fetchFailed'), error)
    }
}

const loadConfigs = async (group: string) => {
    try {
        loading.value = true
        const res = await getConfigsByGroup(group)
        const configs = res.data || []

        configsData[group] = configs

        // 重置表单数据
        const newFormData: Record<string, any> = {}
        const newOriginalFormData: Record<string, any> = {}

        configs.forEach((config: any) => {
            let value = config.config_value

            // 根据类型转换值
            if (config.config_type === 'boolean') {
                value = Number(value)
            } else if (
                NOTIFY_URL_DEFAULTS[config.config_key] &&
                String(value ?? '').trim() === ''
            ) {
                value = NOTIFY_URL_DEFAULTS[config.config_key]
            } else if (config.config_type === 'number') {
                value = Number(value)
            } else if (config.config_type === 'json') {
                try {
                    value = JSON.parse(value)
                } catch {
                    value = {}
                }
            }

            newFormData[config.config_key] = value
            newOriginalFormData[config.config_key] = value
        })

        Object.assign(formData, newFormData)
        Object.assign(originalFormData, newOriginalFormData)
    } catch (error) {
        console.error(t('message.fetchFailed'), error)
        ElMessage.error(t('message.fetchFailed'))
    } finally {
        loading.value = false
    }
}

const handleTabChange = async (tabName: string | number) => {
    const group = String(tabName)
    if (!configsData[group]) {
        await loadConfigs(group)
    }
}

const handleUploadSuccess = (response: Record<string, any>, configKey: string) => {
    if (response.code === 200 || response.code === 0) {
        // 存储相对路径到数据库，用于后续配置保存
        formData[configKey] = response.data?.url || response.data?.path || response.data
        ElMessage.success(t('message.uploadSuccess'))
    } else {
        ElMessage.error(response.message || t('message.uploadFailed'))
    }
}

const beforeUpload = (file: File) => {
    // 检查文件类型
    const isImage = file.type.startsWith('image/')
    if (!isImage) {
        ElMessage.error(t('message.imageOnly'))
        return false
    }

    // 检查文件大小
    const isValidSize = file.size / 1024 / 1024 < 2
    if (!isValidSize) {
        ElMessage.error(t('message.fileSizeLimit'))
        return false
    }

    return true
}

const handleSave = async () => {
    try {
        loading.value = true

        // 准备更新数据 - 包含所有当前分组的配置（含隐藏的）
        const updateConfigs = currentConfigs.value.map((config) => {
            let value = formData[config.config_key]

            // 根据类型处理值
            if (config.config_type === 'json') {
                value = JSON.stringify(value)
            } else {
                value = String(value)
            }

            return {
                config_key: config.config_key,
                config_value: value
            }
        })

        await batchUpdateConfigs(updateConfigs)

        // 更新原始数据
        Object.assign(originalFormData, formData)

        ElMessage.success(t('message.configSaveSuccess'))
    } catch (error) {
        console.error(t('message.configSaveFailed'), error)
        if (error instanceof Error) {
            ElMessage.error(t('message.configSaveFailed') + ': ' + error.message)
        } else {
            ElMessage.error(t('message.configSaveFailed'))
        }
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    ElMessageBox.confirm(t('message.configResetConfirm'), t('common.tip'), {
        confirmButtonText: t('common.confirm'),
        cancelButtonText: t('common.cancel'),
        type: 'warning'
    }).then(() => {
        Object.assign(formData, originalFormData)
        ElMessage.success(t('message.configResetDone'))
    })
}
</script>

<style scoped lang="scss">
.system-config {
    // 图片上传器样式
    .image-uploader {
        :deep(.el-upload) {
            border: 1px dashed var(--ink-200);
            border-radius: 6px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: border-color 0.2s;
            width: 120px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;

            &:hover {
                border-color: var(--brand-500);
            }
        }

        .uploaded-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            display: block;
        }

        .image-uploader-icon {
            font-size: 28px;
            color: var(--ink-400);
        }
    }

    .upload-tip {
        font-size: 12px;
        color: var(--ink-500);
        margin-top: 8px;
        line-height: 1.4;
    }
}

.set-nav-item {
    width: 100%;
    border: 0;
    background: transparent;
    text-align: left;
    font-family: inherit;
    line-height: 1.5;

    &.on {
        background: var(--brand-50);
    }
}
.pro-config-form {
    :deep(.el-form-item.is-required > .el-form-item__label::before) {
        display: none;
    }
    .config-item {
        margin-bottom: 0;
        padding: 14px 0;
        border-bottom: 1px solid var(--ink-100);
        align-items: center;
        &:last-child {
            border-bottom: 0;
        }
    }
    :deep(.el-form-item__label) {
        flex-direction: column;
        align-items: flex-start;
        justify-content: center;
        height: auto;
        line-height: 1.5;
        padding-right: 16px;
    }
    .set-item-ctrl {
        width: 100%;
    }
    .config-notify-address {
        color: var(--ink-500);
        font-size: 12px;
        overflow-wrap: anywhere;
    }
}
@media (max-width: 768px) {
    .set-nav-item {
        width: auto;
    }
    .pro-config-form .config-item {
        display: block;
    }
    .pro-config-form :deep(.el-form-item__label) {
        width: 100% !important;
        margin-bottom: 8px;
    }
    .pro-config-form :deep(.el-form-item__content) {
        margin-left: 0 !important;
    }
}
</style>
