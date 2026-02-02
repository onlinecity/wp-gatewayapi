<script setup lang="ts">
import {ref, onMounted, computed} from 'vue';
import {useParentIframeStore} from '../../stores/parentIframe.ts';
import {Icon} from '@iconify/vue';
import SearchableCountryDropdown from '@/components/SearchableCountryDropdown.vue';
import countriesData from '@/assets/countries.json';

const parentIframe = useParentIframeStore();

const enabled = ref(false);
const gracePeriod = ref('');
const allowedCountries = ref<string[]>([]);
const rememberDuration = ref('session');
const requiredRoles = ref<string[]>([]);
const allRoles = ref<Record<string, string>>({});

const loading = ref(false);
const saving = ref(false);
const message = ref('');
const isError = ref(false);

const countries = computed(() => {
  return Object.entries(countriesData.countries).map(([code, country]: [string, any]) => ({
    slug: code,
    name: country.name,
    phone: country.phone
  })).filter(c => c.phone);
});

onMounted(async () => {
  loading.value = true;
  try {
    const response = await parentIframe.ajaxPost('gatewayapi_get_2fa_settings', {}) as any;
    if (response && response.success) {
      enabled.value = response.data.enabled;
      gracePeriod.value = response.data.grace_period;
      allowedCountries.value = response.data.allowed_countries;
      rememberDuration.value = response.data.remember_duration === '0' ? 'session' : response.data.remember_duration;
      requiredRoles.value = response.data.required_roles;
      allRoles.value = response.data.all_roles;
    }
  } catch (error) {
    console.error('Failed to load 2FA settings:', error);
  } finally {
    loading.value = false;
  }
});

const saveSettings = async () => {
  if (enabled.value) {
    if (allowedCountries.value.length === 0) {
      isError.value = true;
      message.value = 'At least one allowed country must be selected when 2FA is enabled.';
      return;
    }
    if (requiredRoles.value.length === 0) {
      isError.value = true;
      message.value = 'At least one user role must be selected when 2FA is enabled.';
      return;
    }
  }

  saving.value = true;
  message.value = '';
  isError.value = false;

  try {
    const response = await parentIframe.ajaxPost('gatewayapi_save_2fa_settings', {
      enabled: enabled.value,
      grace_period: gracePeriod.value,
      allowed_countries: allowedCountries.value,
      remember_duration: rememberDuration.value === 'session' ? '0' : rememberDuration.value,
      required_roles: requiredRoles.value
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
      <legend class="fieldset-legend">Enable Two-Factor Authentication</legend>
      <label class="label cursor-pointer flex gap-4 justify-start">
        <input type="checkbox" class="checkbox checkbox-primary" v-model="enabled" />
        <span>Enable SMS-based 2FA for selected roles</span>
      </label>
    </fieldset>

    <div v-if="enabled">
      <fieldset class="fieldset text-base mt-4">
        <legend class="fieldset-legend">Grace Period</legend>
        <input type="date" class="input input-bordered w-full max-w-xs" v-model="gracePeriod" />
        <p class="label block">Users won't be asked for 2FA until this date. Default is 2 weeks from now.</p>
      </fieldset>

      <fieldset class="fieldset text-base mt-4">
        <legend class="fieldset-legend">Allowed Countries</legend>
        <div class="max-w-xs">
          <SearchableCountryDropdown
              :countries="countries"
              multiple
              v-model:values="allowedCountries"
              placeholder="Search countries..."
              all-label="No countries selected yet"
          />
        </div>
        <div class="flex flex-wrap gap-2 mt-2">
          <div v-for="prefix in allowedCountries" :key="prefix" class="badge badge-primary gap-2 p-3">
            {{ countries.find(c => c.slug === prefix)?.name || prefix }}
            <Icon icon="lucide:x" class="cursor-pointer" @click="allowedCountries = allowedCountries.filter(c => c !== prefix)"/>
          </div>
        </div>
        <p class="label block">Only numbers from these countries can be used for 2FA. <strong>At least one must be selected.</strong></p>
      </fieldset>

      <fieldset class="fieldset text-base mt-4">
        <legend class="fieldset-legend">Remember 2FA</legend>
        <select class="select select-bordered w-full max-w-xs" v-model="rememberDuration">
          <option value="session">Session only</option>
          <option value="1day">1 day</option>
          <option value="7days">7 days</option>
          <option value="15days">15 days</option>
          <option value="1month">1 month</option>
        </select>
        <p class="label block">How long should a device be remembered before asking for 2FA again?</p>
      </fieldset>

      <fieldset class="fieldset text-base mt-4">
        <legend class="fieldset-legend">Required Roles</legend>
        <div class="flex flex-col gap-2">
          <label v-for="(roleName, roleKey) in allRoles" :key="roleKey" class="label cursor-pointer flex gap-4 justify-start">
            <input type="checkbox" class="checkbox checkbox-sm" :value="roleKey" v-model="requiredRoles" />
            <span>{{ roleName }}</span>
          </label>
        </div>
      </fieldset>
    </div>

    <div class="card-actions justify-end mt-6">
      <button class="btn btn-primary" :disabled="saving" @click="saveSettings">
        <span v-if="saving" class="loading loading-spinner"></span>
        <Icon v-else icon="lucide:check"></Icon>
        Save 2FA Settings
      </button>
    </div>
  </div>
</template>
