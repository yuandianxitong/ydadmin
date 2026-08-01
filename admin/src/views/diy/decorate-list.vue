<template>
    <div class="decorate-list">
        <el-card class="page-card" shadow="never">
            <div class="table-header">
                <span class="table-title">{{ $t('decorateList.title') }}</span>
            </div>
            <div class="card-grid">
                <!-- 首页 -->
                <div class="sys-card" @click="goEditor('home', $t('decorateList.home'))">
                    <div class="sys-card__head">
                        <span class="sys-card__name">{{ $t('decorateList.home') }}</span>
                        <el-tag :type="home.published ? 'success' : 'info'" size="small">
                            {{ home.published ? $t('decorateList.published') : $t('decorateList.draft') }}
                        </el-tag>
                    </div>
                    <div class="sys-card__meta">
                        <span>{{ $t('decorateList.componentCount', { count: home.component_count }) }}</span>
                        <span v-if="home.updated_at">{{ $t('decorateList.updatedAt', { time: home.updated_at }) }}</span>
                    </div>
                    <el-button type="primary" plain size="small" class="sys-card__btn">
                        {{ $t('decorateList.decorate') }}
                    </el-button>
                </div>
                <!-- 个人中心 -->
                <div class="sys-card" @click="goEditor('member', $t('decorateList.member'))">
                    <div class="sys-card__head">
                        <span class="sys-card__name">{{ $t('decorateList.member') }}</span>
                        <el-tag :type="member.published ? 'success' : 'info'" size="small">
                            {{ member.published ? $t('decorateList.published') : $t('decorateList.draft') }}
                        </el-tag>
                    </div>
                    <div class="sys-card__meta">
                        <span>{{ $t('decorateList.componentCount', { count: member.component_count }) }}</span>
                        <span v-if="member.updated_at">{{ $t('decorateList.updatedAt', { time: member.updated_at }) }}</span>
                    </div>
                    <el-button type="primary" plain size="small" class="sys-card__btn">
                        {{ $t('decorateList.decorate') }}
                    </el-button>
                </div>
            </div>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { onActivated, onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'

import { diyApi, type DiyHomeSummary } from '@/api/diy'

const router = useRouter()

const home = reactive<DiyHomeSummary>({
    title: '',
    published: false,
    component_count: 0,
    updated_at: null
})

const member = reactive<DiyHomeSummary>({
    title: '',
    published: false,
    component_count: 0,
    updated_at: null
})

function applySummary(target: DiyHomeSummary, data: Partial<DiyHomeSummary> | undefined) {
    if (!data || typeof data.component_count !== 'number') return
    Object.assign(target, {
        title: data.title ?? target.title,
        published: !!data.published,
        component_count: data.component_count,
        updated_at: data.updated_at ?? null
    })
}

function loadSummary() {
    diyApi
        .getHomeSummary()
        .then((res) => applySummary(home, res.data))
        .catch((err) => console.warn('[decorate-list] home summary failed', err))
    diyApi
        .getPageSummary('member')
        .then((res) => applySummary(member, res.data))
        .catch((err) => console.warn('[decorate-list] member summary failed', err))
}

onMounted(loadSummary)
// 从编辑器返回时刷新（keep-alive 场景）
onActivated(loadSummary)

function goEditor(key: string, title: string) {
    router.push({ path: '/diy/editor', query: { key, title } })
}
</script>

<style scoped lang="scss">
.card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.sys-card {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 18px 20px;
    border: 1px solid var(--color-divider);
    border-radius: var(--el-card-border-radius);
    background: var(--color-surface);
    cursor: pointer;
    transition: box-shadow var(--motion-duration-base) var(--motion-easing);

    &:hover { box-shadow: var(--shadow-md); }

    &.is-disabled {
        cursor: not-allowed;
        opacity: 0.7;

        &:hover { box-shadow: none; }
    }

    &__head { display: flex; align-items: center; justify-content: space-between; }
    &__name { font-size: 15px; font-weight: 600; color: var(--color-text-primary); }
    &__meta { display: flex; gap: 12px; font-size: 12px; color: var(--color-text-tertiary); }
    &__btn { align-self: flex-start; }
}
</style>
