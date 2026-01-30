import {defineStore} from "pinia";
import {type Ref, ref} from "vue";
import {useParentIframeStore} from "./parentIframe.ts";

export const useStateStore = defineStore('mainState', () => {
  const hasKey = ref(null) as Ref<null | boolean>;
  const keyIsValid = ref(null) as Ref<null | boolean>;
  const credit = ref(null) as Ref<null | number>;
  const currency = ref(null) as Ref<null | string>;
  const lastUpdated = ref(null) as Ref<null | number>;

  const reloadKeyStatus = async (force: boolean = false) => {
    if (!force && hasKey.value !== null && lastUpdated.value && (Date.now() - lastUpdated.value) < 60000) {
      return;
    }
    const parentIframe = useParentIframeStore();
    try {
      const response = await parentIframe.ajaxPost('gatewayapi_get_key_status', {}) as any;
      if (response && response.success) {
        hasKey.value = response.data.hasKey;
        keyIsValid.value = response.data.keyIsValid;
        credit.value = Number(response.data.credit);
        currency.value = response.data.currency;
        lastUpdated.value = Date.now();
      }
    } catch (error) {
      console.error('Failed to load key status:', error);
    }
  }
  reloadKeyStatus();

  return {
    hasKey, keyIsValid, credit, currency, lastUpdated,
    reloadKeyStatus
  }
}, { persist: true });