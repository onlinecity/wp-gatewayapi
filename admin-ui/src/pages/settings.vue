<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useParentIframeStore } from '@/stores/parentIframe.ts';
import { useStateStore } from '@/stores/state.ts';
import ConnectionSettings from '@/components/settings/ConnectionSettings.vue';
import DefaultSettings from '@/components/settings/DefaultSettings.vue';
import PageTitle from "@/components/PageTitle.vue";
import Loading from "@/components/Loading.vue";

const parentIframe = useParentIframeStore();
const state = useStateStore();

const gwapiSetup = ref('com');
const gwapiApiVersion = ref('sms');
const defaultCountryCode = ref('45');
const defaultSender = ref('');
const defaultSendSpeed = ref(60);

// Load settings on mount
onMounted(async () => {
  try {
    const response = await parentIframe.ajaxPost('gatewayapi_get_settings', {}) as any;
    if (response && response.success) {
      const data = response.data;
      gwapiSetup.value = data.gwapi_setup || 'com';
      gwapiApiVersion.value = data.gwapi_api_version || 'sms';
      defaultCountryCode.value = data.gwapi_default_country_code || '45';
      defaultSender.value = data.gwapi_default_sender || '';
      defaultSendSpeed.value = parseInt(data.gwapi_default_send_speed) || 60;
    }
  } catch (error) {
    console.error('Failed to load settings:', error);
  }
});
</script>

<template>

  <PageTitle icon="lucide:cog">Settings</PageTitle>

  <Loading v-if="state.hasKey === null" />

  <div v-else class="flex flex-col gap-8">
    <ConnectionSettings :initial-setup="gwapiSetup" :initial-api-version="gwapiApiVersion" />
    <DefaultSettings
      :initial-country-code="defaultCountryCode"
      :initial-sender="defaultSender"
      :initial-send-speed="defaultSendSpeed"
    />
  </div>
</template>