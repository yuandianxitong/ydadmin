<template>
    <!-- 空数据：灰底圆角占位；有数据：图标直角无背景 -->
    <div v-if="isScroll" class="pv-cat pv-cat--scroll" :class="{ 'is-empty': !hasItems }">
        <div v-for="(it, i) in displayItems" :key="i" class="pv-cat__item pv-cat__item--scroll">
            <img v-if="it.icon" :src="img(it.icon)" class="pv-cat__icon pv-cat__icon--real" />
            <div v-else class="pv-cat__icon" :class="{ 'pv-cat__icon--slot': hasItems }"></div>
            <span>{{ it.title || `分类${i + 1}` }}</span>
        </div>
    </div>
    <div v-else class="pv-cat" :class="{ 'is-empty': !hasItems }" :style="{ gridTemplateColumns: `repeat(${cols}, 1fr)` }">
        <div v-for="(it, i) in displayItems" :key="i" class="pv-cat__item">
            <img v-if="it.icon" :src="img(it.icon)" class="pv-cat__icon pv-cat__icon--real" />
            <div v-else class="pv-cat__icon" :class="{ 'pv-cat__icon--slot': hasItems }"></div>
            <span>{{ it.title || `分类${i + 1}` }}</span>
        </div>
    </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { useAppStore } from '@/store/modules/app.store'

const props = defineProps<{ props: Record<string, any> }>()
const appStore = useAppStore()
const isScroll = computed(() => props.props?.style === 'scroll')
const cols = computed(() => props.props?.columns || 5)
const rows = computed(() => props.props?.rows || 2)
const hasItems = computed(() => Array.isArray(props.props?.items) && props.props.items.length > 0)
// 空数据 → rows×columns 灰圆占位（横滑单行 columns+2 个）；有数据用真实项
const displayItems = computed<{ icon?: string; title?: string }[]>(() => {
    const real = Array.isArray(props.props?.items) ? props.props.items : []
    if (real.length) return real
    const n = isScroll.value ? cols.value + 2 : rows.value * cols.value
    return Array.from({ length: n }, () => ({}))
})
function img(url?: string) {
    return url ? (appStore.getImageUrl?.(url) || url) : ''
}
</script>
<style scoped>
.pv-cat {
    display: grid;
    gap: 8px;
    padding: 12px;
    text-align: center;
}
.pv-cat--scroll {
    display: flex;
    overflow: hidden;
}
.pv-cat__item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    color: #666;
}
/* 尺寸对齐 uniapp diy-category-nav：横滑项 128rpx=64px、图标 88rpx=44px */
.pv-cat__item--scroll {
    flex-shrink: 0;
    width: 64px;
}
.pv-cat__icon {
    width: 44px;
    height: 44px;
    border-radius: 0;
    background: transparent;
}
/* 未添加项目：灰底圆角占位 */
.pv-cat.is-empty .pv-cat__icon {
    border-radius: 50%;
    background: #f0f2f5;
}
/* 有数据真实图标：直角、无背景 */
.pv-cat__icon--real {
    object-fit: cover;
    border-radius: 0;
    background: transparent;
}
/* 有数据但缺 icon 的空槽：直角、无圆形灰底 */
.pv-cat__icon--slot {
    border-radius: 0;
    background: transparent;
}
</style>
