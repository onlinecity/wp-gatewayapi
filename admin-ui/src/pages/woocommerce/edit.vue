<script setup lang="ts">
import {computed, ref, watch} from 'vue';
import {useParentIframeStore} from '@/stores/parentIframe.ts';
import {useRouter} from 'vue-router';
import PageTitle from "@/components/PageTitle.vue";
import SmsEditor from "@/components/SmsEditor.vue";
import SearchableCountryDropdown from "@/components/SearchableCountryDropdown.vue";
import {Icon} from "@iconify/vue";

const props = defineProps<{
  id?: string;
}>();

const parentIframe = useParentIframeStore();
const router = useRouter();

const loading = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');

const sms = ref({
  id: 0,
  title: '',
  enabled: true,
  sender: '',
  order_state: 'processing',
  phone_field: 'billing_phone',
  fixed_phone_numbers: '',
  countries: [] as string[],
  message: ''
});

const statuses = ref<any[]>([]);
const allCountries = ref<any[]>([]);

const fetchStatuses = async () => {
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_woo_statuses', {}) as any;
    if (response && response.success) {
      statuses.value = response.data;
    }
  } catch (err) {
    console.error('Failed to fetch statuses:', err);
  }
};

const fetchCountries = async () => {
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_woo_countries', {}) as any;
    if (response && response.success) {
      allCountries.value = response.data;
    }
  } catch (err) {
    console.error('Failed to fetch countries:', err);
  }
};

watch(() => props.id, async () => {
  loading.value = true;
  await Promise.all([fetchStatuses(), fetchCountries()]);

  if (props.id) {
    try {
      const response = await parentIframe.ajaxGet('gatewayapi_get_woo_sms', {id: props.id}) as any;
      if (response && response.success) {
        sms.value = response.data;
      } else {
        error.value = response?.data?.message || 'Failed to load WooCommerce SMS template';
      }
    } catch (err) {
      console.error('Failed to load WooCommerce SMS:', err);
      error.value = 'An error occurred while loading the template';
    }
  }
  loading.value = false;
}, { immediate: true });

const saveSms = async () => {
  saving.value = true;
  error.value = '';
  success.value = '';

  try {
    const response = await parentIframe.ajaxPost('gatewayapi_save_woo_sms', {
      ...sms.value
    }) as any;

    if (response && response.success) {
      success.value = 'WooCommerce SMS template saved successfully';
      if (!props.id) {
        router.push('/woocommerce/' + response.data.id);
      }
    } else {
      error.value = response?.data?.message || 'Failed to save WooCommerce SMS template';
    }
  } catch (err: any) {
    error.value = err?.message || 'An error occurred while saving';
  } finally {
    saving.value = false;
  }
};

const smsTags = computed(() => [
  {tag: '%ORDER_ID%', label: 'Order ID', category: 'Order Fields'},
  {tag: '%ORDER_NUMBER%', label: 'Order Number', category: 'Order Fields'},
  {tag: '%ORDER_TOTAL%', label: 'Order Total', category: 'Order Fields'},
  {tag: '%ORDER_STATUS%', label: 'Order Status', category: 'Order Fields'},
  {tag: '%BILLING_NAME%', label: 'Billing Name', category: 'Billing Fields'},
  {tag: '%BILLING_FIRST_NAME%', label: 'Billing First Name', category: 'Billing Fields'},
  {tag: '%BILLING_LAST_NAME%', label: 'Billing Last Name', category: 'Billing Fields'},
  {tag: '%BILLING_ADDRESS%', label: 'Billing Address', category: 'Billing Fields'},
  {tag: '%SHIPPING_NAME%', label: 'Shipping Name', category: 'Shipping Fields'},
  {tag: '%SHIPPING_ADDRESS%', label: 'Shipping Address', category: 'Shipping Fields'},
]);
</script>

<template>
  <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
    <PageTitle class="mb-0" icon="streamline-logos:woocommerce-logo-solid">
      {{ props.id ? 'Edit WooCommerce SMS' : 'New WooCommerce SMS' }}
      <template #actions>
        <router-link to="/woocommerce" class="btn btn-soft gap-2">
          <Icon icon="lucide:arrow-left" />
          Back to WooCommerce
        </router-link>
      </template>
    </PageTitle>
  </div>

  <div v-if="error" class="alert alert-error mb-6">
    <Icon icon="lucide:circle-alert"/>
    <span>{{ error }}</span>
  </div>

  <div v-if="success" class="alert alert-success mb-6">
    <Icon icon="lucide:circle-check-big"/>
    <span>{{ success }}</span>
  </div>

  <Loading v-if="loading"/>

  <div v-else>
    <div class="card bg-base-100 border-base-300 border-2 mb-8">
      <div class="card-body">
        <h2 class="card-title text-sm uppercase opacity-50">Template Title</h2>
        <fieldset class="fieldset p-0">
          <input v-model="sms.title" type="text" placeholder="eg. Order Completed SMS"
                 class="input input-bordered w-full font-bold"/>
        </fieldset>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Left Column: Trigger Configuration -->
      <div class="lg:col-span-1 space-y-6">
        <div class="card bg-base-100 border-base-300 border-2">
          <div class="card-body">
            <h3 class="card-title text-sm uppercase opacity-50 mb-4">Trigger Configuration</h3>

            <fieldset class="fieldset text-base">
              <legend class="fieldset-legend">Enable Template</legend>
              <label class="label cursor-pointer justify-start gap-4">
                <input type="checkbox" v-model="sms.enabled" class="toggle toggle-primary"/>
                <span class="label-text">Active</span>
              </label>
            </fieldset>

            <fieldset class="fieldset text-base">
              <legend class="fieldset-legend">Sender</legend>
              <input v-model="sms.sender" type="text" placeholder="e.g. MyCompany" class="input input-bordered w-full" />
              <p class="text-sm fieldset-label">Leave empty to use the default sender from settings. Max 11 characters or 18 digits.</p>
            </fieldset>

            <fieldset class="fieldset text-base">
              <legend class="fieldset-legend">Target Order State</legend>
              <select v-model="sms.order_state" class="select select-bordered w-full">
                <option v-for="status in statuses" :key="status.key" :value="status.key">
                  {{ status.label }}
                </option>
              </select>
              <p class="text-sm fieldset-label">When the order reaches this state, send the SMS.</p>
            </fieldset>

            <fieldset class="fieldset text-base">
              <legend class="fieldset-legend">Recipient Phone Source</legend>
              <div class="flex flex-col gap-2">
                <label class="label cursor-pointer justify-start gap-2">
                  <input type="radio" v-model="sms.phone_field" value="billing_phone" class="radio radio-sm"/>
                  <span class="label-text text-sm">Billing Phone</span>
                </label>
                <label class="label cursor-pointer justify-start gap-2">
                  <input type="radio" v-model="sms.phone_field" value="shipping_phone" class="radio radio-sm"/>
                  <span class="label-text text-sm">Shipping Phone</span>
                </label>
                <label class="label cursor-pointer justify-start gap-2">
                  <input type="radio" v-model="sms.phone_field" value="fixed" class="radio radio-sm"/>
                  <span class="label-text text-sm">Fixed number(s)</span>
                </label>
              </div>
            </fieldset>

            <fieldset v-if="sms.phone_field === 'fixed'" class="fieldset text-base">
              <legend class="fieldset-legend">Phone numbers</legend>
              <textarea v-model="sms.fixed_phone_numbers" class="textarea textarea-bordered w-full h-24 text-sm" placeholder="One per line, e.g. +4512345678"></textarea>
              <p class="text-sm">Enter phone numbers separated by newline. Must start with country code prefix (e.g. +45).</p>
            </fieldset>

            <fieldset v-if="sms.phone_field !== 'fixed'" class="fieldset text-base">
              <legend class="fieldset-legend">Limit to Countries</legend>
              <SearchableCountryDropdown
                v-model:values="sms.countries"
                :countries="allCountries"
                :multiple="true"
                placeholder="Search countries..."
                all-label="All countries"
              />
              <p class="text-sm fieldset-label">If none selected, all countries will receive SMS.</p>
            </fieldset>
          </div>
        </div>
      </div>

      <!-- Right Column: Message Editor -->
      <div class="lg:col-span-2 space-y-6">
        <SmsEditor
            v-model="sms.message"
            :tags="smsTags"
        >
          <template #actions>
            <router-link to="/woocommerce" class="btn btn-base">Cancel</router-link>
            <button class="btn btn-primary" :disabled="saving" @click="saveSms">
              <span v-if="saving" class="loading loading-spinner"></span>
              <Icon v-else icon="lucide:save" />
              {{ props.id ? 'Save Changes' : 'Create Template' }}
            </button>
          </template>
        </SmsEditor>
      </div>
    </div>
  </div>
</template>
