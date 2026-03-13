<template>
    <div class="log-container">
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('admin.username')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('loginLog.usernamePlaceholder')"
                        clearable
                        style="width: 160px"
                    />
                </el-form-item>
                <el-form-item label="IP">
                    <el-input
                        v-model="searchForm.ip"
                        :placeholder="$t('loginLog.ipPlaceholder')"
                        clearable
                        style="width: 150px"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.login_result"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 100px"
                    >
                        <el-option :label="$t('loginLog.resultOptions.success')" :value="1" />
                        <el-option :label="$t('loginLog.resultOptions.failed')" :value="0" />
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

        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">{{ $t('loginLog.title') }}</div>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column :label="$t('common.id')" prop="id" width="80" />
                <el-table-column :label="$t('admin.username')" prop="username" width="120" />
                <el-table-column :label="$t('loginLog.loginIp')" prop="ip" width="140" />
                <el-table-column :label="$t('loginLog.browser')" prop="browser" width="160" show-overflow-tooltip />
                <el-table-column :label="$t('loginLog.os')" prop="os" width="130" />
                <el-table-column :label="$t('loginLog.loginResult')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.login_result ? 'success' : 'danger'" size="small">
                            {{ row.login_result ? $t('loginLog.resultOptions.success') : $t('loginLog.resultOptions.failed') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('loginLog.loginMessage')"
                    prop="login_message"
                    min-width="160"
                    show-overflow-tooltip
                />
                <el-table-column :label="$t('loginLog.loginTime')" prop="login_time" width="170" />
            </el-table>

            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                class="pagination"
                @size-change="getList"
                @current-change="getList"
            />
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { Refresh, Search } from '@element-plus/icons-vue'
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { logApi } from '@/api/log'

const { t } = useI18n()

const searchForm = reactive({
    keyword: '',
    ip: '',
    login_result: undefined as number | undefined
})

const list = ref<any[]>([])
const loading = ref(false)
const pagination = reactive({ page: 1, limit: 20, total: 0 })

const getList = async () => {
    try {
        loading.value = true
        const response = await logApi.getLoginLogList({
            ...searchForm,
            page: pagination.page,
            limit: pagination.limit
        })
        list.value = response.data.list
        pagination.total = response.data.pagination.total
    } catch (error) {
        console.error('Failed to fetch login logs:', error)
    } finally {
        loading.value = false
    }
}

const resetSearch = () => {
    searchForm.keyword = ''
    searchForm.ip = ''
    searchForm.login_result = undefined
    pagination.page = 1
    getList()
}

onMounted(() => {
    getList()
})
</script>

<style lang="scss" scoped>
.log-container {
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
        }
        .pagination {
            margin-top: 16px;
            display: flex;
            justify-content: flex-end;
        }
    }
}
</style>
