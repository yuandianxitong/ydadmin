<template>
    <div class="api-doc-page">
        <el-card shadow="never" class="!border-none">
            <template #header>
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <span class="text-lg font-bold">{{ $t('apiDoc.title') }}</span>
                        <el-tag size="small" effect="plain">OpenAPI 3.0</el-tag>
                    </div>
                    <div class="flex items-center gap-2">
                        <el-tooltip :content="$t('apiDoc.openSwagger')" placement="bottom">
                            <el-button type="primary" link @click="openSwaggerUI">
                                <el-icon class="mr-1"><Link /></el-icon>
                                Swagger UI
                            </el-button>
                        </el-tooltip>
                        <el-tooltip :content="$t('apiDoc.downloadJson')" placement="bottom">
                            <el-button type="primary" link @click="downloadJson">
                                <el-icon class="mr-1"><Download /></el-icon>
                                OpenAPI JSON
                            </el-button>
                        </el-tooltip>
                    </div>
                </div>
            </template>

            <div ref="swaggerContainer" class="swagger-wrapper"></div>

            <el-empty v-if="loadError" :description="$t('apiDoc.loadFailed')">
                <el-button type="primary" @click="initSwagger">{{ $t('apiDoc.retry') }}</el-button>
            </el-empty>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { Download, Link } from '@element-plus/icons-vue'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const swaggerContainer = ref<HTMLElement>()
const loadError = ref(false)
let swaggerUI: any = null

const getOpenapiUrl = () => {
    // 始终用相对路径，开发环境走 Vite 代理，生产环境走同域
    return '/adminapi/system/api-doc/openapi.json'
}

const initSwagger = async () => {
    loadError.value = false
    if (!swaggerContainer.value) return

    try {
        // 动态加载 Swagger UI
        const SwaggerUIBundle = await loadSwaggerUI()

        swaggerUI = SwaggerUIBundle({
            url: getOpenapiUrl(),
            domNode: swaggerContainer.value,
            deepLinking: true,
            presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
            layout: 'BaseLayout',
            defaultModelsExpandDepth: -1,
            docExpansion: 'list',
            filter: true,
            persistAuthorization: true,
            requestInterceptor: (req: any) => {
                // 自动注入当前登录 token
                const token = localStorage.getItem('like_admin_token')
                if (token) {
                    req.headers.Authorization = `Bearer ${token}`
                }
                return req
            }
        })
    } catch (e) {
        console.error(t('apiDoc.swaggerLoadFailed') + ':', e)
        loadError.value = true
    }
}

const loadSwaggerUI = (): Promise<any> => {
    return new Promise((resolve, reject) => {
        // 检查是否已加载
        if ((window as any).SwaggerUIBundle) {
            resolve((window as any).SwaggerUIBundle)
            return
        }

        // 加载 CSS
        const link = document.createElement('link')
        link.rel = 'stylesheet'
        link.href = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css'
        document.head.appendChild(link)

        // 加载 JS
        const script = document.createElement('script')
        script.src = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js'
        script.onload = () => {
            if ((window as any).SwaggerUIBundle) {
                resolve((window as any).SwaggerUIBundle)
            } else {
                reject(new Error('SwaggerUIBundle not found'))
            }
        }
        script.onerror = reject
        document.head.appendChild(script)
    })
}

const openSwaggerUI = () => {
    window.open('/adminapi/system/api-doc', '_blank')
}

const downloadJson = () => {
    window.open(getOpenapiUrl(), '_blank')
}

onMounted(initSwagger)

onBeforeUnmount(() => {
    swaggerUI = null
})
</script>

<style lang="scss" scoped>
.api-doc-page {
    height: 100%;

    :deep(.el-card__body) {
        padding: 0;
    }

    .swagger-wrapper {
        min-height: 600px;

        :deep(.swagger-ui) {
            .topbar {
                display: none;
            }

            .info {
                margin: 16px 20px;
            }

            .scheme-container {
                padding: 12px 20px;
                box-shadow: none;
                border-bottom: 1px solid var(--el-border-color-lighter);
            }

            .opblock-tag {
                border-bottom: 1px solid var(--el-border-color-lighter);
            }

            .opblock {
                border-radius: 6px;
                margin-bottom: 8px;
                box-shadow: none;
            }

            .btn {
                border-radius: 4px;
            }
        }
    }
}
</style>
