<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ type: string; iconUrl?: string }>()

// 已纳入本地 svg 图标库（src/assets/icons/diy-*.svg → i-svg:diy-*）的内置组件类型
const KNOWN_TYPES = new Set([
    'banner',
    'nav-grid',
    'category-nav',
    'rich-text',
    'title-bar',
    'divider',
    'image-ad',
    'image-cube',
    'video',
    'notice',
    'search-bar',
    'float-button',
    'user-info-card',
    'service-menu',
    'content-list'
])

// 插件 widget 图标（协议扩展：diy_widgets[].icon → 插件 logo → 占位，回退链在后端拼好）：
// svg 用 CSS mask 渲染（跟随 currentColor 主题着色，与内置 i-svg 线性图标视觉一致）；
// 位图（png 等，通常是插件 logo 回退）用 <img> 直出保留原色。
const isSvg = computed(() => !!props.iconUrl && /\.svg(\?|$)/i.test(props.iconUrl))
const maskStyle = computed(() =>
    isSvg.value
        ? { maskImage: `url("${props.iconUrl}")`, WebkitMaskImage: `url("${props.iconUrl}")` }
        : undefined
)

// 内置类型走本地图标库；无自带图标的插件/未知类型回退通用占位图标
const iconClass = computed(() =>
    KNOWN_TYPES.has(props.type) ? `i-svg:diy-${props.type}` : 'i-svg:diy-default'
)
</script>

<template>
    <span
        v-if="iconUrl && isSvg"
        class="comp-glyph comp-glyph--mask"
        :style="maskStyle"
        aria-hidden="true"
    />
    <img
        v-else-if="iconUrl"
        class="comp-glyph comp-glyph--img"
        :src="iconUrl"
        alt=""
        aria-hidden="true"
    />
    <i v-else class="comp-glyph" :class="iconClass" aria-hidden="true" />
</template>

<style scoped>
.comp-glyph {
    display: inline-block;
    width: 32px;
    height: 32px;
    font-size: 32px;
}
.comp-glyph--mask {
    background-color: currentColor;
    mask-repeat: no-repeat;
    mask-position: center;
    mask-size: contain;
    -webkit-mask-repeat: no-repeat;
    -webkit-mask-position: center;
    -webkit-mask-size: contain;
}
.comp-glyph--img {
    object-fit: contain;
}
</style>
