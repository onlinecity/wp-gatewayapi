<script setup lang="ts">
import { ref, watch } from 'vue';
import { useParentIframeStore } from '../../stores/parentIframe.ts';

const props = defineProps<{
  initialCountryCode?: string;
  initialSender?: string;
  initialSendSpeed?: number;
}>();

const parentIframe = useParentIframeStore();

// Defaults settings
const defaultCountryCode = ref(props.initialCountryCode || '45');
const defaultSender = ref(props.initialSender || '');
const defaultSendSpeed = ref(props.initialSendSpeed || 60);
const defaultsLoading = ref(false);
const defaultsMessage = ref('');
const defaultsError = ref(false);

watch(() => props.initialCountryCode, (newVal) => {
  if (newVal !== undefined) defaultCountryCode.value = newVal;
});
watch(() => props.initialSender, (newVal) => {
  if (newVal !== undefined) defaultSender.value = newVal;
});
watch(() => props.initialSendSpeed, (newVal) => {
  if (newVal !== undefined) defaultSendSpeed.value = newVal;
});

// Save defaults settings
const saveDefaults = async () => {
  defaultsLoading.value = true;
  defaultsMessage.value = '';
  defaultsError.value = false;

  const sender = defaultSender.value;
  if (sender) {
    const isDigitsOnly = /^\d+$/.test(sender);
    if (isDigitsOnly) {
      if (sender.length > 18) {
        defaultsError.value = true;
        defaultsMessage.value = 'Default sender cannot be more than 18 digits';
        defaultsLoading.value = false;
        return;
      }
    } else {
      if (sender.length > 11) {
        defaultsError.value = true;
        defaultsMessage.value = 'Default sender cannot be more than 11 characters when it contains non-digit characters';
        defaultsLoading.value = false;
        return;
      }
    }
  }

  try {
    const response = await parentIframe.ajaxPost('gatewayapi_save_defaults', {
      gwapi_default_country_code: defaultCountryCode.value,
      gwapi_default_sender: defaultSender.value,
      gwapi_default_send_speed: defaultSendSpeed.value,
    }) as any;

    if (response && response.success) {
      defaultsMessage.value = response.data.message;
    } else {
      defaultsError.value = true;
      defaultsMessage.value = response?.data?.message || 'Failed to save default settings';
    }
  } catch (error: any) {
    defaultsError.value = true;
    defaultsMessage.value = error?.message || 'Failed to save default settings';
  } finally {
    defaultsLoading.value = false;
  }
};
</script>

<template>
  <div class="card bg-base-100 shadow-sm">
    <div class="card-body">
      <h2 class="card-title text-xl mb-4">Defaults</h2>

      <!-- Defaults Message -->
      <div v-if="defaultsMessage" class="alert mb-6" :class="defaultsError ? 'alert-error' : 'alert-success'">
        <Icon v-if="defaultsError" icon="lucide:circle-alert" />
        <Icon v-else icon="lucide:circle-check-big" />
        <span>{{ defaultsMessage }}</span>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Default Country Code -->
        <fieldset class="fieldset">
          <legend class="fieldset-legend">Default Country Code</legend>
          <input
              type="text"
              v-model="defaultCountryCode"
              class="input input-bordered w-full"
              placeholder="45"
          />
          <p class="block">
            The default country code for phone numbers. See
            <a href="https://gatewayapi.com/pricing/" target="_blank" rel="noopener noreferrer"
               class="link link-primary">supported countries</a>.
          </p>
        </fieldset>

        <fieldset class="fieldset tooltip tooltip-top" data-tip="The sender must be either up to 18 digits, or max 11 characters if it contains anything except digits.">
          <legend class="fieldset-legend">Default Sender</legend>
          <input
              type="text"
              v-model="defaultSender"
              class="input input-bordered w-full"
              placeholder="Info"
          />
          <p class="block">
            Default sender name or number. MSISDN for replies.
            <a href="https://gatewayapi.com/pricing/" target="_blank" rel="noopener noreferrer"
               class="link link-primary">Pricing info</a>.
          </p>
        </fieldset>

        <!-- Default Send Speed -->
        <fieldset class="fieldset">
          <legend class="fieldset-legend">Default Send Speed</legend>
          <input
              type="number"
              v-model.number="defaultSendSpeed"
              class="input input-bordered w-full"
              min="1"
              max="1000"
              placeholder="60"
          />
          <p class="block">
            Messages per minute (1-1000).
          </p>
        </fieldset>
      </div>

      <div class="card-actions justify-end mt-6">
        <button
            class="btn btn-primary"
            :disabled="defaultsLoading"
            @click="saveDefaults"
        >
          <span v-if="defaultsLoading" class="loading loading-spinner"></span>
          Save Defaults
        </button>
      </div>
    </div>
  </div>
</template>
