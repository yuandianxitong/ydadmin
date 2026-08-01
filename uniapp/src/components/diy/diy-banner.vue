<template>
  <swiper
    class="diy-banner"
    :autoplay="p.autoplay !== false"
    :interval="p.interval || 3000"
    circular
    :style="{ height: swiperHeight + 'rpx' }"
  >
    <swiper-item v-for="(it, i) in items" :key="i">
      <view v-if="it.link" class="diy-banner__link" @tap="diyNavigate(it.link)">
        <image :src="img(it.image)" mode="widthFix" class="diy-banner__img" @load="onImgLoad" />
      </view>
      <image v-else :src="img(it.image)" mode="widthFix" class="diy-banner__img" @load="onImgLoad" />
    </swiper-item>
  </swiper>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { diyNavigate } from './navigate'
import { useAppStore } from '@/store/app.store'

const props = defineProps<{ props: Record<string, any> }>()
const appStore = useAppStore()
const p = computed(() => props.props || {})
const items = computed(() => (props.props?.items as any[]) || [])
const swiperHeight = ref(Number(props.props?.height) || 300)

const measured = ref(false)

watch(
  () => [props.props?.height, items.value?.[0]?.image] as const,
  ([h]) => {
    measured.value = false
    swiperHeight.value = Number(h) || 300
  },
)

function onImgLoad(e: any) {
  const w = Number(e?.detail?.width || 0)
  const h = Number(e?.detail?.height || 0)
  if (w <= 0 || h <= 0 || measured.value) return
  // 按 750 设计稿宽度等比换算高度（widthFix = 适应宽度）
  swiperHeight.value = Math.round((h / w) * 750)
  measured.value = true
}

function img(url?: string): string {
  void appStore.isConfigLoaded
  void appStore.config.site_url
  return url ? appStore.getImageUrl(url) : ''
}
</script>

<style lang="scss" scoped>
.diy-banner {
  width: 100%;
  &__link {
    display: block;
    width: 100%;
  }
  &__img {
    width: 100%;
    display: block;
    vertical-align: top;
  }
}
</style>
