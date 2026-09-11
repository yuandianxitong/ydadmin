<template>
  <view class="ledger-list">
    <view
      v-for="item in items"
      :key="item.id"
      class="ledger-item"
    >
      <view class="ledger-item__left">
        <text class="ledger-item__type">{{ item.type_text }}</text>
        <text class="ledger-item__remark">{{ item.remark || '无备注' }}</text>
      </view>
      <view class="ledger-item__right">
        <text
          class="ledger-item__amount"
          :class="amountValue(item) >= 0 ? 'is-income' : 'is-expense'"
        >
          {{ formatAmount(item) }}
        </text>
        <text class="ledger-item__time">{{ item.created_at }}</text>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
/**
 * 账目/积分流水列表组件
 *
 * 替代 balance.vue 和 points.vue 中各自复制的 100 行重复模板和样式。
 * 通过 `valueKey` 和 `valueMode` 支持不同字段名和显示模式。
 */
interface LedgerItem {
  id: number
  type_text?: string
  remark?: string
  created_at?: string
  [key: string]: any
}

const props = withDefaults(
  defineProps<{
    /** 列表数据 */
    items: LedgerItem[]
    /** 金额字段名（balance 用 `amount`, points 用 `points`） */
    valueKey?: string
    /** 显示模式：currency 保留小数并带正负号，integer 整数 */
    valueMode?: 'currency' | 'integer'
  }>(),
  {
    valueKey: 'amount',
    valueMode: 'currency',
  }
)

/** 取值并转数字 */
function amountValue(item: LedgerItem): number {
  const raw = item[props.valueKey]
  return typeof raw === 'number' ? raw : parseFloat(raw ?? '0') || 0
}

/** 格式化显示 */
function formatAmount(item: LedgerItem): string {
  const n = amountValue(item)
  if (props.valueMode === 'integer') {
    return (n >= 0 ? '+' : '') + n
  }
  const raw = item[props.valueKey]
  return (n >= 0 ? '+' : '') + (typeof raw === 'string' ? raw : String(n))
}
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.ledger-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24rpx 0;
  border-bottom: 1rpx solid $border-color;

  &:last-child {
    border-bottom: none;
  }

  &__left {
    flex: 1;
  }

  &__type {
    display: block;
    font-size: 28rpx;
    color: $text-color;
    font-weight: 500;
    margin-bottom: 8rpx;
  }

  &__remark {
    display: block;
    font-size: 24rpx;
    color: $text-color-secondary;
  }

  &__right {
    text-align: right;
    flex-shrink: 0;
    margin-left: 20rpx;
  }

  &__amount {
    display: block;
    font-size: 30rpx;
    font-weight: 600;
    margin-bottom: 8rpx;

    &.is-income {
      color: $success-color;
    }

    &.is-expense {
      color: $danger-color;
    }
  }

  &__time {
    display: block;
    font-size: 22rpx;
    color: $text-color-secondary;
  }
}
</style>
