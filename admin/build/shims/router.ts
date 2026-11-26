// 插件构建里 `@/router` 的替身：路由实例是宿主的，
// 插件不再自带第二个 createRouter 以及它连带的登录页和错误页。
export {
    createRouteRecord,
    default,
    filterAsyncRoutes,
    findFirstValidPath,
    getModulesKey,
    getRoutePath,
    loadRouteView,
    resetRouter,
} from '@yd/admin-host@1/router'
