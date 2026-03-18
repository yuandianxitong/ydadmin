<template>
    <div class="user-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item label="用户昵称/账号">
                    <el-input
                        v-model="searchForm.keyword"
                        placeholder="请输入昵称或手机号"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select
                        v-model="searchForm.status"
                        placeholder="全部"
                        clearable
                        style="width: 120px"
                    >
                        <el-option label="启用" :value="1" />
                        <el-option label="禁用" :value="0" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="getUserList">
                        <el-icon><Search /></el-icon>
                        搜索
                    </el-button>
                    <el-button @click="resetSearch">
                        <el-icon><Refresh /></el-icon>
                        重置
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 表格区域 -->
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">用户列表</div>
            </div>

            <el-table v-loading="loading" :data="userList">
                <el-table-column label="ID" prop="id" width="80" />

                <el-table-column label="头像" width="80">
                    <template #default="{ row }">
                        <el-image
                            :src="appStore.getImageUrl(row.avatar)"
                            :alt="row.nickname"
                            style="width: 40px; height: 40px; border-radius: 50%"
                            fit="cover"
                        >
                            <template #error>
                                <div class="avatar-fallback">
                                    {{ (row.nickname || '用')[0] }}
                                </div>
                            </template>
                        </el-image>
                    </template>
                </el-table-column>

                <el-table-column label="昵称" prop="nickname" width="120" show-overflow-tooltip />

                <el-table-column label="账号" prop="mobile" width="140" />

                <el-table-column label="余额" width="120">
                    <template #default="{ row }">
                        <span class="balance-text">¥{{ row.balance }}</span>
                    </template>
                </el-table-column>

                <el-table-column label="积分" width="100">
                    <template #default="{ row }">
                        <span class="points-text">{{ row.points }}</span>
                    </template>
                </el-table-column>

                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.status"
                            :active-value="1"
                            :inactive-value="0"
                            @change="handleStatusChange(row)"
                        />
                    </template>
                </el-table-column>

                <el-table-column label="注册时间" prop="created_at" width="170" />

                <el-table-column label="最后登录" prop="last_login_time" width="170" />

                <el-table-column label="操作" width="240" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" size="small" text @click="handleDetail(row)">
                            查看
                        </el-button>
                        <el-button type="warning" size="small" text @click="handleAdjustBalance(row)">
                            调整余额
                        </el-button>
                        <el-button type="success" size="small" text @click="handleAdjustPoints(row)">
                            调整积分
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 分页 -->
            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                class="pagination"
                @size-change="getUserList"
                @current-change="getUserList"
            />
        </el-card>

        <!-- 用户详情弹窗 -->
        <UserDetailDialog
            v-model="detailVisible"
            :user-data="currentUser"
        />

        <!-- 调整余额弹窗 -->
        <AdjustBalanceDialog
            v-model="balanceVisible"
            :user-id="currentUser?.id || 0"
            :current-balance="currentUser?.balance || '0.00'"
            @success="getUserList"
        />

        <!-- 调整积分弹窗 -->
        <AdjustPointsDialog
            v-model="pointsVisible"
            :user-id="currentUser?.id || 0"
            :current-points="currentUser?.points || 0"
            @success="getUserList"
        />
    </div>
</template>

<script setup lang="ts" name="UserList">
import { Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'

import { userManageApi } from '@/api/user'
import type { UserItem } from '@/api/user'
import useAppStore from '@/store/modules/app.store'

import AdjustBalanceDialog from './components/AdjustBalanceDialog.vue'
import AdjustPointsDialog from './components/AdjustPointsDialog.vue'
import UserDetailDialog from './components/UserDetailDialog.vue'

const appStore = useAppStore()

// 搜索表单
const searchForm = reactive({
    keyword: '',
    status: undefined as number | undefined
})

// 用户列表
const userList = ref<UserItem[]>([])
const loading = ref(false)

// 分页信息
const pagination = reactive({
    page: 1,
    limit: 20,
    total: 0
})

// 弹窗相关
const detailVisible = ref(false)
const balanceVisible = ref(false)
const pointsVisible = ref(false)
const currentUser = ref<UserItem | null>(null)

// 获取用户列表
const getUserList = async () => {
    try {
        loading.value = true
        const params = {
            ...searchForm,
            page: pagination.page,
            limit: pagination.limit
        }

        const response = await userManageApi.getUserList(params)
        userList.value = response.data.list
        pagination.total = response.data.pagination.total
    } catch (error) {
        console.error('获取用户列表失败:', error)
    } finally {
        loading.value = false
    }
}

// 重置搜索
const resetSearch = () => {
    Object.assign(searchForm, {
        keyword: '',
        status: undefined
    })
    pagination.page = 1
    getUserList()
}

// 状态变更
const handleStatusChange = async (row: UserItem) => {
    try {
        await userManageApi.updateStatus(row.id, { status: row.status })
        ElMessage.success('状态更新成功')
    } catch (error) {
        // 恢复状态
        row.status = row.status === 1 ? 0 : 1
        console.error('状态更新失败:', error)
    }
}

// 查看详情
const handleDetail = (row: UserItem) => {
    currentUser.value = row
    detailVisible.value = true
}

// 调整余额
const handleAdjustBalance = (row: UserItem) => {
    currentUser.value = row
    balanceVisible.value = true
}

// 调整积分
const handleAdjustPoints = (row: UserItem) => {
    currentUser.value = row
    pointsVisible.value = true
}

onMounted(() => {
    getUserList()
})
</script>

<style lang="scss" scoped>
.user-container {
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

    .avatar-fallback {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: var(--el-color-primary-light-7);
        color: var(--el-color-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: 600;
    }

    .balance-text {
        color: var(--el-color-warning);
        font-weight: 600;
    }

    .points-text {
        color: var(--el-color-primary);
        font-weight: 600;
    }
}
</style>
