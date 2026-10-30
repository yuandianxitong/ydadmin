<template>
    <div class="tabs-wrap flex relative bg-[var(--card-bg)] border-b border-ink-100">
        <div
            class="tabs flex flex-1 items-center px-4 gap-1.5 min-w-0 whitespace-nowrap overflow-x-auto overflow-y-hidden"
        >
            <div
                v-for="item in tabsLists"
                :key="item.fullPath"
                class="tab inline-flex items-center gap-1.5 px-3 py-1.5 rounded flex-shrink-0 cursor-pointer select-none"
                :class="{ active: currentTab === item.fullPath }"
                @click="handleChange(item.fullPath)"
            >
                <span class="tab-dot flex-shrink-0" />
                <span class="tab-label flex-1 truncate">{{
                    translateRouteTitle(item.title, item.name)
                }}</span>
                <span
                    v-if="tabsLists.length > 1"
                    class="tab-close flex-shrink-0 rounded-full"
                    @click.stop="removeTab(item.fullPath)"
                >
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                    >
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </span>
            </div>
        </div>

        <el-dropdown @command="handleCommand">
            <div class="tabs-actions flex-center px-2.5 flex-shrink-0 border-l border-ink-100">
                <span class="tabs-act-btn flex-center w-26px h-26px rounded cursor-pointer">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        style="width: 14px; height: 14px; transform: rotate(90deg)"
                    >
                        <path d="m9 18 6-6-6-6" />
                    </svg>
                </span>
            </div>
            <template #dropdown>
                <el-dropdown-menu>
                    <el-dropdown-item command="closeCurrent">{{
                        $t('tabs.closeCurrent')
                    }}</el-dropdown-item>
                    <el-dropdown-item command="closeOther">{{
                        $t('tabs.closeOther')
                    }}</el-dropdown-item>
                    <el-dropdown-item command="closeAll">{{
                        $t('tabs.closeAll')
                    }}</el-dropdown-item>
                </el-dropdown-menu>
            </template>
        </el-dropdown>
    </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'

import useMultipleTabs from '@/hooks/useMultipleTabs'
import { useWatchRoute } from '@/hooks/useWatchRoute'
import useTabsStore from '@/store/modules/multipleTabs.store'
import { translateRouteTitle } from '@/utils/i18n'

const router = useRouter()
const tabsStore = useTabsStore()

// 从 hooks 获取对 tab 操作的方法和响应式列表
const { addTab, removeTab, removeOtherTab, removeAllTab, tabsLists, currentTab } = useMultipleTabs()

// 在路由每次变化时自动调用 addTab
useWatchRoute(() => {
    addTab()
})

// 切换标签时触发：根据 fullPath 在 tabMap 中找到对应的 TabItem，然后 push 跳转
const handleChange = (fullPath: any) => {
    const tabItem = tabsStore.tabMap[fullPath]
    if (!tabItem) return
    router.push({
        path: tabItem.path,
        query: tabItem.query
    })
}

// 下拉菜单的三种操作：关闭当前 / 关闭其他 / 关闭全部
const handleCommand = (command: string) => {
    switch (command) {
        case 'closeCurrent':
            removeTab(currentTab.value)
            break
        case 'closeOther':
            removeOtherTab()
            break
        case 'closeAll':
            removeAllTab()
            break
    }
}
</script>

<style lang="scss" scoped>
.tabs-wrap {
    height: var(--tabs-h);
    z-index: 1;
    box-shadow: var(--shadow-md);
    clip-path: inset(0 0 -40px);
}

.tabs {
    scrollbar-width: thin;
    &::-webkit-scrollbar {
        height: 4px;
    }
    &::-webkit-scrollbar-thumb {
        background: var(--ink-200);
        border-radius: 2px;
        &:hover {
            background: var(--ink-300);
        }
    }
}

.tab {
    font-size: 12.5px;
    color: var(--ink-500);
    border: 1px solid transparent;
    transition:
        background 0.15s,
        color 0.15s,
        border-color 0.15s;

    .tab-close {
        display: none;
    }

    &:hover {
        background: var(--ink-50);
        color: var(--ink-700);
        .tab-close {
            display: flex;
        }
    }

    &.active {
        background: var(--brand-50);
        color: var(--brand-500);
        border-color: var(--brand-100);
        .tab-dot {
            background: var(--brand-500);
        }
        .tab-close {
            display: flex;
        }
    }
}

.tab-dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--ink-300);
}

.tab-close {
    width: 14px;
    height: 14px;
    align-items: center;
    justify-content: center;
    color: var(--ink-400);
    transition:
        background 0.15s,
        color 0.15s;

    svg {
        width: 10px;
        height: 10px;
    }

    &:hover {
        background: var(--brand-500);
        color: #fff;
    }
}

.tabs-actions {
    height: var(--tabs-h);
}

.tabs-act-btn {
    color: var(--ink-500);
    transition:
        background 0.15s,
        color 0.15s;

    &:hover {
        background: var(--ink-100);
        color: var(--ink-800);
    }
}
</style>
