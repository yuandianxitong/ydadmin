<template>
  <view class="diy-renderer" :style="rootStyle">
    <template v-for="(c, i) in visibleComponents" :key="c.id">
      <diy-float-button v-if="c.type === 'float-button'" :props="c.props" />
      <view v-else :style="wrap(c)">
        <diy-banner v-if="c.type === 'banner'" :props="c.props" />
        <diy-nav-grid v-else-if="c.type === 'nav-grid'" :props="c.props" />
        <diy-category-nav v-else-if="c.type === 'category-nav'" :props="c.props" />
        <diy-rich-text v-else-if="c.type === 'rich-text'" :props="c.props" />
        <diy-title-bar v-else-if="c.type === 'title-bar'" :props="c.props" />
        <diy-divider v-else-if="c.type === 'divider'" :props="c.props" />
        <diy-image-ad v-else-if="c.type === 'image-ad'" :props="c.props" />
        <diy-image-cube v-else-if="c.type === 'image-cube'" :props="c.props" />
        <diy-video v-else-if="c.type === 'video'" :props="c.props" />
        <diy-notice v-else-if="c.type === 'notice'" :props="c.props" />
        <diy-search-bar v-else-if="c.type === 'search-bar'" :props="c.props" />
        <diy-user-info-card v-else-if="c.type === 'user-info-card'" :props="c.props" :is-first="i === 0" />
        <diy-service-menu v-else-if="c.type === 'service-menu'" :props="c.props" />
        <diy-content-list v-else-if="c.type === 'content-list'" :props="c.props" />
      </view>
    </template>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import DiyBanner from './diy-banner.vue'
import DiyCategoryNav from './diy-category-nav.vue'
import DiyContentList from './diy-content-list.vue'
import DiyNavGrid from './diy-nav-grid.vue'
import DiyRichText from './diy-rich-text.vue'
import DiyTitleBar from './diy-title-bar.vue'
import DiyDivider from './diy-divider.vue'
import DiyImageAd from './diy-image-ad.vue'
import DiyImageCube from './diy-image-cube.vue'
import DiyVideo from './diy-video.vue'
import DiyNotice from './diy-notice.vue'
import DiySearchBar from './diy-search-bar.vue'
import DiyFloatButton from './diy-float-button.vue'
import DiyUserInfoCard from './diy-user-info-card.vue'
import DiyServiceMenu from './diy-service-menu.vue'
import { wrapComponentStyle } from './styleUtils'
import { filterVisible } from './filterVisible'
import { useTheme } from '@/hooks/useTheme'

const props = defineProps<{
  components?: Array<{ id: string; type: string; props: Record<string, any>; hidden?: boolean }>
  pageSettings?: Record<string, any>
}>()

const { cssVars } = useTheme()
const visibleComponents = computed(() => filterVisible(props.components))
const rootStyle = computed(() => {
  const s = props.pageSettings ?? {}
  const css: Record<string, string> = { ...cssVars.value }
  if (s.background_color) css.backgroundColor = s.background_color
  if (s.background_image) css.backgroundImage = `url(${s.background_image})`
  return css
})

function wrap(c: { type: string; props?: Record<string, any> }) {
  return wrapComponentStyle(c.type, c.props?.componentStyle, 'uniapp')
}
</script>
