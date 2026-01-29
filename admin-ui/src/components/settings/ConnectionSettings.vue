<script setup lang="ts">
import { ref, computed, nextTick, watch } from 'vue';
import { useParentIframeStore } from '../../stores/parentIframe.ts';
import { useStateStore } from '../../stores/state.ts';

const props = defineProps<{
  initialSetup?: string;
}>();

const parentIframe = useParentIframeStore();
const stateStore = useStateStore();

const tokenInput = ref<HTMLInputElement | null>(null);

// Connection settings
const gwapiToken = ref('');
const gwapiSetup = ref(props.initialSetup || 'com');
const tokenFieldCleared = ref(false);
const connectionLoading = ref(false);
const connectionMessage = ref('');
const connectionError = ref(false);

watch(() => props.initialSetup, (newVal) => {
  if (newVal) gwapiSetup.value = newVal;
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
      gwapi_token: gwapiToken.value,
      gwapi_setup: gwapiSetup.value,
    }) as any;

    if (response && response.success) {
      connectionMessage.value = response.data.message;
      if (response.data.credit) {
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
  <div class="card bg-base-200  border border-base-300">
    <div class="card-body">
      <h2 class="card-title text-xl mb-4">Connection</h2>

      <fieldset class="fieldset">
        <legend class="fieldset-legend">API Token</legend>
        <span data-tip="Click to change token" class="tooltip" v-if="tokenFieldDisabled">
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
          <a href="https://gatewayapi.eu" target="_blank" rel="noopener noreferrer" class="link link-primary">GatewayAPI.eu</a>
        </p>
      </fieldset>

      <fieldset class="fieldset mt-4">
        <legend class="fieldset-legend">API Region</legend>
        <div class="flex flex-wrap gap-4">
          <label class="label cursor-pointer flex gap-2">
            <input
              type="radio"
              name="gwapi_setup"
              class="radio radio-primary"
              value="com"
              v-model="gwapiSetup"
            />
            <span>GatewayAPI.com (Global)</span>
          </label>
          <label class="label cursor-pointer flex gap-2">
            <input
              type="radio"
              name="gwapi_setup"
              class="radio radio-primary"
              value="eu"
              v-model="gwapiSetup"
            />
            <span>GatewayAPI.eu (EU)</span>
          </label>
        </div>
      </fieldset>

      <!-- Connection Message -->
      <div v-if="connectionMessage" class="alert mt-6" :class="connectionError ? 'alert-error' : 'alert-success'">
        <span v-if="connectionError">
          <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </span>
        <span v-else>
          <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </span>
        <span>{{ connectionMessage }}</span>
      </div>

      <div class="card-actions justify-end mt-6">
        <button
          class="btn btn-primary"
          :disabled="connectionLoading || !gwapiToken"
          @click="saveConnection"
        >
          <span v-if="connectionLoading" class="loading loading-spinner"></span>
          Save Connection
        </button>
      </div>
    </div>
  </div>
</template>
