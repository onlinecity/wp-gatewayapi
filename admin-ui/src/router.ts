import {createRouter, createWebHashHistory} from 'vue-router'
import Settings from "./pages/settings.vue";
import Index from "./pages/index.vue";
import ContactsIndex from "./pages/contacts/index.vue";
import ContactsEdit from "./pages/contacts/edit.vue";
import ContactsImport from "./pages/contacts/import.vue";
import CampaignsIndex from "./pages/campaigns/index.vue";
import CampaignsEdit from "./pages/campaigns/edit.vue";
import WooCommerceIndex from "./pages/woocommerce/index.vue";
import WooCommerceEdit from "./pages/woocommerce/edit.vue";

const routes = [
  { path: '/', component: Index },
  { path: '/settings', component: Settings },
  { path: '/contacts', component: ContactsIndex },
  { path: '/contacts/import', component: ContactsImport },
  { path: '/contacts/:id', component: ContactsEdit, props: true },
  { path: '/contacts/new', component: ContactsEdit },
  { path: '/campaigns', component: CampaignsIndex },
  { path: '/campaigns/:id', component: CampaignsEdit, props: true },
  { path: '/campaigns/new', component: CampaignsEdit },
  { path: '/woocommerce', component: WooCommerceIndex },
  { path: '/woocommerce/:id', component: WooCommerceEdit, props: true },
  { path: '/woocommerce/new', component: WooCommerceEdit },
]

export const router = createRouter({
  history: createWebHashHistory(),
  routes,
})