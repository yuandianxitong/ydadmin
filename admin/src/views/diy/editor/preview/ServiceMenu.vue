<template>
    <div class="pv-smenu">
        <div v-for="(it, i) in rows" :key="i" class="pv-smenu__cell">
            <span class="pv-smenu__icon-wrap">
                <img v-if="it.icon" :src="img(it.icon)" class="pv-smenu__icon" alt="" />
                <span v-else class="pv-smenu__icon is-empty" />
                <span v-if="it.badge_key" class="pv-badge-dot" />
            </span>
            <span class="pv-smenu__text">{{ it.text || '菜单项' }}</span>
            <span class="pv-smenu__arrow">›</span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useAppStore } from '@/store/modules/app.store'

const p = defineProps<{ props: Record<string, any> }>()
const appStore = useAppStore()
const rows = computed(() => {
    const items = Array.isArray(p.props.items) ? p.props.items : []
    return items.length ? items : [{ icon: '', text: '菜单项', link: '' }, { icon: '', text: '菜单项', link: '' }]
})
function img(url?: string) {
    return url ? (appStore.getImageUrl?.(url) || url) : ''
}
</script>

<style scoped lang="scss">
.pv-smenu {
    background: var(--color-surface);

    &__cell { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-bottom: 1px solid var(--color-divider);
        &:last-child { border-bottom: none; } }
    &__icon-wrap { position: relative; display: inline-flex; flex: none; }
    &__icon { width: 20px; height: 20px; border-radius: 4px; object-fit: cover;
        &.is-empty { background: var(--color-surface-sunken); } }
    &__text { flex: 1; font-size: 13px; color: var(--color-text-primary); }
    &__arrow { color: var(--color-text-disabled); }
    .pv-badge-dot { position: absolute; top: -2px; right: -4px; width: 8px; height: 8px; border-radius: 50%; background: #f56c6c; }
}
</style>
