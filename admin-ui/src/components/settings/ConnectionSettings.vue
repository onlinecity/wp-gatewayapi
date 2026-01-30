<script setup lang="ts">
import {ref, computed, nextTick, watch} from 'vue';
import {useParentIframeStore} from '../../stores/parentIframe.ts';
import {useStateStore} from '../../stores/state.ts';

const props = defineProps<{
  initialSetup?: string;
  initialApiVersion?: string;
}>();

const parentIframe = useParentIframeStore();
const stateStore = useStateStore();

const tokenInput = ref<HTMLInputElement | null>(null);

// Connection settings
const gwapiToken = ref('');
const gwapiSetup = ref(props.initialSetup || 'com');
const gwapiApiVersion = ref(props.initialApiVersion || 'sms');
const tokenFieldCleared = ref(false);
const connectionLoading = ref(false);
const connectionMessage = ref('');
const connectionError = ref(false);

watch(() => props.initialSetup, (newVal) => {
  if (newVal) gwapiSetup.value = newVal;
});

watch(() => props.initialApiVersion, (newVal) => {
  if (newVal) gwapiApiVersion.value = newVal;
});

// Token field should be disabled if hasKey is true and user hasn't clicked to clear it
const tokenFieldDisabled = computed(() => {
  return stateStore.hasKey === true && !tokenFieldCleared.value;
});

// Handle token field click when disabled
const handleTokenFieldClick = async () => {
  if (tokenFieldDisabled.value) {
    tokenFieldCleared.value = true;
    gwapiToken.value = '';
    await nextTick();
    tokenInput.value?.focus();
  }
};

// Handle token field blur - revert if empty
const handleTokenFieldBlur = () => {
  if (tokenFieldCleared.value && !gwapiToken.value) {
    tokenFieldCleared.value = false;
  }
};

// Save connection settings
const saveConnection = async () => {
  connectionLoading.value = true;
  connectionMessage.value = '';
  connectionError.value = false;

  try {
    const response = await parentIframe.ajaxPost('gatewayapi_save_connection', {
      gwapi_token: tokenFieldDisabled.value ? null : gwapiToken.value,
      gwapi_setup: gwapiSetup.value,
      gwapi_api_version: gwapiApiVersion.value,
    }) as any;

    if (response && response.success) {
      connectionMessage.value = response.data.message;
      if (response.data.credit !== null && response.data.credit !== undefined) {
        connectionMessage.value += ` (Credit: ${response.data.credit} ${response.data.currency})`;
      }
      tokenFieldCleared.value = false;
      gwapiToken.value = '';
      await stateStore.reloadKeyStatus();
    } else {
      connectionError.value = true;
      connectionMessage.value = response?.data?.message || 'Failed to save connection settings';
    }
  } catch (error: any) {
    connectionError.value = true;
    connectionMessage.value = error?.message || 'Failed to save connection settings';
  } finally {
    connectionLoading.value = false;
  }
};
</script>

<template>

  <!-- Connection Message -->
  <div v-if="connectionMessage" class="alert mb-6" :class="connectionError ? 'alert-error' : 'alert-success'">
    <Icon v-if="connectionError" icon="lucide:circle-alert"/>
    <Icon v-else icon="lucide:circle-check-big"/>
    <span>{{ connectionMessage }}</span>
  </div>

  <fieldset class="fieldset text-base">
    <legend class="fieldset-legend">API Token</legend>
    <span data-tip="Click to change token" class="tooltip w-full" v-if="tokenFieldDisabled">
          <input
              type="password"
              class="input input-bordered w-full cursor-pointer"
              value="••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••"
              readonly
              @click="handleTokenFieldClick"
              placeholder="Click to change token"
          />
        </span>
    <input
        v-else
        type="text"
        v-model="gwapiToken"
        class="input input-bordered w-full"
        placeholder="Enter your GatewayAPI token"
        @blur="handleTokenFieldBlur"
        ref="tokenInput"
    />
    <p class="label block">
      Get your token from
      <a href="https://gatewayapi.com" target="_blank" rel="noopener noreferrer" class="link link-primary">GatewayAPI.com</a>
      or
      <a href="https://gatewayapi.eu" target="_blank" rel="noopener noreferrer"
         class="link link-primary">GatewayAPI.eu</a>
    </p>
  </fieldset>

  <fieldset class="fieldset text-base mt-4">
    <legend class="fieldset-legend">API Region</legend>
    <div class="flex flex-wrap gap-4">
      <label class="cursor-pointer flex gap-2">
        <input
            type="radio"
            name="gwapi_setup"
            class="radio radio-primary"
            value="com"
            v-model="gwapiSetup"
        />
        <span>GatewayAPI.com</span>
      </label>
      <label class="cursor-pointer flex gap-2">
        <input
            type="radio"
            name="gwapi_setup"
            class="radio radio-primary"
            value="eu"
            v-model="gwapiSetup"
        />
        <span>GatewayAPI.eu</span>
      </label>
    </div>
  </fieldset>

  <fieldset class="fieldset text-base mt-4">
    <legend class="fieldset-legend">API Version</legend>
    <div class="flex flex-col gap-4">
      <label class="cursor-pointer flex gap-2 items-start">
        <input
            type="radio"
            name="gwapi_api_version"
            class="radio radio-primary mt-1"
            value="sms"
            v-model="gwapiApiVersion"
        />
        <div class="flex flex-col text-sm">
          <span class="font-bold">SMS API <span class="badge badge-primary ms-2 badge-sm">RECOMMENDED</span></span>
          <div class="text-sm">We recommend this to most clients. It works for any account type.</div>
        </div>
      </label>
      <label class="cursor-pointer flex gap-2 items-start">
        <input
            type="radio"
            name="gwapi_api_version"
            class="radio radio-primary mt-1"
            value="messaging"
            v-model="gwapiApiVersion"
        />
        <div class="flex flex-col">
          <span class="font-bold text-sm">Messaging API <span
              class="badge badge-warning ms-2  badge-sm">NEW</span></span>
          <div class="text-sm">This API automatically uses the best protocol available for each recipient. It always
            falls back to SMS, but also supports RCS and more protocols are coming. <em>Currently you need to get in
              touch with our support prior to enabling this feature.</em></div>
        </div>
      </label>
    </div>
  </fieldset>

  <div class="card-actions justify-end mt-6">
    <button
        class="btn btn-primary"
        :disabled="connectionLoading || (!gwapiToken && !tokenFieldDisabled)"
        @click="saveConnection"
    >
      <span v-if="connectionLoading" class="loading loading-spinner"></span>
      Save Connection
    </button>
  </div>
</template>
