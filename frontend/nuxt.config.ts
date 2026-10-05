import tailwindcss from '@tailwindcss/vite'

export default defineNuxtConfig({
  compatibilityDate: '2026-10-01',
  devtools: { enabled: false },
  css: ['~/assets/css/main.css'],
  vite: {
    plugins: [tailwindcss()],
  },
  runtimeConfig: {
    // Where the Nuxt server reaches the Laravel API while rendering. In Docker Compose: http://app:8000.
    apiBase: 'http://localhost:8000',
    public: {
      // Where the browser reaches the Laravel API.
      apiBase: 'http://localhost:8000',
    },
  },
  app: {
    head: {
      htmlAttrs: { lang: 'en-GB' },
      titleTemplate: (title) => (title ? `${title} · The Board` : 'The Board'),
      meta: [{ name: 'viewport', content: 'width=device-width, initial-scale=1' }],
    },
  },
})
