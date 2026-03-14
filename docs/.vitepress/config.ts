import { defineConfig } from 'vitepress'

export default defineConfig({
  lang: 'zh-CN',
  title: 'Dev007 Framework',
  description: '开源通用软件系统管理后台框架',

  // 排除 superpowers 目录，不作为文档页面处理
  srcExclude: ['superpowers/**'],

  themeConfig: {
    nav: [
      { text: '指南', link: '/guide/introduction' },
      { text: '后端', link: '/backend/architecture' },
      { text: '前端', link: '/frontend/overview' },
      { text: '移动端', link: '/mobile/overview' },
      { text: '插件', link: '/plugin/introduction' },
    ],

    sidebar: {
      '/guide/': [
        {
          text: '入门',
          items: [
            { text: '项目介绍', link: '/guide/introduction' },
            { text: '快速开始', link: '/guide/quick-start' },
            { text: '目录结构', link: '/guide/directory-structure' },
            { text: '配置说明', link: '/guide/configuration' },
            { text: '部署指南', link: '/guide/deployment' },
          ],
        },
      ],
      '/backend/': [
        {
          text: '后端开发',
          items: [
            { text: '架构设计', link: '/backend/architecture' },
            { text: 'Controller', link: '/backend/controller' },
            { text: 'Service', link: '/backend/service' },
            { text: 'Repository', link: '/backend/repository' },
            { text: 'Model', link: '/backend/model' },
            { text: '事件与监听器', link: '/backend/event-listener' },
            { text: '中间件', link: '/backend/middleware' },
            { text: 'API 规范', link: '/backend/api-convention' },
            { text: '内置模块', link: '/backend/modules' },
            { text: '代码生成器', link: '/backend/code-generator' },
          ],
        },
      ],
      '/frontend/': [
        {
          text: '前端开发',
          items: [
            { text: '概览', link: '/frontend/overview' },
            { text: '架构', link: '/frontend/architecture' },
            { text: '路由系统', link: '/frontend/router' },
            { text: '权限控制', link: '/frontend/permission' },
            { text: '请求封装', link: '/frontend/request' },
            { text: '公共组件', link: '/frontend/components' },
            { text: 'Composables', link: '/frontend/hooks' },
            { text: '状态管理', link: '/frontend/store' },
            { text: '主题与样式', link: '/frontend/theme' },
          ],
        },
      ],
      '/mobile/': [
        {
          text: '移动端开发',
          items: [
            { text: '概览', link: '/mobile/overview' },
            { text: '快速开始', link: '/mobile/getting-started' },
            { text: '内置模块', link: '/mobile/modules' },
            { text: '自定义组件', link: '/mobile/components' },
            { text: '支付集成', link: '/mobile/payment' },
            { text: '微信登录', link: '/mobile/wechat-login' },
          ],
        },
      ],
      '/plugin/': [
        {
          text: '插件开发',
          items: [
            { text: '插件系统介绍', link: '/plugin/introduction' },
            { text: '创建插件', link: '/plugin/create-plugin' },
            { text: '插件 API', link: '/plugin/plugin-api' },
          ],
        },
      ],
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/yuandianxitong/ydadmin' },
    ],

    footer: {
      message: '基于 MIT 许可发布',
      copyright: 'Copyright © 2024-present Dev007',
    },

    outline: {
      label: '页面导航',
    },

    docFooter: {
      prev: '上一页',
      next: '下一页',
    },

    search: {
      provider: 'local',
      options: {
        translations: {
          button: {
            buttonText: '搜索文档',
            buttonAriaLabel: '搜索文档',
          },
          modal: {
            noResultsText: '无法找到相关结果',
            resetButtonTitle: '清除查询条件',
            footer: {
              selectText: '选择',
              navigateText: '切换',
              closeText: '关闭',
            },
          },
        },
      },
    },
  },
})
