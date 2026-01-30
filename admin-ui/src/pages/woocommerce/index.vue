<script setup lang="ts">
import {ref, onMounted, watch} from 'vue';
import {useParentIframeStore} from '@/stores/parentIframe.ts';
import PageTitle from "@/components/PageTitle.vue";
import Loading from "@/components/Loading.vue";
import {Icon} from '@iconify/vue';

const parentIframe = useParentIframeStore();

const smss = ref<any[]>([]);
const loading = ref(true);
const pagination = ref({total: 0, pages: 1, current: 1});
const filters = ref({
  s: '',
});

const fetchSmss = async () => {
  loading.value = true;
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_woo_smss', {
      paged: pagination.value.current,
      ...filters.value
    }) as any;
    if (response && response.success) {
      smss.value = response.data.smss;
      pagination.value = response.data.pagination;
    }
  } catch (error) {
    console.error('Failed to fetch WooCommerce SMSs:', error);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchSmss();
});

watch(() => filters.value, () => {
  pagination.value.current = 1;
  fetchSmss();
}, {deep: true});

const setPage = (page: number) => {
  pagination.value.current = page;
  fetchSmss();
};

const toggleSms = async (id: number, enabled: boolean) => {
  try {
    const response = await parentIframe.ajaxPost('gatewayapi_toggle_woo_sms', {id, enabled}) as any;
    if (!response || !response.success) {
      console.error('Failed to toggle WooCommerce SMS:', response);
      // Revert local state if needed
    }
  } catch (error) {
    console.error('Failed to toggle WooCommerce SMS:', error);
  }
};

const deleteSms = async (id: number) => {
  if (!confirm('Are you sure you want to delete this WooCommerce SMS template?')) return;

  try {
    const response = await parentIframe.ajaxPost('gatewayapi_delete_woo_sms', {id}) as any;
    if (response && response.success) {
      fetchSmss();
    }
  } catch (error) {
    console.error('Failed to delete WooCommerce SMS:', error);
  }
};

const getExcerpt = (text: string) => {
  if (!text) return '';
  const lines = text.split('\n').slice(0, 2);
  let excerpt = lines.join('\n');
  if (text.split('\n').length > 2 || excerpt.length > 100) {
    excerpt = excerpt.substring(0, 100) + '...';
  }
  return excerpt;
};
</script>

<template>
  <div class="flex justify-between items-center mb-4">
    <PageTitle icon="streamline-logos:woocommerce-logo-solid">
      Order SMS
      <template #actions>
        <router-link to="/woocommerce/new" class="btn btn-primary">
          <Icon icon="lucide:plus"/>
          Create WooCommerce SMS
        </router-link>
      </template>
    </PageTitle>
  </div>

  <!-- FILTERS -->
  <div class="card bg-base-100 border-base-300 border-2 mb-8">
    <div class="card-body p-4">
      <div class="flex flex-wrap gap-4 -mt-2">
        <fieldset class="fieldset text-base">
          <legend class="fieldset-legend">Search</legend>
          <input v-model.lazy="filters.s" type="text" placeholder="Search title..." class="input input-bordered "/>
        </fieldset>
      </div>
    </div>
  </div>

  <!-- TABLE -->
  <div class="card bg-base-100 border-base-300 border-2">
    <div class="overflow-x-auto">
      <table class="table md:table-md">
        <thead>
        <tr>
          <th>Enable</th>
          <th>Title</th>
          <th>Order State</th>
          <th>Target Countries</th>
          <th>Message</th>
          <th class="text-right">Actions</th>
        </tr>
        </thead>
        <tbody>
        <tr v-if="loading">
          <td colspan="6" class="p-0">
            <Loading/>
          </td>
        </tr>
        <tr v-else-if="smss.length === 0">
          <td colspan="6" class="text-center p-12 text-base-content/50">No WooCommerce SMS templates found.</td>
        </tr>
        <tr v-for="sms in smss" :key="sms.id" class="hover">
          <td>
            <input type="checkbox" v-model="sms.enabled" @change="toggleSms(sms.id, sms.enabled)" class="toggle" :class="sms.enabled ? 'toggle-primary' : 'toggle-warning'"/>
          </td>
          <td class="font-bold">{{ sms.title }}</td>
          <td>
            <span class="badge badge-ghost">{{ sms.order_state }}</span>
          </td>
          <td>
            <div class="flex items-center gap-1">
              <template v-for="(country, index) in sms.countries.slice(0, 3)" :key="country">
                <div class="tooltip" :data-tip="country">
                  <Icon :icon="`circle-flags:${country.toLowerCase()}`" class="w-6 h-6"/>
                </div>
              </template>
              <span v-if="sms.countries.length > 3" class="text-xs opacity-50">
                +{{ sms.countries.length - 3 }} more
              </span>
              <span v-else-if="sms.countries.length === 0" class="text-xs opacity-50">All</span>
            </div>
          </td>
          <td class="max-w-xs whitespace-pre-wrap text-sm opacity-80">{{ getExcerpt(sms.message) }}</td>
          <td>
            <div class="flex justify-end gap-1">
              <router-link :to="'/woocommerce/' + sms.id" class="btn btn-primary tooltip" data-tip="Edit">
                <Icon icon="lucide:edit"/>
              </router-link>
              <button @click="deleteSms(sms.id)" class="btn btn-error btn-outline tooltip" data-tip="Delete">
                <Icon icon="lucide:trash"/>
              </button>
            </div>
          </td>
        </tr>
        </tbody>
      </table>
    </div>

    <div v-if="pagination.pages > 1" class="flex justify-center p-4">
      <div class="join">
        <button
            v-for="p in pagination.pages"
            :key="p"
            class="join-item btn "
            :class="{ 'btn-active': p === pagination.current }"
            @click="setPage(p)"
        >
          {{ p }}
        </button>
      </div>
    </div>
  </div>
</template>
