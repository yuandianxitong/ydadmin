<template>
    <div class="pv-uic" :class="{ 'pv-uic--first': isFirst }">
        <div class="pv-uic__head">
            <div class="pv-uic__avatar" />
            <div class="pv-uic__meta">
                <div class="pv-uic__name">用户昵称</div>
                <div class="pv-uic__mobile">138****8888</div>
            </div>
        </div>
        <div v-if="props.show_assets" class="pv-uic__assets">
            <div v-for="(a, i) in assets" :key="i" class="pv-uic__asset">
                <b>{{ a.stat_key ? '**' : '-' }}</b><span>{{ a.label || '资产' }}</span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { defaultAssets } from '../useEditor'

const p = defineProps<{ props: Record<string, any>; isFirst?: boolean }>()
const assets = computed(() => {
    const list = p.props.assets
    return Array.isArray(list) && list.length ? list : defaultAssets()
})
</script>

<style scoped lang="scss">
.pv-uic {
    padding: 20px 16px;
    color: #fff;
    background: linear-gradient(135deg, var(--color-brand), var(--color-brand-active));

    // 首位时模拟手机状态栏安全距离（375 宽画布按 iOS 44px 电池条近似 24px）
    &--first { padding-top: 44px; }

    &__head { display: flex; align-items: center; gap: 12px; }
    &__avatar { width: 52px; height: 52px; border-radius: 50%; background: rgba(255, 255, 255, 0.35); flex: none; }
    &__name { font-size: 15px; font-weight: 600; }
    &__mobile { font-size: 12px; opacity: 0.85; margin-top: 2px; }
    &__assets { display: flex; margin-top: 14px; }
    &__asset { flex: 1; display: flex; flex-direction: column; align-items: center; font-size: 11px; opacity: 0.95;
        b { font-size: 15px; } }
}
</style>
