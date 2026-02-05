<script setup lang="ts">
import {ref, onMounted} from 'vue';
import {useParentIframeStore} from '../../stores/parentIframe.ts';
import {Icon} from '@iconify/vue';

const parentIframe = useParentIframeStore();

const siteKey = ref('');
const secretKey = ref('');

const loading = ref(false);
const saving = ref(false);
const message = ref('');
const isError = ref(false);

onMounted(async () => {
  loading.value = true;
  try {
    const response = await parentIframe.ajaxPost('gatewayapi_get_frontend_settings', {}) as any;
    if (response && response.success) {
      siteKey.value = response.data.recaptcha_site_key || '';
      secretKey.value = response.data.recaptcha_secret_key || '';
    }
  } catch (error) {
    console.error('Failed to load frontend settings:', error);
  } finally {
    loading.value = false;
  }
});

const saveSettings = async () => {
  saving.value = true;
  message.value = '';
  isError.value = false;

  try {
    const response = await parentIframe.ajaxPost('gatewayapi_save_frontend_settings', {
      recaptcha_site_key: siteKey.value,
      recaptcha_secret_key: secretKey.value
    }) as any;

    if (response && response.success) {
      message.value = response.data.message;
    } else {
      isError.value = true;
      message.value = response?.data?.message || 'Failed to save settings';
    }
  } catch (error: any) {
    isError.value = true;
    message.value = error?.message || 'Failed to save settings';
  } finally {
    saving.value = false;
  }
};
</script>

<template>
  <div v-if="loading" class="flex justify-center p-12">
    <span class="loading loading-spinner loading-lg"></span>
  </div>

  <div v-else>
    <div v-if="message" class="alert mb-6" :class="isError ? 'alert-error' : 'alert-success'">
      <Icon v-if="isError" icon="lucide:circle-alert"/>
      <Icon v-else icon="lucide:circle-check-big"/>
      <span>{{ message }}</span>
    </div>

    <fieldset class="fieldset text-base">
      <legend class="fieldset-legend">Google reCAPTCHA v2</legend>
      <p class="mb-4 text-sm opacity-75">
        To use reCAPTCHA v2 ("I'm not a robot" checkbox) on your frontend forms, you need to register your site with Google and enter the keys below.
        <a href="https://www.google.com/recaptcha/admin" target="_blank" class="link">Get keys here</a>.
      </p>

      <label class="label">
        <span class="label-text">Site Key</span>
      </label>
      <input type="text" class="input input-bordered w-full max-w-lg" v-model="siteKey" placeholder="e.g. 6LeMx..." />

      <label class="label mt-4">
        <span class="label-text">Secret Key</span>
      </label>
      <input type="text" class="input input-bordered w-full max-w-lg" v-model="secretKey" placeholder="e.g. 6LeMx..." />
    </fieldset>

    <div class="card-actions justify-end mt-6">
      <button class="btn btn-primary" :disabled="saving" @click="saveSettings">
        <span v-if="saving" class="loading loading-spinner"></span>
        <Icon v-else icon="lucide:check"></Icon>
        Save Settings
      </button>
    </div>
  </div>
</template>
