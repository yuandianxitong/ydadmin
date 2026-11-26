<template>
    <div class="plugin-page">
        <div class="filter-bar">
            <div class="chips">
                <button
                    v-for="chip in chips"
                    :key="chip.key"
                    type="button"
                    class="chip"
                    :class="{ 'is-active': activeCategory === chip.key }"
                    @click="activeCategory = chip.key"
                >
                    {{ chip.label }}
                </button>
            </div>
            <el-input
                v-model="keyword"
                class="keyword"
                clearable
                :placeholder="t('pluginManage.keyword')"
            />
            <el-button v-has-perm="['plugin.install']" type="primary" @click="pickInstallZip">
                {{ t('pluginManage.uploadInstall') }}
            </el-button>
            <input
                ref="installInput"
                class="file-input"
                type="file"
                accept=".zip,application/zip"
                @change="onInstallZip"
            />
            <input
                ref="upgradeInput"
                class="file-input"
                type="file"
                accept=".zip,application/zip"
                @change="onUpgradeZip"
            />
        </div>

        <div v-if="loading" class="empty">{{ t('pluginManage.loading') }}</div>
        <div v-else-if="sections.length === 0" class="empty">{{ t('pluginManage.empty') }}</div>

        <section v-for="section in sections" :key="section.key" class="section">
            <div class="section-head">
                <h2>{{ section.label }}</h2>
                <button
                    v-if="section.more"
                    type="button"
                    class="more"
                    @click="expanded.add(section.key)"
                >
                    {{ t('pluginManage.more') }}
                </button>
            </div>
            <div class="grid">
                <article v-for="item in section.items" :key="item.code" class="card">
                    <div class="card-main">
                        <img v-if="isIconUrl(item.icon)" class="icon" :src="item.icon" alt="" />
                        <div v-else class="icon icon-fallback">{{ fallback(item.name) }}</div>
                        <div class="card-copy">
                            <h3>{{ item.name }}</h3>
                            <p>{{ item.description || item.code }}</p>
                            <div class="tags">
                                <span class="tag">{{ statusLabel(item.status) }}</span>
                                <span v-if="item.busy" class="tag">{{ t('pluginManage.working') }}</span>
                                <span v-if="item.version" class="tag">v{{ item.version }}</span>
                                <span class="tag">{{ kindLabel(item.kind) }}</span>
                            </div>
                            <p v-if="item.last_error" class="error">{{ item.last_error }}</p>
                        </div>
                    </div>
                    <div class="actions">
                        <el-button
                            v-if="canInstall(item.status)"
                            v-has-perm="['plugin.install']"
                            link
                            type="primary"
                            :disabled="locked(item)"
                            @click="run(item.code, () => pluginApi.install(item.code))"
                        >
                            {{ t('pluginManage.install') }}
                        </el-button>
                        <el-button
                            v-if="item.status === 1"
                            v-has-perm="['plugin.disable']"
                            link
                            type="primary"
                            :disabled="locked(item)"
                            @click="run(item.code, () => pluginApi.disable(item.code))"
                        >
                            {{ t('pluginManage.disable') }}
                        </el-button>
                        <el-button
                            v-if="item.status === 2"
                            v-has-perm="['plugin.enable']"
                            link
                            type="primary"
                            :disabled="locked(item)"
                            @click="run(item.code, () => pluginApi.enable(item.code))"
                        >
                            {{ t('pluginManage.enable') }}
                        </el-button>
                        <el-button
                            v-if="item.status === 1"
                            v-has-perm="['plugin.upgrade']"
                            link
                            type="primary"
                            :disabled="locked(item)"
                            @click="pickUpgrade(item.code)"
                        >
                            {{ t('pluginManage.upgrade') }}
                        </el-button>
                        <el-button
                            v-if="item.status === 1 || item.status === 2"
                            v-has-perm="['plugin.uninstall']"
                            link
                            type="danger"
                            :disabled="locked(item)"
                            @click="confirmUninstall(item)"
                        >
                            {{ t('pluginManage.uninstall') }}
                        </el-button>
                        <el-button
                            v-if="item.status === 2"
                            v-has-perm="['plugin.purge']"
                            link
                            type="danger"
                            :disabled="locked(item)"
                            @click="confirmPurge(item)"
                        >
                            {{ t('pluginManage.purge') }}
                        </el-button>
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>

<script lang="ts" setup>
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { pluginApi, type PluginCard, type PluginCategoryItem } from '@/api/plugin'

const { t } = useI18n()
const loading = ref(false)
const busy = ref('')
const keyword = ref('')
const activeCategory = ref('all')
const categories = ref<PluginCategoryItem[]>([])
const list = ref<PluginCard[]>([])
const expanded = reactive(new Set<string>())
const installInput = ref<HTMLInputElement>()
const upgradeInput = ref<HTMLInputElement>()
const upgradeCode = ref('')

const chips = computed(() => [
    { key: 'all', label: t('pluginManage.all') },
    ...categories.value.map((item) => ({ key: item.key, label: item.label }))
])

const filtered = computed(() => {
    const word = keyword.value.trim().toLowerCase()
    return list.value.filter((item) => {
        if (activeCategory.value !== 'all' && item.category !== activeCategory.value) {
            return false
        }
        if (!word) return true
        return [item.name, item.code, item.description].some((text) =>
            text.toLowerCase().includes(word)
        )
    })
})

const sections = computed(() => {
    const labels = new Map(categories.value.map((item) => [item.key, item.label]))
    const order = categories.value.map((item) => item.key)
    const buckets = new Map<string, PluginCard[]>()
    for (const item of filtered.value) {
        const key = item.category || 'other'
        const bucket = buckets.get(key) ?? []
        bucket.push(item)
        buckets.set(key, bucket)
    }
    const keys = [
        ...order.filter((key) => buckets.has(key)),
        ...[...buckets.keys()].filter((key) => !order.includes(key))
    ]
    return keys.map((key) => {
        const items = buckets.get(key) ?? []
        const truncated = activeCategory.value === 'all' && !expanded.has(key) && items.length > 6
        return {
            key,
            label: labels.get(key) || key,
            items: truncated ? items.slice(0, 6) : items,
            more: truncated
        }
    })
})

function isIconUrl(icon: string) {
    return /^https?:\/\//i.test(icon)
}

function fallback(name: string) {
    return (name || '?').slice(0, 1)
}

function statusLabel(status: number) {
    const key = `pluginManage.status.${status}`
    const label = t(key)
    return label === key ? String(status) : label
}

function kindLabel(kind: string) {
    return kind === 'app' ? t('pluginManage.kindApp') : t('pluginManage.kindPlugin')
}

function canInstall(status: number) {
    return status === 0 || status === 5 || status === 6
}

function locked(item: PluginCard) {
    return busy.value === item.code || item.busy
}

async function load(silent = false) {
    if (!silent) loading.value = true
    try {
        const res = await pluginApi.catalog()
        categories.value = res.data?.categories ?? []
        list.value = res.data?.list ?? []
    } finally {
        loading.value = false
    }
}

async function waitUntilSettled(code: string) {
    const deadline = Date.now() + 90000
    while (Date.now() < deadline) {
        const item = list.value.find((row) => row.code === code)
        if (item && !item.busy && item.status !== 3 && item.status !== 4) return
        if (!item) return
        await new Promise((resolve) => setTimeout(resolve, 1000))
        await load(true)
    }
}

async function run(code: string, action: () => Promise<{ message?: string }>) {
    busy.value = code
    try {
        const res = await action()
        ElMessage.success(res.message || t('pluginManage.done'))
        await load(true)
        await waitUntilSettled(code)
    } finally {
        busy.value = ''
    }
}

function pickInstallZip() {
    installInput.value?.click()
}

async function onInstallZip(event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ''
    if (!file) return
    await run('upload', () => pluginApi.installZip(file))
}

function pickUpgrade(code: string) {
    upgradeCode.value = code
    upgradeInput.value?.click()
}

async function onUpgradeZip(event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    const code = upgradeCode.value
    input.value = ''
    if (!file || !code) return
    await run(code, () => pluginApi.upgrade(code, file))
}

async function confirmUninstall(item: PluginCard) {
    try {
        await ElMessageBox.confirm(
            t('pluginManage.uninstallConfirm', { name: item.name }),
            t('pluginManage.uninstall'),
            { type: 'warning' }
        )
    } catch {
        return
    }
    await run(item.code, () => pluginApi.uninstall(item.code))
}

async function confirmPurge(item: PluginCard) {
    try {
        await ElMessageBox.confirm(
            t('pluginManage.purgeConfirm', { name: item.name }),
            t('pluginManage.purge'),
            { type: 'warning' }
        )
    } catch {
        return
    }
    await run(item.code, () => pluginApi.purge(item.code))
}

onMounted(load)
</script>

<style scoped>
.plugin-page {
    padding: 16px;
}
.filter-bar {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
    padding: 14px 16px;
    background: var(--card-bg);
    border-radius: 12px;
}
.chips {
    display: flex;
    flex: 1;
    flex-wrap: wrap;
    gap: 8px;
}
.chip {
    padding: 6px 14px;
    border: 0;
    border-radius: 999px;
    background: var(--el-fill-color);
    color: var(--el-text-color-regular);
    cursor: pointer;
}
.chip.is-active {
    background: var(--el-color-primary-light-9);
    color: var(--el-color-primary);
}
.keyword {
    width: 240px;
}
.file-input {
    display: none;
}
.empty {
    padding: 48px 0;
    color: var(--el-text-color-secondary);
    text-align: center;
}
.section {
    margin-bottom: 28px;
}
.section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}
.section-head h2 {
    margin: 0;
    color: var(--el-text-color-primary);
    font-size: 16px;
    font-weight: 600;
}
.more {
    border: 0;
    background: transparent;
    color: var(--el-color-primary);
    cursor: pointer;
}
.grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}
.card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 148px;
    padding: 16px;
    background: var(--card-bg);
    border-radius: 12px;
}
.card-main {
    display: flex;
    gap: 12px;
}
.icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    object-fit: cover;
    flex: none;
}
.icon-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--el-color-primary-light-9);
    color: var(--el-color-primary);
    font-weight: 600;
}
.card-copy {
    min-width: 0;
}
.card-copy h3 {
    margin: 0 0 4px;
    overflow: hidden;
    color: var(--el-text-color-primary);
    font-size: 15px;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.card-copy p {
    margin: 0;
    overflow: hidden;
    color: var(--el-text-color-secondary);
    font-size: 13px;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.tags {
    display: flex;
    gap: 6px;
    margin-top: 8px;
}
.tag {
    padding: 1px 8px;
    border-radius: 999px;
    background: var(--el-fill-color);
    color: var(--el-text-color-regular);
    font-size: 12px;
}
.error {
    margin-top: 6px;
    color: var(--el-color-danger);
}
.actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 8px;
}
@media (max-width: 1100px) {
    .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .filter-bar {
        flex-wrap: wrap;
    }
}
@media (max-width: 720px) {
    .grid {
        grid-template-columns: 1fr;
    }
    .keyword {
        width: 100%;
    }
}
</style>
