import { createPinia } from 'pinia'

const store = createPinia()

export default store

// 统一导出所有 Store
export { useUserStore } from './modules/user.store'
export { useAppStore } from './modules/app.store'
export { useSettingStore } from './modules/settings.store'
export { useMultipleTabsStore } from './modules/multipleTabs.store'
export type { TabItem } from './modules/multipleTabs.store'
