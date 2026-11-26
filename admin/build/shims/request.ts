// 插件构建里 `@/utils/request` 的替身：请求实例、拦截器和 401 跳转都属于宿主，
// 插件产物只引用宿主 import map 提供的同一份模块，不自带副本。
export { default, myRequest } from '@yd/admin-host@1/http'
