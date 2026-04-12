<template>
  <div>
    <h2 class="text-xl font-bold text-gray-900 mb-6">我的积分</h2>

    <!-- Points overview card -->
    <div class="card p-6 mb-6">
      <div class="text-sm text-gray-500 mb-1">当前积分</div>
      <div class="text-3xl font-bold text-indigo-600">{{ points }}</div>
    </div>

    <!-- Points log list -->
    <div class="card">
      <div class="px-6 py-4 border-b border-gray-100 font-semibold text-gray-900">积分明细</div>
      <div v-if="logsLoading" class="text-center py-10 text-gray-400">加载中...</div>
      <template v-else>
        <div v-if="logs.length === 0" class="text-center py-10 text-gray-400">暂无记录</div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 border-b border-gray-100">
              <th class="px-6 py-3 font-medium">时间</th>
              <th class="px-6 py-3 font-medium">类型</th>
              <th class="px-6 py-3 font-medium">变动积分</th>
              <th class="px-6 py-3 font-medium">积分余额</th>
              <th class="px-6 py-3 font-medium">备注</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in logs" :key="item.id" class="border-b border-gray-50 hover:bg-gray-50/50">
              <td class="px-6 py-3 text-gray-500">{{ item.created_at }}</td>
              <td class="px-6 py-3">
                <span class="inline-block px-2 py-0.5 text-xs rounded" :class="item.points >= 0 ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'">
                  {{ item.type_text }}
                </span>
              </td>
              <td class="px-6 py-3 font-medium" :class="item.points >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ item.points >= 0 ? '+' : '' }}{{ item.points }}
              </td>
              <td class="px-6 py-3 text-gray-700">{{ item.after_points }}</td>
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
  </div>
</template>

<script setup lang="ts">
import { userApi } from '~/api/user'
import type { PointsLogItem } from '~/api/user'

const refreshUserInfo = inject<() => Promise<void>>('refreshUserInfo')

// Points
const points = ref(0)

// Logs
const logs = ref<PointsLogItem[]>([])
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

async function fetchPoints() {
  try {
    const res = await userApi.getPoints()
    if (res.code === 200) {
      points.value = res.data.points
    }
  } catch { /* ignore */ }
}

async function fetchLogs() {
  logsLoading.value = true
  try {
    const res = await userApi.getPointsLogs({ page_no: page.value, page_size: pageSize })
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

onMounted(() => {
  fetchPoints()
  fetchLogs()
  refreshUserInfo?.()
})
</script>
