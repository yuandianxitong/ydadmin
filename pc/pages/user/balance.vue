<template>
  <div>
    <h2 class="text-xl font-bold text-gray-900 mb-6">我的余额</h2>

    <!-- Balance overview card -->
    <div class="card p-6 mb-6 flex items-center justify-between">
      <div>
        <div class="text-sm text-gray-500 mb-1">当前余额</div>
        <div class="text-3xl font-bold text-amber-600">{{ balance }}</div>
      </div>
      <button class="btn-primary" @click="showRechargeDialog = true">充值</button>
    </div>

    <!-- Balance log list -->
    <div class="card">
      <div class="px-6 py-4 border-b border-gray-100 font-semibold text-gray-900">余额明细</div>
      <div v-if="logsLoading" class="text-center py-10 text-gray-400">加载中...</div>
      <template v-else>
        <div v-if="logs.length === 0" class="text-center py-10 text-gray-400">暂无记录</div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 border-b border-gray-100">
              <th class="px-6 py-3 font-medium">时间</th>
              <th class="px-6 py-3 font-medium">类型</th>
              <th class="px-6 py-3 font-medium">变动金额</th>
              <th class="px-6 py-3 font-medium">余额</th>
              <th class="px-6 py-3 font-medium">备注</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in logs" :key="item.id" class="border-b border-gray-50 hover:bg-gray-50/50">
              <td class="px-6 py-3 text-gray-500">{{ item.created_at }}</td>
              <td class="px-6 py-3">
                <span class="inline-block px-2 py-0.5 text-xs rounded" :class="Number(item.amount) >= 0 ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'">
                  {{ item.type_text }}
                </span>
              </td>
              <td class="px-6 py-3 font-medium" :class="Number(item.amount) >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ Number(item.amount) >= 0 ? '+' : '' }}{{ item.amount }}
              </td>
              <td class="px-6 py-3 text-gray-700">{{ item.after_balance }}</td>
              <td class="px-6 py-3 text-gray-500">{{ item.remark || '-' }}</td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="total > pageSize" class="flex items-center justify-center gap-2 px-6 py-4 border-t border-gray-100">
          <button
            :disabled="page <= 1"
            class="px-3 py-1.5 text-sm rounded border border-gray-200 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
            @click="goPage(page - 1)"
          >
            上一页
          </button>
          <template v-for="p in displayPages" :key="p">
            <button
              class="w-8 h-8 text-sm rounded border transition-colors"
              :class="p === page ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white' : 'border-gray-200 hover:bg-gray-50'"
              @click="goPage(p)"
            >
              {{ p }}
            </button>
          </template>
          <button
            :disabled="page >= totalPages"
            class="px-3 py-1.5 text-sm rounded border border-gray-200 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
            @click="goPage(page + 1)"
          >
            下一页
          </button>
        </div>
      </template>
    </div>

    <!-- Recharge dialog -->
    <NModal v-model:show="showRechargeDialog" preset="card" title="余额充值" class="max-w-500px">
      <!-- Preset amounts -->
      <div class="mb-5">
        <div class="text-sm text-gray-600 mb-2">选择充值金额</div>
        <div class="grid grid-cols-3 gap-3">
          <button
            v-for="amt in presetAmounts"
            :key="amt"
            class="py-3 rounded-lg border-2 text-center font-medium transition-colors"
            :class="selectedAmount === amt && !useCustom
              ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/5 text-[var(--color-primary)]'
              : 'border-gray-200 text-gray-700 hover:border-gray-300'"
            @click="selectPreset(amt)"
          >
            {{ amt }} 元
          </button>
        </div>
      </div>

      <!-- Custom amount -->
      <div class="mb-5">
        <div class="text-sm text-gray-600 mb-2">自定义金额</div>
        <NInputNumber
          v-model:value="customAmount"
          :min="1"
          :max="10000"
          :precision="2"
          placeholder="请输入金额"
          class="w-full"
          @focus="useCustom = true"
          @update:value="useCustom = true"
        />
      </div>

      <!-- Payment method -->
      <div class="mb-6">
        <div class="text-sm text-gray-600 mb-2">支付方式</div>
        <div class="flex gap-3">
          <button
            class="flex-1 flex items-center justify-center gap-2 py-3 rounded-lg border-2 transition-colors"
            :class="payChannel === 'wechat' ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-200 text-gray-600 hover:border-gray-300'"
            @click="payChannel = 'wechat'"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098 10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348z" /></svg>
            微信支付
          </button>
          <button
            class="flex-1 flex items-center justify-center gap-2 py-3 rounded-lg border-2 transition-colors"
            :class="payChannel === 'alipay' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600 hover:border-gray-300'"
            @click="payChannel = 'alipay'"
          >
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M21.422 15.358c-1.49-.563-3.154-1.195-4.943-1.895a18.6 18.6 0 0 0 1.474-4.206h-3.84V7.394h4.846V6.37h-4.846V3.6h-2.387c-.26 0-.26.26-.26.26v2.51H6.62v1.024h4.846v1.863H7.695v1.024h8.49a15.456 15.456 0 0 1-.955 2.67c-2.322-.87-4.45-1.444-6.14-.946-3.49 1.033-4.231 4.206-2.352 6.167 1.879 1.96 5.554 1.96 7.588-.316a17.746 17.746 0 0 0 2.238-3.52c2.07.94 5.647 2.647 5.647 2.647L24 15.996s-.836-.13-2.578-.638zM10.34 19.793c-2.774 1.46-5.483.63-5.62-1.643-.087-1.456.813-3.09 2.906-3.513 2.093-.424 4.01.465 5.32 1.19a14.242 14.242 0 0 1-2.606 3.966z" /></svg>
            支付宝
          </button>
        </div>
      </div>

      <!-- Submit -->
      <button
        :disabled="recharging || finalAmount <= 0"
        class="w-full btn-primary justify-center"
        :class="{ 'opacity-60 cursor-not-allowed': recharging || finalAmount <= 0 }"
        @click="handleRecharge"
      >
        {{ recharging ? '提交中...' : `确认充值 ${finalAmount > 0 ? finalAmount + ' 元' : ''}` }}
      </button>
    </NModal>
  </div>
</template>

<script setup lang="ts">
import { NModal, NInputNumber, useMessage } from 'naive-ui'
import { userApi } from '~/api/user'
import { get } from '~/composables/useRequest'
import type { BalanceLogItem } from '~/api/user'

const message = useMessage()
const refreshUserInfo = inject<() => Promise<void>>('refreshUserInfo')

// Balance
const balance = ref('0.00')

// Logs
const logs = ref<BalanceLogItem[]>([])
const logsLoading = ref(true)
const page = ref(1)
const pageSize = 15
const total = ref(0)

const totalPages = computed(() => Math.ceil(total.value / pageSize))
const displayPages = computed(() => {
  const pages: number[] = []
  const tp = totalPages.value
  const cp = page.value
  let start = Math.max(1, cp - 2)
  let end = Math.min(tp, cp + 2)
  if (end - start < 4) {
    if (start === 1) end = Math.min(tp, start + 4)
    else start = Math.max(1, end - 4)
  }
  for (let i = start; i <= end; i++) pages.push(i)
  return pages
})

// Recharge dialog
const showRechargeDialog = ref(false)
const presetAmounts = [10, 30, 50, 100, 200, 500]
const selectedAmount = ref(50)
const customAmount = ref<number | null>(null)
const useCustom = ref(false)
const payChannel = ref<'wechat' | 'alipay'>('wechat')
const recharging = ref(false)

const finalAmount = computed(() => {
  if (useCustom.value && customAmount.value && customAmount.value > 0) {
    return customAmount.value
  }
  return selectedAmount.value
})

function selectPreset(amt: number) {
  selectedAmount.value = amt
  useCustom.value = false
  customAmount.value = null
}

async function fetchBalance() {
  try {
    const res = await userApi.getBalance()
    if (res.code === 200) {
      balance.value = res.data.balance
    }
  } catch { /* ignore */ }
}

async function fetchLogs() {
  logsLoading.value = true
  try {
    const res = await userApi.getBalanceLogs({ page: page.value, page_size: pageSize })
    if (res.code === 200) {
      logs.value = res.data.list
      total.value = res.data.total
    }
  } finally {
    logsLoading.value = false
  }
}

function goPage(p: number) {
  if (p < 1 || p > totalPages.value) return
  page.value = p
  fetchLogs()
}

function pollPayment(orderNo: string) {
  const timer = setInterval(async () => {
    try {
      const res = await get('/api/payment/query', { order_no: orderNo })
      if (res.code === 200 && res.data.status === 'paid') {
        clearInterval(timer)
        showRechargeDialog.value = false
        recharging.value = false
        fetchBalance()
        fetchLogs()
        refreshUserInfo?.()
        message.success('充值成功')
      }
    } catch { /* ignore polling errors */ }
  }, 2000)
  // Clear after 5 minutes timeout
  setTimeout(() => clearInterval(timer), 300000)
}

async function handleRecharge() {
  if (finalAmount.value <= 0) return
  recharging.value = true
  try {
    const res = await userApi.recharge({ amount: finalAmount.value, channel: payChannel.value })
    if (res.code === 200 && res.data) {
      const data = res.data as any
      // Open payment URL in new window
      if (data.pay_url) {
        window.open(data.pay_url, '_blank')
      }
      // Start polling for payment status
      if (data.order_no) {
        pollPayment(data.order_no)
      }
    } else {
      message.error(res.message || '充值失败')
      recharging.value = false
    }
  } catch {
    message.error('网络错误，请重试')
    recharging.value = false
  }
}

onMounted(() => {
  fetchBalance()
  fetchLogs()
})
</script>
