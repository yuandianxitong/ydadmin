<template>
    <div class="login-page">
        <!-- 左侧品牌区 -->
        <div class="login-brand">
            <div class="brand-content">
                <div class="brand-image">
                    <img src="@/assets/images/login_image.png" alt="元点Admin" />
                </div>
                <h1 class="text-4xl font-bold leading-tight mb-4">{{ $t('login.title') }}</h1>
                <p class="text-lg text-gray-200">{{ $t('login.subtitle') }}</p>
            </div>
            <div class="brand-footer">
                <span>&copy; {{ new Date().getFullYear() }} Dev007.cn</span>
            </div>
        </div>

        <!-- 右侧登录区 -->
        <div class="login-main">
            <div class="login-card">
                <!-- 头部 -->
                <div class="login-header">
                    <h2 class="login-title">{{ $t('login.welcome') }}</h2>
                    <p class="login-desc">{{ $t('login.enterCredentials') }}</p>
                </div>

                <!-- 表单 -->
                <el-form
                    ref="loginFormRef"
                    :model="loginForm"
                    :rules="loginRules"
                    size="large"
                    class="login-form"
                    @submit.prevent="handleLogin"
                >
                    <el-form-item prop="username">
                        <el-input
                            v-model="loginForm.username"
                            :placeholder="$t('login.message.username.required')"
                            :disabled="loading"
                            :prefix-icon="User"
                        />
                    </el-form-item>

                    <el-form-item prop="password">
                        <el-input
                            v-model="loginForm.password"
                            type="password"
                            :placeholder="$t('login.message.password.required')"
                            :disabled="loading"
                            :prefix-icon="Lock"
                            show-password
                            @keyup.enter="handleLogin"
                        />
                    </el-form-item>

                    <el-form-item prop="captcha">
                        <div class="captcha-row">
                            <el-input
                                v-model="loginForm.captcha"
                                :placeholder="$t('login.captchaPlaceholder')"
                                :disabled="loading"
                                :prefix-icon="Key"
                                maxlength="6"
                                @keyup.enter="handleLogin"
                            />
                            <div
                                class="captcha-image"
                                :title="$t('login.captchaRefresh')"
                                @click="refreshCaptcha"
                            >
                                <img
                                    v-if="captchaImage"
                                    :src="captchaImage"
                                    :alt="$t('login.captchaAlt')"
                                />
                                <div v-else class="captcha-loading">
                                    <el-icon class="is-loading"><Loading /></el-icon>
                                </div>
                            </div>
                        </div>
                    </el-form-item>

                    <el-form-item>
                        <el-button
                            type="primary"
                            size="large"
                            :loading="loading"
                            class="login-btn"
                            @click="handleLogin"
                        >
                            {{ loading ? t('login.captchaLoading') : t('login.login') }}
                        </el-button>
                    </el-form-item>
                </el-form>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts" name="Login">
import { Key, Loading, Lock, User } from '@element-plus/icons-vue'
import type { FormInstance, FormRules } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

import { authApi } from '@/api/auth'
import useUserStore from '@/store/modules/user.store'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const userStore = useUserStore()

const loginFormRef = ref<FormInstance>()

const loginForm = reactive({
    username: '',
    password: '',
    captcha: '',
    captcha_key: ''
})

const loginRules: FormRules = {
    username: [
        { required: true, message: t('login.message.username.required'), trigger: 'blur' },
        { min: 3, max: 20, message: t('admin.validate.usernameLength'), trigger: 'blur' }
    ],
    password: [
        { required: true, message: t('login.message.password.required'), trigger: 'blur' },
        { min: 6, max: 20, message: t('admin.validate.passwordLength'), trigger: 'blur' }
    ],
    captcha: [{ required: true, message: t('login.message.captchaCode.required'), trigger: 'blur' }]
}

const loading = ref(false)
const captchaImage = ref('')

// 获取验证码
const refreshCaptcha = async () => {
    try {
        const response = await authApi.getCaptcha()
        captchaImage.value = response.data.image
        loginForm.captcha_key = response.data.key
        loginForm.captcha = ''
    } catch (error) {
        console.error('获取验证码失败:', error)
    }
}

// 登录
const handleLogin = async () => {
    if (!loginFormRef.value) return

    try {
        await loginFormRef.value.validate()

        loading.value = true

        await userStore.login({
            username: loginForm.username,
            password: loginForm.password,
            captcha: loginForm.captcha,
            captcha_key: loginForm.captcha_key
        })

        const redirect = (route.query.redirect as string) || '/'
        await router.push(redirect)
    } catch (error) {
        // 登录失败时刷新验证码
        refreshCaptcha()
        console.error('登录失败:', error)
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    refreshCaptcha()
})
</script>

<style lang="scss" scoped>
.login-page {
    display: flex;
    min-height: 100vh;
    background-color: var(--color-bg);
}

/* ===== 左侧品牌区 ===== */
.login-brand {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    width: 45%;
    min-height: 100vh;
    padding: 60px 40px;
    background: linear-gradient(135deg, #1d39c4 0%, #6366f1 50%, #818cf8 100%);
    color: #fff;
    position: relative;
    overflow: hidden;

    // 装饰圆形
    &::before {
        content: '';
        position: absolute;
        top: -120px;
        right: -120px;
        width: 400px;
        height: 400px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.06);
    }

    &::after {
        content: '';
        position: absolute;
        bottom: -80px;
        left: -80px;
        width: 300px;
        height: 300px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.04);
    }
}

.brand-content {
    position: relative;
    z-index: 1;
    text-align: center;
}

.brand-image {
    margin-bottom: 32px;
    img {
        max-width: 320px;
        width: 100%;
        height: auto;
    }
}

.brand-footer {
    position: absolute;
    bottom: 24px;
    font-size: 12px;
    opacity: 0.5;
    z-index: 1;
}

/* ===== 右侧登录区 ===== */
.login-main {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
    background-color: var(--color-bg);
}

.login-card {
    width: 100%;
    max-width: 400px;
}

.login-header {
    margin-bottom: 32px;

    .login-title {
        font-size: 26px;
        font-weight: 700;
        color: var(--color-text-primary);
        margin: 0 0 8px;
    }

    .login-desc {
        font-size: 14px;
        color: var(--color-text-secondary);
        margin: 0;
    }
}

.login-form {
    :deep(.el-form-item) {
        margin-bottom: 22px;
    }

    :deep(.el-input__wrapper) {
        border-radius: 8px;
        padding: 4px 12px;
        box-shadow: 0 0 0 1px var(--color-border);
        transition: all 0.2s;

        &:hover {
            box-shadow: 0 0 0 1px var(--gray-400);
        }

        &.is-focus {
            box-shadow: 0 0 0 1px var(--el-color-primary);
        }
    }
}

/* 验证码行 */
.captcha-row {
    display: flex;
    gap: 12px;
    width: 100%;

    .el-input {
        flex: 1;
    }
}

.captcha-image {
    flex-shrink: 0;
    width: 130px;
    height: 40px;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    border: 1px solid var(--color-border);
    background: var(--gray-100);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: border-color 0.2s;

    &:hover {
        border-color: var(--el-color-primary);
    }

    img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
}

.captcha-loading {
    color: var(--color-text-disabled);
}

/* 登录按钮 */
.login-btn {
    width: 100%;
    height: 44px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 500;
    letter-spacing: 4px;
}

/* ===== 响应式 ===== */
@media (max-width: 900px) {
    .login-brand {
        display: none;
    }

    .login-main {
        background: var(--color-bg);
    }
}

@media (max-width: 480px) {
    .login-main {
        padding: 24px 20px;
    }

    .login-header .login-title {
        font-size: 22px;
    }
}
</style>
