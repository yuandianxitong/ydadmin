// 插件构建里 `@/store/modules/user.store` 的替身：宿主组件有的直接引这个文件而不走 `@/store`，
// 不替换的话用户仓库会连同登录接口和请求上下文一起打进插件。
export { useUserStore as default, useUserStore } from '@yd/admin-host@1/store'
