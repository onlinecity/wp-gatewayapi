import {createRouter, createWebHashHistory} from 'vue-router'
import Settings from "./pages/settings.vue";

const routes = [
  { path: '/settings', component: Settings },
]

export const router = createRouter({
  history: createWebHashHistory(),
  routes,
})