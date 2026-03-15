<template>
    <div class="region-container">
        <!-- 面包屑导航 -->
        <el-card class="search-card" shadow="never">
            <div class="breadcrumb-bar">
                <el-breadcrumb separator="/">
                    <el-breadcrumb-item>
                        <a @click.prevent="navigateTo(-1)">{{ $t('regionMgmt.title') }}</a>
                    </el-breadcrumb-item>
                    <el-breadcrumb-item v-for="(item, index) in breadcrumbs" :key="item.id">
                        <a v-if="index < breadcrumbs.length - 1" @click.prevent="navigateTo(index)">{{ item.name }}</a>
                        <span v-else>{{ item.name }}</span>
                    </el-breadcrumb-item>
                </el-breadcrumb>
                <div class="search-actions">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('regionMgmt.namePlaceholder')"
                        clearable
                        style="width: 200px"
                        @keyup.enter="handleSearch"
                    />
                    <el-button type="primary" @click="handleSearch">
                        <el-icon><Search /></el-icon>
                        {{ $t('common.search') }}
                    </el-button>
                    <el-button @click="resetSearch">
                        <el-icon><Refresh /></el-icon>
                        {{ $t('common.reset') }}
                    </el-button>
                </div>
            </div>
        </el-card>

        <!-- 表格区域 -->
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">
                    <el-tag v-if="currentParentId > 0" type="info" size="small" class="level-tag">
                        {{ levelTextMap[currentLevel] || `Level ${currentLevel}` }}
                    </el-tag>
                    {{ currentParentId === 0 ? $t('regionMgmt.title') : breadcrumbs[breadcrumbs.length - 1]?.name }}
                </div>
                <div class="table-actions">
                    <el-button
                        v-if="currentParentId > 0"
                        @click="goBack"
                    >
                        <el-icon><Back /></el-icon>
                        返回上级
                    </el-button>
                    <el-button
                        v-has-perm="['region.create']"
                        type="primary"
                        @click="handleAdd"
                    >
                        <el-icon><Plus /></el-icon>
                        {{ $t('regionMgmt.addRegion') }}
                    </el-button>
                </div>
            </div>

            <el-table
                v-loading="loading"
                :data="tableData"
                border
            >
                <el-table-column :label="$t('regionMgmt.regionName')" prop="name" min-width="200" />

                <el-table-column :label="$t('regionMgmt.regionCode')" prop="code" width="150" />

                <el-table-column :label="$t('regionMgmt.level')" prop="level" width="120">
                    <template #default="{ row }">
                        <el-tag :type="levelTagMap[row.level] || 'info'" size="small">
                            {{ levelTextMap[row.level] || row.level }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.sort')" prop="sort" width="100" />

                <el-table-column :label="$t('common.status')" prop="status" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                            {{ row.status === 1 ? $t('common.enable') : $t('common.disable') }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.operation')" width="320" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            type="primary"
                            size="small"
                            text
                            @click="handleDrillDown(row)"
                        >
                            下级管理
                        </el-button>
                        <el-button
                            v-has-perm="['region.create']"
                            type="primary"
                            size="small"
                            text
                            @click="handleAddChild(row)"
                        >
                            {{ $t('regionMgmt.addChildRegion') }}
                        </el-button>
                        <el-button
                            v-has-perm="['region.update']"
                            type="primary"
                            size="small"
                            text
                            @click="handleEdit(row)"
                        >
                            {{ $t('common.edit') }}
                        </el-button>
                        <el-button
                            v-has-perm="['region.delete']"
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
            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="pagination.page"
                    v-model:page-size="pagination.pageSize"
                    :total="pagination.total"
                    :page-sizes="[20, 50, 100]"
                    layout="total, sizes, prev, pager, next, jumper"
                    background
                    @size-change="fetchList"
                    @current-change="fetchList"
                />
            </div>
        </el-card>

        <!-- 表单弹窗 -->
        <RegionForm v-model="formVisible" :form-data="formData" @success="fetchList" />
    </div>
</template>

<script setup lang="ts" name="RegionList">
import { Back, Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { regionApi } from '@/api/region'

import RegionForm from './components/RegionForm.vue'

const { t } = useI18n()

interface BreadcrumbItem {
    id: number
    name: string
    level: number
}

const searchForm = reactive({ keyword: '' })
const tableData = ref<any[]>([])
const loading = ref(false)

const currentParentId = ref(0)
const currentLevel = ref(0)
const breadcrumbs = ref<BreadcrumbItem[]>([])

const pagination = reactive({
    page: 1,
    pageSize: 20,
    total: 0
})

const formVisible = ref(false)
const formData = ref<Record<string, any>>({})

const levelTextMap = computed(() => ({
    1: t('regionMgmt.levelOptions.province'),
    2: t('regionMgmt.levelOptions.city'),
    3: t('regionMgmt.levelOptions.district'),
    4: t('regionMgmt.levelOptions.street')
} as Record<number, string>))
const levelTagMap: Record<number, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
    1: 'primary',
    2: 'success',
    3: 'warning',
    4: 'info'
}

const fetchList = async () => {
    try {
        loading.value = true
        const params: Record<string, any> = {
            page_no: pagination.page,
            page_size: pagination.pageSize,
            parent_id: currentParentId.value
        }
        if (searchForm.keyword?.trim()) {
            params.keyword = searchForm.keyword.trim()
        }
        const res = await regionApi.getList(params)
        tableData.value = res.data.list || []
        pagination.total = res.data.pagination?.total || 0
    } catch {
        ElMessage.error(t('message.fetchFailed'))
    } finally {
        loading.value = false
    }
}

const handleSearch = () => {
    pagination.page = 1
    fetchList()
}

const resetSearch = () => {
    searchForm.keyword = ''
    pagination.page = 1
    fetchList()
}

const handleDrillDown = (row: any) => {
    breadcrumbs.value.push({ id: row.id, name: row.name, level: row.level })
    currentParentId.value = row.id
    currentLevel.value = row.level
    searchForm.keyword = ''
    pagination.page = 1
    fetchList()
}

const navigateTo = (index: number) => {
    if (index === -1) {
        // Navigate to root
        breadcrumbs.value = []
        currentParentId.value = 0
        currentLevel.value = 0
    } else {
        // Navigate to specific breadcrumb level
        const target = breadcrumbs.value[index]
        breadcrumbs.value = breadcrumbs.value.slice(0, index + 1)
        currentParentId.value = target.id
        currentLevel.value = target.level
    }
    searchForm.keyword = ''
    pagination.page = 1
    fetchList()
}

const goBack = () => {
    if (breadcrumbs.value.length > 1) {
        breadcrumbs.value.pop()
        const parent = breadcrumbs.value[breadcrumbs.value.length - 1]
        currentParentId.value = parent.id
        currentLevel.value = parent.level
    } else {
        breadcrumbs.value = []
        currentParentId.value = 0
        currentLevel.value = 0
    }
    searchForm.keyword = ''
    pagination.page = 1
    fetchList()
}

const handleAdd = () => {
    const level = currentParentId.value === 0 ? 1 : currentLevel.value + 1
    const parentName = breadcrumbs.value.length > 0 ? breadcrumbs.value[breadcrumbs.value.length - 1].name : ''
    formData.value = { parent_id: currentParentId.value, parent_name: parentName, level, sort: 0, status: 1 }
    formVisible.value = true
}

const handleAddChild = (row: any) => {
    formData.value = { parent_id: row.id, parent_name: row.name, level: (row.level || 0) + 1, sort: 0, status: 1 }
    formVisible.value = true
}

const handleEdit = (row: any) => {
    formData.value = { ...row }
    formVisible.value = true
}

const handleDelete = async (row: any) => {
    try {
        await ElMessageBox.confirm(t('message.deleteConfirmName', { name: row.name }), t('message.confirmDelete'), {
            confirmButtonText: t('common.confirm'),
            cancelButtonText: t('common.cancel'),
            type: 'warning'
        })
        await regionApi.delete(row.id)
        ElMessage.success(t('message.deleteSuccess'))
        fetchList()
    } catch (error) {
        if (error !== 'cancel') ElMessage.error(t('common.error'))
    }
}

onMounted(() => {
    fetchList()
})
</script>

<style lang="scss" scoped>
.region-container {
    .search-card {
        margin-bottom: 16px;

        .breadcrumb-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;

            :deep(.el-breadcrumb) {
                font-size: 14px;

                a {
                    cursor: pointer;
                    color: var(--el-color-primary);

                    &:hover {
                        text-decoration: underline;
                    }
                }
            }

            .search-actions {
                display: flex;
                gap: 8px;
                align-items: center;
            }
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
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .table-actions {
                display: flex;
                gap: 8px;
            }
        }

        .pagination-wrap {
            display: flex;
            justify-content: flex-end;
            margin-top: 16px;
        }
    }
}
</style>
