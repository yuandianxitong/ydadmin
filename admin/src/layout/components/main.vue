<template>
    <main class="main-wrap h-full">
        <el-scrollbar>
            <div class="p-4">
                <router-view v-if="isRouteShow" v-slot="{ Component, route }">
                    <transition name="fade" mode="out-in">
                        <keep-alive :include="includeList" :max="20">
                            <component :is="Component" :key="route.fullPath" />
                        </keep-alive>
                    </transition>
                </router-view>
            </div>
        </el-scrollbar>
    </main>
</template>

<script setup lang="ts">
import useAppStore from '@/store/modules/app.store'
import useTabsStore from '@/store/modules/multipleTabs.store'
import useSettingStore from '@/store/modules/settings.store'

const appStore = useAppStore()
const tabsStore = useTabsStore()
const settingStore = useSettingStore()

// 是否显示路由内容
const isRouteShow = computed(() => appStore.isRouteShow)

// 需要缓存的组件列表
const includeList = computed(() => (settingStore.openMultipleTabs ? tabsStore.getCacheTabList : []))
</script>
