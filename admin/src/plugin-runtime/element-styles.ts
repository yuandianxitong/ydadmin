/**
 * 宿主按页面拆分 Element Plus 的组件样式：没打开过用到某个组件的页面，它的规则就不在文档里。
 * 插件页的样式表只带页面自己的规则，所以进入插件页之前由这个模块把全部组件样式补齐。
 * 模块路径和自动导入解析器生成的一致，已经随宿主页面加载过的样式表不会再取第二遍。
 */
import.meta.glob('/node_modules/element-plus/es/components/*/style/index.mjs', { eager: true })

export {}
