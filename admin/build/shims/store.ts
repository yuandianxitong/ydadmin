// 插件构建里 `@/store` 的替身：各个 store 和默认导出都取宿主的那一份，插件不再自带第二个 createPinia。
export { default, useAppStore, useMultipleTabsStore, useSettingStore, useUserStore } from '@yd/admin-host@1/store'
