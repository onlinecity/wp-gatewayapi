<script setup lang="ts">
import {ref, watch, onMounted} from 'vue';
import {useParentIframeStore} from '../../stores/parentIframe.ts';
import {Icon} from "@iconify/vue";

const props = defineProps<{
  initialAllowedCountries?: string[];
}>();

const parentIframe = useParentIframeStore();

const allowedCountries = ref<string[]>(props.initialAllowedCountries || []);
const allCountries = ref<any[]>([]);
const loading = ref(false);
const message = ref('');
const isError = ref(false);

watch(() => props.initialAllowedCountries, (newVal) => {
  if (newVal !== undefined) allowedCountries.value = newVal;
});

onMounted(async () => {
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_woo_countries', {}) as any;
    if (response && response.success) {
      allCountries.value = response.data;
    }
  } catch (err) {
    console.error('Failed to fetch countries:', err);
  }
});

const saveSettings = async () => {
  loading.value = true;
  message.value = '';
  isError.value = false;

  try {
    const response = await parentIframe.ajaxPost('gatewayapi_save_woocommerce_settings', {
      gwapi_woocommerce_allowed_countries: allowedCountries.value,
    }) as any;

    if (response && response.success) {
      message.value = response.data.message;
      // Refresh the page to update the menu
      if (window.parent) {
        window.parent.location.reload();
      }
    } else {
      isError.value = true;
      message.value = response?.data?.message || 'Failed to save WooCommerce settings';
    }
  } catch (error: any) {
    isError.value = true;
    message.value = error?.message || 'Failed to save WooCommerce settings';
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div v-if="message" class="alert mb-6" :class="isError ? 'alert-error' : 'alert-success'">
    <Icon v-if="isError" icon="lucide:circle-alert"/>
    <Icon v-else icon="lucide:circle-check-big"/>
    <span>{{ message }}</span>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <fieldset class="fieldset text-base">
      <legend class="fieldset-legend">Allowed Countries</legend>
      <div class="dropdown w-full">
        <div tabindex="0" role="button" class="select pe-10 flex items-center gap-2 w-full overflow-hidden">
          <template v-if="allowedCountries.length > 0">
            <span class="truncate">{{ allowedCountries.length }} selected</span>
          </template>
          <template v-else>
            Select countries...
          </template>
        </div>
        <div tabindex="0"
            class="dropdown-content bg-base-100 rounded-box z-50 w-full p-2 shadow-sm border border-base-200 max-h-80 overflow-y-auto mt-1">
          <ul class="menu w-full">
          <li v-for="country in allCountries" :key="country.slug">
            <label class="label cursor-pointer justify-start gap-3 w-full py-2">
              <input type="checkbox" v-model="allowedCountries" :value="country.slug" class="checkbox checkbox-sm"/>
              <Icon :icon="`circle-flags:${country.slug.toLowerCase()}`" class="w-5 h-5"/>
              <span class="label-text">{{ country.name }} ({{ country.prefix }})</span>
            </label>
          </li>
          </ul>
        </div>
      </div>
      <p class="mt-2 text-sm opacity-70">
        The handler will only send an SMS if the phone number has a prefix corresponding to one of the allowed countries.
      </p>

      <div class="flex flex-wrap gap-2 mt-4">
        <div v-for="code in allowedCountries" :key="code" class="badge badge-ghost gap-2 py-3 px-4 h-auto">
          <Icon :icon="`circle-flags:${code.toLowerCase()}`" class="w-4 h-4" />
          <span>{{ allCountries.find(c => c.slug === code)?.prefix }} ({{ allCountries.find(c => c.slug === code)?.name }})</span>
          <Icon icon="lucide:x" class="w-3 h-3 cursor-pointer" @click="allowedCountries = allowedCountries.filter(c => c !== code)" />
        </div>
      </div>
    </fieldset>
  </div>

  <div class="card-actions justify-end mt-6" >
    <button
        class="btn btn-primary"
        :disabled="loading"
        @click="saveSettings"
    >
      <span v-if="loading" class="loading loading-spinner"></span>
      Save WooCommerce Settings
    </button>
  </div>
</template>
