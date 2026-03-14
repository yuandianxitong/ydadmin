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
    ],

    sidebar: {
      '/guide/': [
        {
          text: '入门',
          items: [
            { text: '项目介绍', link: '/guide/introduction' },
            { text: '快速开始', link: '/guide/quick-start' },
          ],
        },
      ],
      '/backend/': [
        {
          text: '后端开发',
          items: [
            { text: '架构设计', link: '/backend/architecture' },
          ],
        },
      ],
      '/frontend/': [
        {
          text: '前端开发',
          items: [
            { text: '概览', link: '/frontend/overview' },
          ],
        },
      ],
      '/mobile/': [
        {
          text: '移动端开发',
          items: [
            { text: '概览', link: '/mobile/overview' },
          ],
        },
      ],
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/user/dev007-framework' },
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
