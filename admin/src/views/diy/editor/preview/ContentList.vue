<template>
    <div class="pv-cl">
        <div v-if="sectionTitle" class="pv-cl__head">
            <strong class="pv-cl__title">{{ sectionTitle }}</strong>
            <span v-if="moreLink" class="pv-cl__more">更多</span>
        </div>

        <div v-if="loading" class="pv-cl__hint">加载中…</div>
        <div v-else-if="!items.length" class="pv-cl__hint">暂无文章，发布文章后将显示在此</div>

        <div v-else-if="layout === 'grid'" class="pv-cl__grid">
            <div v-for="it in items" :key="it.id" class="pv-cl__card">
                <img v-if="showCover && it.cover" :src="img(it.cover)" class="pv-cl__card-img" alt="" />
                <div v-else-if="showCover" class="pv-cl__card-img pv-cl__ph" />
                <div class="pv-cl__card-body">
                    <span class="pv-cl__card-title">{{ it.title }}</span>
                    <span v-if="showSummary && it.summary" class="pv-cl__card-desc">{{ it.summary }}</span>
                    <span v-if="showDate && it.date" class="pv-cl__card-meta">{{ it.date }}</span>
                </div>
            </div>
        </div>

        <div v-else class="pv-cl__list">
            <div v-for="it in items" :key="it.id" class="pv-cl__row">
                <img v-if="showCover && it.cover" :src="img(it.cover)" class="pv-cl__row-img" alt="" />
                <div v-else-if="showCover" class="pv-cl__row-img pv-cl__ph" />
                <div class="pv-cl__row-body">
                    <span class="pv-cl__row-title">{{ it.title }}</span>
                    <span v-if="showSummary && it.summary" class="pv-cl__row-desc">{{ it.summary }}</span>
                    <span v-if="showDate && it.date" class="pv-cl__row-meta">{{ it.date }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import { articleApi } from '@/api/article'
import useAppStore from '@/store/modules/app.store'

interface PreviewItem {
    id: number
    title: string
    cover?: string
    summary?: string
    date?: string
}

const p = defineProps<{ props: Record<string, any> }>()
const appStore = useAppStore()

const loading = ref(false)
const items = ref<PreviewItem[]>([])

const sectionTitle = computed(() => String(p.props?.section_title || '').trim())
const moreLink = computed(() => String(p.props?.more_link || '').trim())
const layout = computed(() => (p.props?.layout === 'grid' ? 'grid' : 'list'))
const showCover = computed(() => p.props?.show_cover !== false)
const showSummary = computed(() => p.props?.show_summary !== false)
const showDate = computed(() => p.props?.show_date !== false)

function img(url: string) {
    return appStore.getImageUrl?.(url) || url
}

function formatDate(raw?: string) {
    if (!raw) return ''
    const t = Date.parse(raw)
    if (Number.isNaN(t)) return String(raw).slice(0, 10)
    const d = new Date(t)
    const pad = (n: number) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

async function load() {
    loading.value = true
    try {
        const limit = Math.max(1, Math.min(20, Number(p.props?.limit) || 6))
        const params: Record<string, any> = {
            page_no: 1,
            page_size: limit,
            status: 1
        }
        if (p.props?.source === 'category' && Number(p.props?.category_id) > 0) {
            params.category_id = Number(p.props.category_id)
        }
        const res: any = await articleApi.getList(params)
        const list = (res?.data?.list || res?.list || []) as any[]
        items.value = list.map((row) => ({
            id: Number(row.id),
            title: String(row.title || ''),
            cover: row.cover || '',
            summary: row.summary || '',
            date: formatDate(row.publish_at || row.published_at || row.created_at)
        }))
    } catch {
        items.value = []
    } finally {
        loading.value = false
    }
}

watch(
    () =>
        [
            p.props?.source,
            p.props?.category_id,
            p.props?.limit,
            p.props?.layout,
            p.props?.show_cover,
            p.props?.show_summary,
            p.props?.show_date
        ].join('|'),
    () => {
        load()
    },
    { immediate: true }
)
</script>

<style scoped>
.pv-cl {
    width: 100%;
    padding: 4px 0 8px;
}
.pv-cl__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px 6px;
}
.pv-cl__title {
    font-size: 15px;
    color: #172033;
}
.pv-cl__more {
    font-size: 11px;
    color: #8a93a2;
}
.pv-cl__hint {
    padding: 20px 12px;
    text-align: center;
    font-size: 12px;
    color: #9aa4b2;
}
.pv-cl__ph {
    background: linear-gradient(135deg, #f2f3f5 0%, #e5e6eb 100%);
}
.pv-cl__grid {
    display: flex;
    flex-wrap: wrap;
    padding: 4px 6px;
    box-sizing: border-box;
}
.pv-cl__card {
    width: 50%;
    box-sizing: border-box;
    padding: 4px;
}
.pv-cl__card-img {
    width: 100%;
    height: 100px;
    border-radius: 4px;
    display: block;
    object-fit: cover;
}
.pv-cl__card-body {
    padding: 4px 2px;
}
.pv-cl__card-title {
    display: block;
    font-size: 13px;
    color: #222;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pv-cl__card-desc,
.pv-cl__card-meta {
    display: block;
    margin-top: 3px;
    font-size: 11px;
    color: #8a93a2;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pv-cl__list {
    padding: 0 10px;
}
.pv-cl__row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #f0f1f3;
}
.pv-cl__row:last-child {
    border-bottom: none;
}
.pv-cl__row-img {
    width: 64px;
    height: 64px;
    border-radius: 4px;
    object-fit: cover;
    flex-shrink: 0;
}
.pv-cl__row-body {
    flex: 1;
    min-width: 0;
}
.pv-cl__row-title {
    display: block;
    font-size: 13px;
    color: #222;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pv-cl__row-desc,
.pv-cl__row-meta {
    display: block;
    margin-top: 3px;
    font-size: 11px;
    color: #8a93a2;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
