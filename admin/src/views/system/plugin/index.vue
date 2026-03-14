<template>
    <div class="plugin-container">
        <!-- 顶部操作栏 -->
        <el-card shadow="never" class="toolbar-card">
            <div class="toolbar">
                <div class="toolbar-left">
                    <el-radio-group v-model="filter" @change="applyFilter">
                        <el-radio-button value="all">全部</el-radio-button>
                        <el-radio-button value="enabled">已启用</el-radio-button>
                        <el-radio-button value="disabled">已禁用</el-radio-button>
                        <el-radio-button value="not_installed">未安装</el-radio-button>
                    </el-radio-group>
                </div>
                <div class="toolbar-right">
                    <el-upload
                        :show-file-list="false"
                        :before-upload="handleBeforeUpload"
                        :http-request="handleUpload"
                        accept=".zip"
                    >
                        <el-button type="primary">
                            <el-icon><Upload /></el-icon>
                            上传插件
                        </el-button>
                    </el-upload>
                </div>
            </div>
        </el-card>

        <!-- 插件列表 -->
        <div class="plugin-grid" v-loading="loading">
            <el-empty v-if="filteredList.length === 0" description="暂无插件" />
            <el-row :gutter="16" v-else>
                <el-col
                    v-for="plugin in filteredList"
                    :key="plugin.name"
                    :xs="24" :sm="12" :md="8" :lg="6"
                >
                    <el-card class="plugin-card" shadow="hover">
                        <div class="plugin-header">
                            <div class="plugin-icon">
                                {{ plugin.title?.charAt(0) || plugin.name.charAt(0) }}
                            </div>
                            <div class="plugin-info">
                                <div class="plugin-title">{{ plugin.title || plugin.name }}</div>
                                <div class="plugin-meta">
                                    v{{ plugin.version }}
                                    <span v-if="plugin.author"> · {{ plugin.author }}</span>
                                </div>
                            </div>
                            <el-tag
                                v-if="plugin.installed && plugin.enabled"
                                type="success" size="small"
                            >已启用</el-tag>
                            <el-tag
                                v-else-if="plugin.installed && !plugin.enabled"
                                type="warning" size="small"
                            >已禁用</el-tag>
                            <el-tag v-else type="info" size="small">未安装</el-tag>
                        </div>
                        <div class="plugin-desc">
                            {{ plugin.description || '暂无描述' }}
                        </div>
                        <div class="plugin-actions">
                            <template v-if="!plugin.installed">
                                <el-button
                                    type="primary" size="small" text
                                    @click="handleInstall(plugin)"
                                    :loading="actionLoading === plugin.name"
                                >安装</el-button>
                                <el-button
                                    type="danger" size="small" text
                                    @click="handleDelete(plugin)"
                                >删除</el-button>
                            </template>
                            <template v-else-if="plugin.enabled">
                                <el-button
                                    type="warning" size="small" text
                                    @click="handleDisable(plugin)"
                                    :loading="actionLoading === plugin.name"
                                >禁用</el-button>
                                <el-button
                                    type="danger" size="small" text
                                    @click="handleUninstall(plugin)"
                                >卸载</el-button>
                            </template>
                            <template v-else>
                                <el-button
                                    type="success" size="small" text
                                    @click="handleEnable(plugin)"
                                    :loading="actionLoading === plugin.name"
                                >启用</el-button>
                                <el-button
                                    type="danger" size="small" text
                                    @click="handleUninstall(plugin)"
                                >卸载</el-button>
                            </template>
                        </div>
                    </el-card>
                </el-col>
            </el-row>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Upload } from '@element-plus/icons-vue'
import { pluginApi, type PluginInfo } from '@/api/plugin'

const loading = ref(false)
const actionLoading = ref('')
const pluginList = ref<PluginInfo[]>([])
const filter = ref('all')

const filteredList = computed(() => {
    if (filter.value === 'all') return pluginList.value
    if (filter.value === 'enabled') return pluginList.value.filter(p => p.installed && p.enabled)
    if (filter.value === 'disabled') return pluginList.value.filter(p => p.installed && !p.enabled)
    if (filter.value === 'not_installed') return pluginList.value.filter(p => !p.installed)
    return pluginList.value
})

const getList = async () => {
    loading.value = true
    try {
        const res = await pluginApi.getList()
        pluginList.value = res.data || []
    } finally {
        loading.value = false
    }
}

const applyFilter = () => {
    // computed 已处理
}

const handleBeforeUpload = (file: File) => {
    if (!file.name.endsWith('.zip')) {
        ElMessage.error('仅支持 .zip 格式的插件包')
        return false
    }
    return true
}

const handleUpload = async (options: any) => {
    loading.value = true
    try {
        await pluginApi.upload(options.file)
        ElMessage.success('插件上传成功')
        await getList()
    } catch {
        // 错误由拦截器处理
    } finally {
        loading.value = false
    }
}

const handleInstall = async (plugin: PluginInfo) => {
    actionLoading.value = plugin.name
    try {
        await pluginApi.install(plugin.name)
        ElMessage.success('安装成功')
        await getList()
    } finally {
        actionLoading.value = ''
    }
}

const handleUninstall = async (plugin: PluginInfo) => {
    await ElMessageBox.confirm(`确定卸载插件「${plugin.title}」？卸载将回滚数据库迁移。`, '确认卸载', {
        type: 'warning'
    })
    actionLoading.value = plugin.name
    try {
        await pluginApi.uninstall(plugin.name)
        ElMessage.success('卸载成功')
        await getList()
    } finally {
        actionLoading.value = ''
    }
}

const handleEnable = async (plugin: PluginInfo) => {
    actionLoading.value = plugin.name
    try {
        await pluginApi.enable(plugin.name)
        ElMessage.success('启用成功')
        await getList()
    } finally {
        actionLoading.value = ''
    }
}

const handleDisable = async (plugin: PluginInfo) => {
    actionLoading.value = plugin.name
    try {
        await pluginApi.disable(plugin.name)
        ElMessage.success('禁用成功')
        await getList()
    } finally {
        actionLoading.value = ''
    }
}

const handleDelete = async (plugin: PluginInfo) => {
    await ElMessageBox.confirm(`确定删除插件「${plugin.title}」文件？此操作不可恢复。`, '确认删除', {
        type: 'warning'
    })
    actionLoading.value = plugin.name
    try {
        await pluginApi.delete(plugin.name)
        ElMessage.success('删除成功')
        await getList()
    } finally {
        actionLoading.value = ''
    }
}

onMounted(() => {
    getList()
})
</script>

<style scoped lang="scss">
.plugin-container {
    padding: 16px;
}

.toolbar-card {
    margin-bottom: 16px;
}

.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.plugin-grid {
    min-height: 200px;
}

.plugin-card {
    margin-bottom: 16px;

    .plugin-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }

    .plugin-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: var(--el-color-primary-light-9);
        color: var(--el-color-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: bold;
        flex-shrink: 0;
    }

    .plugin-info {
        flex: 1;
        min-width: 0;
    }

    .plugin-title {
        font-size: 14px;
        font-weight: 600;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .plugin-meta {
        font-size: 12px;
        color: var(--el-text-color-secondary);
        margin-top: 2px;
    }

    .plugin-desc {
        font-size: 13px;
        color: var(--el-text-color-regular);
        line-height: 1.5;
        min-height: 40px;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .plugin-actions {
        margin-top: 12px;
        display: flex;
        justify-content: flex-end;
        gap: 4px;
        border-top: 1px solid var(--el-border-color-lighter);
        padding-top: 12px;
    }
}
</style>
