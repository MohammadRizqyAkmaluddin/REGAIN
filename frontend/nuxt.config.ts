import tailwindcss from '@tailwindcss/vite'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',

  ssr: false,
  spaLoadingTemplate: 'app/spa-loading-template.html',

devServer: {
  host: '127.0.0.1',
  port: 3000,
},

  devtools: {
    enabled: false,
  },

  app: {
    head: {
      title: 'Deep Pockets — Keuangan & Bebas Rokok',
      htmlAttrs: {
        lang: 'id',
      },
      script: [
        {
          innerHTML: `(function(){try{var d=window.matchMedia('(prefers-color-scheme: dark)').matches;document.documentElement.classList.toggle('dark',d);}catch(e){}})();`,
        },
      ],
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1, viewport-fit=cover' },
        { name: 'description', content: 'Setiap rokok yang tidak kamu beli adalah uang yang kamu simpan untuk masa depan.' },
        { name: 'theme-color', content: '#f8fafc', media: '(prefers-color-scheme: light)' },
        { name: 'theme-color', content: '#09090b', media: '(prefers-color-scheme: dark)' },
        { name: 'apple-mobile-web-app-capable', content: 'yes' },
        { name: 'mobile-web-app-capable', content: 'yes' },
        { name: 'apple-mobile-web-app-title', content: 'Deep Pockets' },
        { name: 'apple-mobile-web-app-status-bar-style', content: 'black-translucent' },
      ],
      link: [
        { rel: 'icon', type: 'image/png', href: '/favicon.png' },
        { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' },
        { rel: 'apple-touch-icon', sizes: '180x180', href: '/icons/apple-touch-icon.png' },
      ],
    },
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000/api/v1',
    },
  },

  css: ['~/assets/css/main.css'],

  modules: [
    '@pinia/nuxt',
    '@vueuse/nuxt',
    '@vite-pwa/nuxt',
  ],

  pwa: {
    strategies: 'buildSW',
    filename: 'sw.js',
    registerType: 'prompt',
    
    buildSW: {
    	swSrc: 'service-worker/sw.ts',
	swDest: 'sw.js',
    	globPatterns: ['**/*.{js,css,html,svg,png,ico,woff2}'],
    },
    
   manifest: {
      name: 'Deep Pockets — Keuangan & Bebas Rokok',
      short_name: 'Deep Pockets',
      description: 'Pendamping keuangan pribadi dan perjalanan bebas rokok.',
      lang: 'id',
      theme_color: '#09090b',
      background_color: '#09090b',
      display: 'standalone',
      orientation: 'portrait',
      start_url: '/',
      scope: '/',
      icons: [
        { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
        { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
        { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
      ],
    },
    injectManifest: {
      globPatterns: ['**/*.{js,css,html,svg,png,ico,woff2}'],
    },
    devOptions: {
      enabled: false,
      type: 'module',
    },
  },

  vite: {
    plugins: [tailwindcss()],
  },

  typescript: {
    strict: true,
  },
})
