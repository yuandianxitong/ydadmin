<template>
  <view class="diy-cl">
    <view v-if="sectionTitle" class="diy-cl__head">
      <text class="diy-cl__title">{{ sectionTitle }}</text>
      <text v-if="moreLink" class="diy-cl__more" @tap="diyNavigate(moreLink)">更多</text>
    </view>

    <view v-if="loading" class="diy-cl__hint">加载中...</view>
    <view v-else-if="!items.length" class="diy-cl__hint">暂无文章</view>

    <view v-else-if="layout === 'grid'" class="diy-cl__grid">
      <view v-for="it in items" :key="it.id" class="diy-cl__card" @tap="goDetail(it.id)">
        <image v-if="showCover && it.cover" :src="it.cover" mode="aspectFill" class="diy-cl__card-img" />
        <view v-else-if="showCover" class="diy-cl__card-img diy-cl__ph" />
        <view class="diy-cl__card-body">
          <text class="diy-cl__card-title">{{ it.title }}</text>
          <text v-if="showSummary && it.summary" class="diy-cl__card-desc">{{ it.summary }}</text>
          <text v-if="showDate && it.date" class="diy-cl__card-meta">{{ it.date }}</text>
        </view>
      </view>
    </view>

    <view v-else class="diy-cl__list">
      <view v-for="it in items" :key="it.id" class="diy-cl__row" @tap="goDetail(it.id)">
        <image v-if="showCover && it.cover" :src="it.cover" mode="aspectFill" class="diy-cl__row-img" />
        <view v-else-if="showCover" class="diy-cl__row-img diy-cl__ph" />
        <view class="diy-cl__row-body">
          <text class="diy-cl__row-title">{{ it.title }}</text>
          <text v-if="showSummary && it.summary" class="diy-cl__row-desc">{{ it.summary }}</text>
          <text v-if="showDate && it.date" class="diy-cl__row-meta">{{ it.date }}</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { articleApi, type ArticleItem } from '@/api/article'
import { useAppStore } from '@/store/app.store'
import { diyNavigate } from './navigate'

interface ListItem {
  id: number
  title: string
  cover: string
  summary: string
  date: string
}

const props = defineProps<{ props: Record<string, any> }>()
const appStore = useAppStore()

const loading = ref(false)
const items = ref<ListItem[]>([])

const sectionTitle = computed(() => String(props.props?.section_title || '').trim())
const moreLink = computed(() => String(props.props?.more_link || '').trim())
const layout = computed(() => (props.props?.layout === 'grid' ? 'grid' : 'list'))
const showCover = computed(() => props.props?.show_cover !== false)
const showSummary = computed(() => props.props?.show_summary !== false)
const showDate = computed(() => props.props?.show_date !== false)

function formatDate(raw?: string) {
  if (!raw) return ''
  return String(raw).slice(0, 10)
}

function mapItem(row: ArticleItem): ListItem {
  const cover = row.cover ? appStore.getImageUrl(row.cover) : ''
  return {
    id: Number(row.id),
    title: String(row.title || ''),
    cover,
    summary: String(row.summary || ''),
    date: formatDate((row as any).publish_at || row.published_at || row.created_at),
  }
}

async function load() {
  loading.value = true
  try {
    const limit = Math.max(1, Math.min(20, Number(props.props?.limit) || 6))
    const params: { page_no: number; page_size: number; category_id?: number } = {
      page_no: 1,
      page_size: limit,
    }
    if (props.props?.source === 'category' && Number(props.props?.category_id) > 0) {
      params.category_id = Number(props.props.category_id)
    }
    const res = await articleApi.getList(params)
    const list = (res?.list || []) as ArticleItem[]
    items.value = list.map(mapItem)
  } catch {
    items.value = []
  } finally {
    loading.value = false
  }
}

function goDetail(id: number) {
  if (!id) return
  uni.navigateTo({ url: `/modules/article/pages/article-detail?id=${id}` })
}

watch(
  () =>
    [
      props.props?.source,
      props.props?.category_id,
      props.props?.limit,
    ].join('|'),
  () => load(),
  { immediate: true }
)
</script>

<style scoped lang="scss">
@import '@/styles/variables.scss';

.diy-cl { width: 100%; background: #fff; }
.diy-cl__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx 24rpx 12rpx;
}
.diy-cl__title { font-size: 32rpx; font-weight: 600; color: #172033; }
.diy-cl__more { font-size: 24rpx; color: #8a93a2; }
.diy-cl__hint {
  padding: 40rpx 24rpx;
  text-align: center;
  font-size: 24rpx;
  color: #9aa4b2;
}
.diy-cl__ph { background: linear-gradient(135deg, #f2f3f5 0%, #e5e6eb 100%); }
.diy-cl__grid {
  display: flex;
  flex-wrap: wrap;
  padding: 8rpx 12rpx 20rpx;
  box-sizing: border-box;
}
.diy-cl__card {
  width: 50%;
  box-sizing: border-box;
  padding: 8rpx;
}
.diy-cl__card-img {
  width: 100%;
  height: 200rpx;
  border-radius: 8rpx;
  display: block;
}
.diy-cl__card-body { padding: 8rpx 4rpx; }
.diy-cl__card-title {
  display: block;
  font-size: 26rpx;
  color: #222;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.diy-cl__card-desc, .diy-cl__card-meta {
  display: block;
  margin-top: 6rpx;
  font-size: 22rpx;
  color: #8a93a2;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.diy-cl__list { padding: 0 24rpx 16rpx; }
.diy-cl__row {
  display: flex;
  align-items: center;
  gap: 20rpx;
  padding: 20rpx 0;
  border-bottom: 1rpx solid #f0f1f3;
}
.diy-cl__row:last-child { border-bottom: none; }
.diy-cl__row-img {
  width: 128rpx;
  height: 128rpx;
  border-radius: 8rpx;
  flex-shrink: 0;
}
.diy-cl__row-body { flex: 1; min-width: 0; }
.diy-cl__row-title {
  display: block;
  font-size: 28rpx;
  color: #222;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.diy-cl__row-desc, .diy-cl__row-meta {
  display: block;
  margin-top: 8rpx;
  font-size: 22rpx;
  color: #8a93a2;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
