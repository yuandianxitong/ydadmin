<template>
    <div class="auto-reply">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline>
                <el-form-item :label="$t('channel.type')">
                    <el-select
                        v-model="searchForm.type"
                        :placeholder="$t('channel.all')"
                        clearable
                        style="width: 140px"
                    >
                        <el-option :label="$t('channel.keywordReply')" value="keyword" />
                        <el-option :label="$t('channel.subscribeReply')" value="subscribe" />
                        <el-option :label="$t('channel.defaultReply')" value="default" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="getList">{{ $t('channel.search') }}</el-button>
                    <el-button @click="resetSearch">{{ $t('channel.reset') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 表格 -->
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">{{ $t('channel.autoReply') }}</div>
                <el-button type="primary" @click="handleAdd">{{ $t('channel.addReply') }}</el-button>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column :label="$t('channel.type')" width="120">
                    <template #default="{ row }">
                        <el-tag :type="typeTagMap[row.type]" size="small">{{
                            typeTextMap[row.type]
                        }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('channel.keyword')" prop="keyword" width="200" show-overflow-tooltip />
                <el-table-column :label="$t('channel.matchType')" width="100">
                    <template #default="{ row }">
                        {{ row.match_type === 'exact' ? $t('channel.exact') : $t('channel.fuzzy') }}
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('channel.replyContent')"
                    prop="content"
                    min-width="250"
                    show-overflow-tooltip
                />
                <el-table-column :label="$t('channel.status')" width="80" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                            {{ row.status === 1 ? $t('channel.enabled') : $t('channel.disabled') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('channel.operation')" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" size="small" text @click="handleEdit(row)"
                            >{{ $t('channel.edit') }}</el-button
                        >
                        <el-button type="danger" size="small" text @click="handleDelete(row)"
                            >{{ $t('channel.delete') }}</el-button
                        >
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="pagination.current_page"
                v-model:page-size="pagination.per_page"
                :total="pagination.total"
                :page-sizes="[10, 20, 50]"
                layout="total, sizes, prev, pager, next, jumper"
                class="pagination"
                @size-change="getList"
                @current-change="getList"
            />
        </el-card>

        <!-- 编辑弹窗 -->
        <el-dialog
            :model-value="formVisible"
            :title="form.id ? $t('channel.editReply') : $t('channel.addReply')"
            width="550px"
            destroy-on-close
            @update:model-value="formVisible = $event"
        >
            <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
                <el-form-item :label="$t('channel.replyType')" prop="type">
                    <el-select v-model="form.type" style="width: 100%">
                        <el-option :label="$t('channel.keywordReply')" value="keyword" />
                        <el-option :label="$t('channel.subscribeReply')" value="subscribe" />
                        <el-option :label="$t('channel.defaultReply')" value="default" />
                    </el-select>
                </el-form-item>
                <template v-if="form.type === 'keyword'">
                    <el-form-item :label="$t('channel.keyword')" prop="keyword">
                        <el-input v-model="form.keyword" :placeholder="$t('channel.triggerKeyword')" />
                    </el-form-item>
                    <el-form-item :label="$t('channel.matchType')">
                        <el-radio-group v-model="form.match_type">
                            <el-radio value="exact">{{ $t('channel.exactMatch') }}</el-radio>
                            <el-radio value="fuzzy">{{ $t('channel.fuzzyMatch') }}</el-radio>
                        </el-radio-group>
                    </el-form-item>
                </template>
                <el-form-item :label="$t('channel.replyContent')" prop="content">
                    <el-input
                        v-model="form.content"
                        type="textarea"
                        :rows="4"
                        :placeholder="$t('channel.replyTextContent')"
                    />
                </el-form-item>
                <el-form-item :label="$t('channel.sortOrder')">
                    <el-input-number v-model="form.sort_order" :min="0" />
                </el-form-item>
                <el-form-item :label="$t('channel.status')">
                    <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="formVisible = false">{{ $t('channel.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="handleSubmit"
                    >{{ $t('channel.confirm') }}</el-button
                >
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts" name="WechatAutoReply">
import type { FormInstance, FormRules } from 'element-plus'
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { autoReplyApi } from '@/api/wechat'

const { t } = useI18n()

const searchForm = reactive({ type: '' as string })
const list = ref<any[]>([])
const loading = ref(false)
const pagination = reactive({ current_page: 1, per_page: 20, total: 0, last_page: 1 })

const formVisible = ref(false)
const submitting = ref(false)
const formRef = ref<FormInstance>()
const form = reactive<Record<string, any>>({
    type: 'keyword',
    keyword: '',
    match_type: 'exact',
    content: '',
    sort_order: 0,
    status: 1
})

const typeTextMap = computed<Record<string, string>>(() => ({
    keyword: t('channel.keywordText'),
    subscribe: t('channel.subscribeReply'),
    default: t('channel.defaultReply')
}))
const typeTagMap: Record<string, any> = {
    keyword: 'primary',
    subscribe: 'success',
    default: 'info'
}

const rules = computed<FormRules>(() => ({
    type: [{ required: true, message: t('channel.selectType'), trigger: 'change' }],
    content: [{ required: true, message: t('channel.enterReplyContent'), trigger: 'blur' }]
}))

const getList = async () => {
    try {
        loading.value = true
        const params: Record<string, any> = {
            page: pagination.current_page,
            limit: pagination.per_page
        }
        if (searchForm.type) params.type = searchForm.type
        const res = await autoReplyApi.getList(params)
        list.value = res.data.list
        Object.assign(pagination, res.data.pagination)
    } catch {
        ElMessage.error(t('channel.fetchListFailed'))
    } finally {
        loading.value = false
    }
}

const resetSearch = () => {
    searchForm.type = ''
    pagination.current_page = 1
    getList()
}

const handleAdd = () => {
    Object.assign(form, {
        id: undefined,
        type: 'keyword',
        keyword: '',
        match_type: 'exact',
        content: '',
        sort_order: 0,
        status: 1
    })
    formVisible.value = true
}

const handleEdit = (row: any) => {
    Object.assign(form, row)
    formVisible.value = true
}

const handleDelete = async (row: any) => {
    try {
        await ElMessageBox.confirm(t('channel.deleteAutoReplyConfirm'), t('channel.tip'), { type: 'warning' })
        await autoReplyApi.delete(row.id)
        ElMessage.success(t('channel.deleteSuccess'))
        getList()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('channel.deleteFailed'))
    }
}

const handleSubmit = async () => {
    try {
        await formRef.value?.validate()
        submitting.value = true
        if (form.id) {
            await autoReplyApi.update(form.id, form)
        } else {
            await autoReplyApi.create(form)
        }
        ElMessage.success(form.id ? t('channel.updateSuccess') : t('channel.createSuccess'))
        formVisible.value = false
        getList()
    } catch (e: any) {
        if (e?.message) ElMessage.error(e.message)
    } finally {
        submitting.value = false
    }
}

onMounted(() => getList())
</script>

<style lang="scss" scoped>
.auto-reply {
    .search-card {
        margin-bottom: 16px;
    }
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
</style>
