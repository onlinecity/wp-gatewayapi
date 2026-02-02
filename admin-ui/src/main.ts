import {createApp} from 'vue'
import './style.css'
import App from './App.vue'
import {createPinia} from 'pinia'
import {router} from "./router.ts";
import {createPersistedState} from 'pinia-plugin-persistedstate'
import {Icon} from "@iconify/vue";

const pinia = createPinia()
pinia.use(createPersistedState({
  storage: sessionStorage
}))

const app = createApp(App);
app.use(pinia)
app.use(router)
app.component('Icon', Icon)
app.mount('#app')
