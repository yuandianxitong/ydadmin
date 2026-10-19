<template>
    <div class="api-doc-page">
        <el-card shadow="never" class="!border-none">
            <template #header>
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <span class="text-lg font-bold">{{ $t('apiDoc.title') }}</span>
                        <el-tag size="small" effect="plain">OpenAPI 3.0</el-tag>
                        <el-radio-group v-model="apiType" size="small" @change="handleTypeChange">
                            <el-radio-button value="admin">后台管理 API</el-radio-button>
                            <el-radio-button value="api">前端应用 API</el-radio-button>
                        </el-radio-group>
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

            <div v-show="!loadError" ref="swaggerContainer" class="swagger-wrapper"></div>

            <el-empty v-if="loadError" :description="loadErrorMessage || $t('apiDoc.loadFailed')">
                <el-button type="primary" @click="initSwagger">{{ $t('apiDoc.retry') }}</el-button>
            </el-empty>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { Download, Link } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

import { fetchOpenApiDocument } from '@/api/api-doc'
import { getToken } from '@/utils/auth'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const swaggerContainer = ref<HTMLElement>()
const loadError = ref(false)
const loadErrorMessage = ref('')
let loadSequence = 0
let loadController: AbortController | undefined
let swaggerUI: any = null

const apiType = ref<'admin' | 'api'>(route.query.type === 'api' ? 'api' : 'admin')

const initSwagger = async () => {
    const sequence = ++loadSequence
    loadController?.abort()
    loadController = new AbortController()
    loadError.value = false
    loadErrorMessage.value = ''
    if (!swaggerContainer.value) return
    swaggerUI?.destroy?.()
    swaggerUI = null
    swaggerContainer.value.replaceChildren()

    try {
        const spec = await fetchOpenApiDocument(apiType.value, loadController.signal)
        const SwaggerUIBundle = await loadSwaggerUI()
        if (sequence !== loadSequence) return
        swaggerUI = SwaggerUIBundle({
            spec,
            domNode: swaggerContainer.value,
            deepLinking: true,
            presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
            layout: 'BaseLayout',
            defaultModelsExpandDepth: -1,
            docExpansion: 'list',
            filter: true,
            persistAuthorization: true,
            requestInterceptor: (req: any) => {
                const token = getToken()
                if (token) req.headers.Authorization = `Bearer ${token}`
                return req
            }
        })
    } catch (error) {
        if (
            sequence !== loadSequence ||
            (error instanceof DOMException && error.name === 'AbortError')
        )
            return
        loadError.value = true
        loadErrorMessage.value = error instanceof Error ? error.message : t('apiDoc.loadFailed')
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

const handleTypeChange = () => {
    initSwagger()
}

const openSwaggerUI = () => {
    const href = router.resolve({
        path: route.path,
        query: { ...route.query, type: apiType.value }
    }).href
    window.open(href, '_blank', 'noopener')
}

const downloadJson = async () => {
    try {
        const document = await fetchOpenApiDocument(apiType.value)
        const url = URL.createObjectURL(
            new Blob([JSON.stringify(document, null, 2)], { type: 'application/json' })
        )
        const link = window.document.createElement('a')
        link.href = url
        link.download = `openapi-${apiType.value}.json`
        window.document.body.appendChild(link)
        link.click()
        link.remove()
        setTimeout(() => URL.revokeObjectURL(url), 0)
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') return
        ElMessage.error(error instanceof Error ? error.message : t('apiDoc.loadFailed'))
    }
}

onMounted(initSwagger)

onBeforeUnmount(() => {
    loadSequence++
    loadController?.abort()
    swaggerUI?.destroy?.()
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
