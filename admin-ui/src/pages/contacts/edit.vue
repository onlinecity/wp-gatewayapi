<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useParentIframeStore } from '@/stores/parentIframe.ts';
import { useRouter } from 'vue-router';
import PageTitle from "@/components/PageTitle.vue";
import Loading from "@/components/Loading.vue";
import { Icon } from '@iconify/vue';
import { parsePhoneNumberFromString } from 'libphonenumber-js';
import countryData from '@/assets/countries.json';

const props = defineProps<{
  id?: string;
}>();

const parentIframe = useParentIframeStore();
const router = useRouter();

const loading = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');
const validationError = ref('');

const contact = ref({
  id: 0,
  name: '',
  msisdn: '',
  status: 'active',
  tags: [] as string[]
});

const allRecipientTags = ref<any[]>([]);

const country = computed(() => {
  if (!contact.value.msisdn) return null;
  
  let msisdn = contact.value.msisdn.trim();
  if (!msisdn.startsWith('+')) {
    msisdn = '+' + msisdn;
  }
  
  try {
    const phoneNumber = parsePhoneNumberFromString(msisdn);
    if (phoneNumber && phoneNumber.country) {
      const isoCode = phoneNumber.country;
      const countryInfo = (countryData.countries as any)[isoCode];
      if (countryInfo) {
        return {
          code: isoCode.toLowerCase(),
          name: countryInfo.name
        };
      }
    }
  } catch (e) {
    // Ignore parsing errors
  }
  return null;
});

const fetchContact = async () => {
  if (!props.id) return;
  loading.value = true;
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_contact', { id: props.id }) as any;
    if (response && response.success) {
      contact.value = response.data;
    } else {
      error.value = response?.data?.message || 'Failed to load contact';
    }
  } catch (err) {
    console.error('Failed to fetch contact:', err);
    error.value = 'An error occurred while loading the contact.';
  } finally {
    loading.value = false;
  }
};

const fetchRecipientTags = async () => {
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_tags', {}) as any;
    if (response && response.success) {
      allRecipientTags.value = response.data;
    }
  } catch (err) {
    console.error('Failed to fetch recipient tags:', err);
  }
};

onMounted(() => {
  fetchContact();
  fetchRecipientTags();
});

const validateContact = () => {
  if (!contact.value.name || !contact.value.name.trim()) {
    validationError.value = 'Name is required.';
    return false;
  }
  if (!contact.value.msisdn || !contact.value.msisdn.trim()) {
    validationError.value = 'MSISDN is required.';
    return false;
  }
  const msisdnValue = contact.value.msisdn.trim();
  if (!/^[\d\s+]+$/.test(msisdnValue)) {
    validationError.value = 'MSISDN can only contain digits, + and spaces.';
    return false;
  }
  const digitCount = (msisdnValue.match(/\d/g) || []).length;
  if (digitCount < 5) {
    validationError.value = 'MSISDN must contain at least 5 digits.';
    return false;
  }
  if (!country.value) {
    validationError.value = 'Country could not be detected from MSISDN. Please ensure the country code is correct.';
    return false;
  }
  if (!contact.value.tags || contact.value.tags.length === 0) {
    validationError.value = 'At least one tag is required.';
    return false;
  }
  validationError.value = '';
  return true;
};

const saveContact = async () => {
  if (!validateContact()) {
    return;
  }
  saving.value = true;
  error.value = '';
  success.value = '';
  try {
    const response = await parentIframe.ajaxPost('gatewayapi_save_contact', {
      ...contact.value,
      tags: contact.value.tags,
      country: country.value?.name || '',
      country_code: country.value?.code || ''
    }) as any;
    if (response && response.success) {
      success.value = 'Contact saved successfully!';
      if (!contact.value.id) {
        contact.value.id = response.data.id;
        router.replace('/contacts/' + response.data.id);
      }
    } else {
      error.value = response?.data?.message || 'Failed to save contact';
    }
  } catch (err) {
    console.error('Failed to save contact:', err);
    error.value = 'An error occurred while saving the contact.';
  } finally {
    saving.value = false;
  }
};

const addTag = () => {
  const name = window.prompt('Enter new tag name:');
  if (name) {
    const tag = name.trim();
    if (tag) {
      if (!contact.value.tags.includes(tag)) {
        contact.value.tags.push(tag);
      }
      if (!allRecipientTags.value.find(t => t.name === tag)) {
        allRecipientTags.value.push({ name: tag, count: 0 });
      }
    }
  }
};
</script>

<template>

  <PageTitle icon="lucide:user-pen">
    {{ props.id ? 'Edit Contact' : 'Add New Contact' }}
    <template #actions>
      <router-link to="/contacts" class="btn btn-soft gap-2">
        <Icon icon="lucide:arrow-left" />
        Back to Contacts
      </router-link>
    </template>
  </PageTitle>

  <div v-if="loading" class="flex justify-center py-12">
    <Loading />
  </div>

  <div v-else class="max-w-2xl mx-auto">
    <div class="card bg-base-100 border-base-300 border-2">
      <div class="card-body">
        <div v-if="validationError" class="alert alert-warning mb-6 ">
          <Icon icon="lucide:circle-alert" />
          <span>{{ validationError }}</span>
        </div>
        <div v-if="error" class="alert alert-error mb-6 ">
          <Icon icon="lucide:circle-alert" />
          <span>{{ error }}</span>
        </div>
        <div v-if="success" class="alert alert-success mb-6 ">
          <Icon icon="lucide:circle-check-big" />
          <span>{{ success }}</span>
        </div>
        <form @submit.prevent="saveContact">
          <fieldset class="fieldset text-base mb-4">
            <legend class="fieldset-legend">Name</legend>
            <input v-model="contact.name" type="text" placeholder="Contact Name" class="input input-bordered w-full" required />
          </fieldset>

          <fieldset class="fieldset text-base mb-4">
            <legend class="fieldset-legend">MSISDN</legend>
            <label class="input flex w-full">

              <span class="label flex-1" v-if="country">
                <Icon :icon="`circle-flags:${country.code}`" class="w-6 h-6" />
                <span class="text-sm font-medium">{{ country.name }}</span>
              </span>
              <span class="label flex-1" v-else>
                - No country detected -
              </span>

              <input v-model="contact.msisdn" type="text" placeholder="e.g. 4512345678" required class="flex-2" />
            </label>
            <p class="fieldset-label">Include country code, e.g. 45 for Denmark.</p>
          </fieldset>

          <fieldset class="fieldset text-base mb-4">
            <legend class="fieldset-legend">Status</legend>
            <select v-model="contact.status" class="select select-bordered w-full">
              <option value="unconfirmed">Unconfirmed</option>
              <option value="active">Active</option>
              <option value="blocked">Blocked</option>
            </select>
          </fieldset>

          <fieldset class="fieldset text-base mb-6">
            <legend class="fieldset-legend">Tags</legend>
            <div class="flex gap-3 relative">
              <div class="dropdown w-full static">
                <div tabindex="0" role="button" class="select select-bordered w-full flex items-center justify-between mb-1">
                  <span>
                    {{ contact.tags.length }} tags selected
                  </span>
                </div>
                <ul tabindex="0" class="menu dropdown-content bg-base-100 rounded-box z-50 w-full p-2 shadow-lg max-h-64 overflow-y-auto border border-base-200">
                  <li v-if="allRecipientTags.length === 0" class="p-4 text-center text-sm opacity-50">
                    No contacts found or no contacts are associated with a tag.
                  </li>
                  <li v-for="tag in allRecipientTags" :key="tag.name">
                    <label class="label cursor-pointer justify-start gap-3 w-full py-2">
                      <input type="checkbox" v-model="contact.tags" :value="tag.name" class="checkbox checkbox-sm" />
                      <span class="label-text flex-grow">{{ tag.name }}</span>
                      <span class="badge badge-sm badge-ghost opacity-50">{{ tag.count }}</span>
                    </label>
                  </li>
                </ul>
              </div>
              <button type="button" @click="addTag" class="btn btn-outline btn-primary tooltip" data-tip="Add new tag"><Icon icon="lucide:plus" /></button>
            </div>
          </fieldset>

          <div class="card-actions justify-end">
            <button type="submit" class="btn btn-primary" :disabled="saving">
              <span v-if="saving" class="loading loading-spinner"></span>
              <Icon icon="lucide:check" class="me-2" />
              {{ contact.id ? 'Update Contact' : 'Create Contact' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
