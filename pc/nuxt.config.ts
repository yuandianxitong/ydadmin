export default defineNuxtConfig({
  ssr: false,

  css: [
    '@unocss/reset/tailwind.css',
    '~/assets/css/main.css',
  ],

  app: {
    baseURL: '/pc/',
    head: {
      title: '元点Admin',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
      ],
      link: [
        { rel: 'icon', type: 'image/x-icon', href: '/pc/favicon.ico' },
      ],
    },
  },

  modules: [
    '@unocss/nuxt',
    '@pinia/nuxt',
  ],

  devtools: { enabled: false },

  devServer: {
    port: 5175,
  },

  vite: {
    server: {
      proxy: {
        '/api': {
          target: 'http://localhost',
          changeOrigin: true,
        },
        '/storage': {
          target: 'http://localhost',
          changeOrigin: true,
        },
      },
    },
  },

  compatibilityDate: '2025-01-01',
})
