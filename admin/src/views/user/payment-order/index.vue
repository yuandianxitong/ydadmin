<template>
    <div class="payment-order-container">
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="t('paymentOrder.orderNo')">
                    <el-input v-model="searchForm.order_no" clearable style="width: 220px" />
                </el-form-item>
                <el-form-item :label="t('paymentOrder.status')">
                    <el-select v-model="searchForm.status" clearable style="width: 140px">
                        <el-option :label="t('paymentOrder.statusPending')" value="pending" />
                        <el-option :label="t('paymentOrder.statusPaid')" value="paid" />
                        <el-option :label="t('paymentOrder.statusClosed')" value="closed" />
                        <el-option :label="t('paymentOrder.statusRefunded')" value="refunded" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('paymentOrder.channel')">
                    <el-select v-model="searchForm.channel" clearable style="width: 140px">
                        <el-option :label="t('paymentOrder.channelWechat')" value="wechat" />
                        <el-option :label="t('paymentOrder.channelAlipay')" value="alipay" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">{{ t('common.search') }}</el-button>
                    <el-button @click="resetSearch">{{ t('common.reset') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card class="table-card" shadow="never">
            <div class="table-title">{{ t('paymentOrder.title') }}</div>
            <el-table v-loading="loading" :data="list">
                <el-table-column :label="t('paymentOrder.orderNo')" prop="order_no" min-width="200" />
                <el-table-column :label="t('paymentOrder.user')" min-width="140">
                    <template #default="{ row }">
                        {{ row.user_nickname || row.user_id }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('paymentOrder.amount')" prop="amount" width="110" />
                <el-table-column :label="t('paymentOrder.refunded')" prop="refunded_amount" width="110" />
                <el-table-column :label="t('paymentOrder.channel')" prop="channel" width="100" />
                <el-table-column :label="t('paymentOrder.status')" prop="status" width="110" />
                <el-table-column :label="t('paymentOrder.createdAt')" prop="created_at" width="170" />
                <el-table-column :label="t('common.operation')" width="160" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-has-perm="['payment.order.detail']"
                            type="primary"
                            size="small"
                            text
                            @click="openDetail(row.order_no)"
                        >
                            {{ t('common.detail') }}
                        </el-button>
                        <el-button
                            v-if="row.status === 'paid'"
                            v-has-perm="['payment.order.refund']"
                            type="warning"
                            size="small"
                            text
                            @click="openRefund(row as PaymentOrderItem)"
                        >
                            {{ t('paymentOrder.refund') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                class="pagination"
                @size-change="handleSizeChange"
                @current-change="handlePageChange"
            />
        </el-card>

        <el-drawer v-model="detailVisible" :title="t('paymentOrder.detailTitle')" size="480px">
            <template v-if="detail">
                <p>{{ t('paymentOrder.orderNo') }}：{{ detail.order_no }}</p>
                <p>{{ t('paymentOrder.amount') }}：{{ detail.amount }}</p>
                <p>{{ t('paymentOrder.refunded') }}：{{ detail.refunded_amount }}</p>
                <p>{{ t('paymentOrder.status') }}：{{ detail.status }}</p>
                <el-table :data="detail.refunds" class="mt-4">
                    <el-table-column :label="t('paymentOrder.refundNo')" prop="refund_no" min-width="160" />
                    <el-table-column :label="t('paymentOrder.amount')" prop="amount" width="90" />
                    <el-table-column :label="t('paymentOrder.status')" prop="status" width="100" />
                </el-table>
            </template>
        </el-drawer>

        <el-dialog v-model="refundVisible" :title="t('paymentOrder.refund')" width="420px">
            <el-form :model="refundForm" label-width="90px">
                <el-form-item :label="t('paymentOrder.amount')">
                    <el-input v-model="refundForm.amount" />
                </el-form-item>
                <el-form-item :label="t('paymentOrder.reason')">
                    <el-input v-model="refundForm.reason" type="textarea" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="refundVisible = false">{{ t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="refunding" @click="submitRefund">
                    {{ t('common.confirm') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts" name="UserPaymentOrder">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { paymentOrderApi } from '@/api/payment'
import { useListPage } from '@/hooks/useListPage'
import type { PaymentOrderDetail, PaymentOrderItem } from '@/types/payment'
import feedback from '@/utils/feedback'

const { t } = useI18n()

const {
    list,
    loading,
    pagination,
    searchForm,
    getList,
    handleSearch,
    resetSearch,
    handleSizeChange,
    handlePageChange
} = useListPage<PaymentOrderItem, { order_no: string; status?: string; channel?: string }>({
    fetchFn: (params) => paymentOrderApi.list(params),
    defaultSearchForm: { order_no: '', status: undefined, channel: undefined }
})

const detailVisible = ref(false)
const detail = ref<PaymentOrderDetail | null>(null)
const refundVisible = ref(false)
const refunding = ref(false)
const refundForm = ref({ order_no: '', amount: '', reason: '' })

const openDetail = async (orderNo: string) => {
    try {
        const res = await paymentOrderApi.detail(orderNo)
        detail.value = res.data
        detailVisible.value = true
    } catch {
        // 拦截器已经弹过错误提示，这里只是别让 Promise 悬着
    }
}

const openRefund = (row: PaymentOrderItem) => {
    const remain = (Number(row.amount) - Number(row.refunded_amount)).toFixed(2)
    refundForm.value = { order_no: row.order_no, amount: remain, reason: '' }
    refundVisible.value = true
}

const submitRefund = async () => {
    refunding.value = true
    try {
        await paymentOrderApi.refund(refundForm.value)
        feedback.msgSuccess(t('paymentOrder.refundSubmitted'))
        refundVisible.value = false
        getList()
        if (detail.value?.order_no === refundForm.value.order_no) {
            await openDetail(refundForm.value.order_no)
        }
    } finally {
        refunding.value = false
    }
}
</script>
