import {createApp} from 'vue'
import './style.css'
import App from './App.vue'
import {createPinia} from 'pinia'
import {router} from "./router.ts";
import {createPersistedState} from 'pinia-plugin-persistedstate'

const pinia = createPinia()
pinia.use(createPersistedState({
  storage: sessionStorage
}))

createApp(App).use(pinia).use(router).mount('#app')
