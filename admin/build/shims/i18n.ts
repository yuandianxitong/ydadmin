// 插件构建里 `@/utils/i18n` 的替身：i18n 实例只装在宿主的应用上，
// 插件自带一份 locales 的话 t() 拿不到实例，只会原样返回 key。
export { t, translateRouteTitle } from '@yd/admin-host@1/i18n'
