<script setup lang="ts">
import {ref, onMounted} from 'vue';
import {useParentIframeStore} from '@/stores/parentIframe.ts';
import {useStateStore} from '@/stores/state.ts';
import ConnectionSettings from '@/components/settings/ConnectionSettings.vue';
import TwoFactorSettings from '@/components/settings/TwoFactorSettings.vue';
import DefaultSettings from '@/components/settings/DefaultSettings.vue';
import ContactFieldsSettings from '@/components/settings/ContactFieldsSettings.vue';
import FrontendFormsSettings from '@/components/settings/FrontendFormsSettings.vue';
import PageTitle from "@/components/PageTitle.vue";
import Loading from "@/components/Loading.vue";

const parentIframe = useParentIframeStore();
const state = useStateStore();

const gwapiSetup = ref('com');
const gwapiApiVersion = ref('messaging');
const isOAuthOnly = ref(false);
const defaultSender = ref('');
const defaultSendSpeed = ref(60);

// Load settings on mount
onMounted(async () => {
  try {
    const response = await parentIframe.ajaxPost('gatewayapi_get_settings', {}) as any;
    if (response && response.success) {
      const data = response.data;
      gwapiSetup.value = data.gwapi_setup || 'com';
      gwapiApiVersion.value = data.gwapi_api_version || 'messaging';
      isOAuthOnly.value = data.is_oauth_only || false;
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

  <Loading v-if="state.hasKey === null"/>

  <div v-else class="tabs tabs-lift">
    <input type="radio" name="settings_tabs" class="tab" aria-label="Connection" :checked="true"/>
    <div class="tab-content bg-base-100 border-base-300 p-6">
      <ConnectionSettings :initial-setup="gwapiSetup" :initial-api-version="gwapiApiVersion" v-model:is-oauth-only="isOAuthOnly"/>
    </div>

    <input type="radio" name="settings_tabs" class="tab" aria-label="Two-Factor"/>
    <div class="tab-content bg-base-100 border-base-300 p-6">
      <TwoFactorSettings/>
    </div>

    <input type="radio" name="settings_tabs" class="tab" aria-label="Defaults"/>
    <div class="tab-content bg-base-100 border-base-300 p-6">
      <DefaultSettings
          :initial-sender="defaultSender"
          :initial-send-speed="defaultSendSpeed"
      />
    </div>

    <input type="radio" name="settings_tabs" class="tab" aria-label="Contact Fields"/>
    <div class="tab-content bg-base-100 border-base-300 p-6">
      <ContactFieldsSettings/>
    </div>

    <input type="radio" name="settings_tabs" class="tab" aria-label="Shortcode Defaults"/>
    <div class="tab-content bg-base-100 border-base-300 p-6">
      <FrontendFormsSettings/>
    </div>
  </div>
</template>