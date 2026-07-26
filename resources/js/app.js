import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primevue/themes/aura'
import { createI18n } from 'vue-i18n'
import router from './router/index.js'
import App from './App.vue'
import en from './locales/en.js'
import 'primeicons/primeicons.css'

// i18n setup — English only for V1, structure ready for German/Polish later
const i18n = createI18n({
    legacy: false,
    locale: 'en',
    fallbackLocale: 'en',
    messages: { en },
})

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(PrimeVue, {
    theme: {
        preset: Aura,
        options: {
            darkModeSelector: '.dark',
        },
    },
})
app.use(i18n)

app.mount('#app')
