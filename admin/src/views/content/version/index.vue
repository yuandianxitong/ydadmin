<template>
    <div class="version-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('versionMgmt.platform')">
                    <el-select
                        v-model="searchForm.platform"
                        :placeholder="$t('versionMgmt.platformPlaceholder')"
                        clearable
                        style="width: 150px"
                    >
                        <el-option :label="$t('versionMgmt.platformOptions.android')" value="android" />
                        <el-option :label="$t('versionMgmt.platformOptions.ios')" value="ios" />
                        <el-option :label="$t('versionMgmt.platformOptions.harmony')" value="harmony" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('common.selectPlaceholder')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('common.enable')" :value="1" />
                        <el-option :label="$t('common.disable')" :value="0" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="getList">
                        <el-icon><Search /></el-icon>
                        {{ $t('common.search') }}
                    </el-button>
                    <el-button @click="resetSearch">
                        <el-icon><Refresh /></el-icon>
                        {{ $t('common.reset') }}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 操作区域 -->
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">{{ $t('versionMgmt.title') }}</div>
                <div class="table-actions">
                    <el-button
                        v-has-perm="['version.create']"
                        type="primary"
                        @click="handleAdd"
                    >
                        <el-icon><Plus /></el-icon>
                        {{ $t('versionMgmt.addVersion') }}
                    </el-button>
                </div>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column :label="$t('versionMgmt.platform')" prop="platform" width="120">
                    <template #default="{ row }">
                        <el-tag :type="platformTagMap[row.platform] || 'info'" size="small">
                            {{ platformTextMap[row.platform] || row.platform }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('versionMgmt.version')" prop="version" width="120" />

                <el-table-column :label="$t('versionMgmt.versionCode')" prop="version_code" width="120" />

                <el-table-column :label="$t('versionMgmt.downloadUrl')" prop="download_url" min-width="250" show-overflow-tooltip />

                <el-table-column :label="$t('versionMgmt.forceUpdate')" prop="force_update" width="110">
                    <template #default="{ row }">
                        <el-tag :type="row.force_update === 1 ? 'danger' : 'info'" size="small">
                            {{ row.force_update === 1 ? $t('common.yes') : $t('common.no') }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.status')" prop="status" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                            {{ row.status === 1 ? $t('common.enable') : $t('common.disable') }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.createdAt')" prop="created_at" width="160" />

                <el-table-column :label="$t('common.operation')" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-has-perm="['version.update']"
                            type="primary"
                            size="small"
                            text
                            @click="handleEdit(row)"
                        >
                            {{ $t('common.edit') }}
                        </el-button>
                        <el-button
                            v-has-perm="['version.delete']"
                            type="danger"
                            size="small"
                            text
                            @click="handleDelete(row)"
                        >
                            {{ $t('common.delete') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 分页 -->
            <el-pagination
                v-model:current-page="pagination.current_page"
                v-model:page-size="pagination.per_page"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                class="pagination"
                @size-change="getList"
                @current-change="getList"
            />
        </el-card>

        <!-- 表单弹窗 -->
        <VersionForm v-model="formVisible" :form-data="formData" @success="getList" />
    </div>
</template>

<script setup lang="ts" name="VersionList">
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { versionApi } from '@/api/version'

import VersionForm from './components/VersionForm.vue'

const { t } = useI18n()

const searchForm = reactive({ platform: undefined as string | undefined, status: undefined as number | undefined })
const list = ref<any[]>([])
const loading = ref(false)
const pagination = reactive({ current_page: 1, per_page: 20, total: 0, last_page: 1 })

const formVisible = ref(false)
const formData = ref<Record<string, any>>({})

const platformTextMap = computed(() => ({
    android: t('versionMgmt.platformOptions.android'),
    ios: t('versionMgmt.platformOptions.ios'),
    harmony: t('versionMgmt.platformOptions.harmony')
} as Record<string, string>))
const platformTagMap: Record<string, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
    android: 'success',
    ios: 'primary',
    harmony: 'warning'
}

const getList = async () => {
    try {
        loading.value = true
        const params: Record<string, any> = {
            page: pagination.current_page,
            limit: pagination.per_page
        }
        if (searchForm.platform) params.platform = searchForm.platform
        if (searchForm.status !== undefined) params.status = searchForm.status

        const res = await versionApi.getList(params)
        list.value = res.data.list
        Object.assign(pagination, res.data.pagination)
    } catch {
        ElMessage.error(t('message.fetchFailed'))
    } finally {
        loading.value = false
    }
}

const resetSearch = () => {
    Object.assign(searchForm, { platform: undefined, status: undefined })
    pagination.current_page = 1
    getList()
}

const handleAdd = () => {
    formData.value = { platform: '', force_update: 0, status: 1 }
    formVisible.value = true
}

const handleEdit = (row: any) => {
    formData.value = { ...row }
    formVisible.value = true
}

const handleDelete = async (row: any) => {
    try {
        await ElMessageBox.confirm(t('message.deleteConfirmName', { name: `${row.platform} v${row.version}` }), t('message.confirmDelete'), {
            confirmButtonText: t('common.confirm'),
            cancelButtonText: t('common.cancel'),
            type: 'warning'
        })
        await versionApi.delete(row.id)
        ElMessage.success(t('message.deleteSuccess'))
        getList()
    } catch (error) {
        if (error !== 'cancel') ElMessage.error(t('common.error'))
    }
}

onMounted(() => {
    getList()
})
</script>

<style lang="scss" scoped>
.version-container {
    .search-card {
        margin-bottom: 16px;

        .search-form {
            margin: 0;
        }
    }

    .table-card {
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;

            .table-title {
                font-size: 16px;
                font-weight: 600;
                color: var(--el-text-color-primary);
            }

            .table-actions {
                display: flex;
                gap: 8px;
            }
        }

        .pagination {
            margin-top: 16px;
            display: flex;
            justify-content: flex-end;
        }
    }
}
</style>
