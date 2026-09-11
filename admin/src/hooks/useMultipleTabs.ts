import type { TabPaneName } from 'element-plus'
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import useTabsStore from '@/store/modules/multipleTabs.store'
import useSettingStore from '@/store/modules/settings.store'

/**
 * 多标签页钩子
 * 提供 tabsLists, currentTab, 以及对标签的增删改方法
 */
export default function useMultipleTabs() {
    const router = useRouter()
    const route = useRoute()
    const tabsStore = useTabsStore()
    const settingStore = useSettingStore()

    // 响应式地取出当前所有标签列表
    const tabsLists = computed(() => tabsStore.getTabList)

    // 当前激活的标签 fullPath
    const currentTab = computed({
        get: () => route.fullPath,
        set: (fullPath: string) => {
            // 切换标签时，路由跳转
            router.push(fullPath)
        }
    })

    /** 新增一个标签 */
    const addTab = () => {
        if (!settingStore.openMultipleTabs) return
        tabsStore.addTab(router)
    }

    /** 关闭指定标签（若未传 fullPath，则关闭当前激活标签） */
    function removeTab(fullPath: TabPaneName) {
        // 强转为 string
        const path = String(fullPath)
        tabsStore.removeTab(path, router)
    }

    /** 关闭除当前激活标签外的所有标签 */
    const removeOtherTab = () => {
        if (!settingStore.openMultipleTabs) return
        tabsStore.removeOtherTab(route)
    }

    /** 关闭所有标签并跳转首页 */
    const removeAllTab = () => {
        if (!settingStore.openMultipleTabs) return
        tabsStore.removeAllTab(router)
    }

    return {
        tabsLists,
        currentTab,
        addTab,
        removeTab,
        removeOtherTab,
        removeAllTab
    }
}
