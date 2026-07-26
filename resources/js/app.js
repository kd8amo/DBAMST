import { createApp } from 'vue'
import { createPinia } from 'pinia'
import naive from 'naive-ui'
import { createI18n } from 'vue-i18n'
import router from './router/index.js'
import App from './App.vue'
import en from './locales/en.js'

const i18n = createI18n({
    legacy: false,
    locale: 'en',
    fallbackLocale: 'en',
    messages: { en },
})

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(naive)
app.use(i18n)

app.mount('#app')
