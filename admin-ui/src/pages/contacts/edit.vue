<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useParentIframeStore } from '@/stores/parentIframe.ts';
import { useRouter } from 'vue-router';
import PageTitle from "@/components/PageTitle.vue";
import Loading from "@/components/Loading.vue";

const props = defineProps<{
  id?: string;
}>();

const parentIframe = useParentIframeStore();
const router = useRouter();

const loading = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');

const contact = ref({
  id: 0,
  name: '',
  msisdn: '',
  status: 'active',
  tags: [] as string[]
});

const tagInput = ref('');

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

onMounted(() => {
  fetchContact();
});

const saveContact = async () => {
  saving.value = true;
  error.value = '';
  success.value = '';
  try {
    const response = await parentIframe.ajaxPost('gatewayapi_save_contact', {
      ...contact.value,
      tags: contact.value.tags
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
  const tag = tagInput.value.trim();
  if (tag && !contact.value.tags.includes(tag)) {
    contact.value.tags.push(tag);
  }
  tagInput.value = '';
};

const removeTag = (tag: string) => {
  contact.value.tags = contact.value.tags.filter(t => t !== tag);
};
</script>

<template>
  <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
    <PageTitle class="mb-0">{{ props.id ? 'Edit Contact' : 'Add New Contact' }}</PageTitle>
    <router-link to="/contacts" class="btn btn-ghost  gap-2">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
      Back to Contacts
    </router-link>
  </div>

  <div v-if="loading" class="flex justify-center py-12">
    <Loading />
  </div>

  <div v-else class="max-w-2xl mx-auto">
    <div v-if="error" class="alert alert-error mb-6 ">
      <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
      <span>{{ error }}</span>
    </div>
    <div v-if="success" class="alert alert-success mb-6 ">
      <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
      <span>{{ success }}</span>
    </div>

    <div class="card bg-base-100 ">
      <div class="card-body">
        <form @submit.prevent="saveContact">
          <fieldset class="fieldset mb-4">
            <legend class="fieldset-legend">Name</legend>
            <input v-model="contact.name" type="text" placeholder="Contact Name" class="input input-bordered w-full" required />
          </fieldset>

          <fieldset class="fieldset mb-4">
            <legend class="fieldset-legend">MSISDN</legend>
            <input v-model="contact.msisdn" type="text" placeholder="e.g. 4512345678" class="input input-bordered w-full" required />
            <p class="fieldset-label">Include country code, e.g. 45 for Denmark.</p>
          </fieldset>

          <fieldset class="fieldset mb-4">
            <legend class="fieldset-legend">Status</legend>
            <select v-model="contact.status" class="select select-bordered w-full">
              <option value="unconfirmed">Unconfirmed</option>
              <option value="active">Active</option>
              <option value="blocked">Blocked</option>
            </select>
          </fieldset>

          <fieldset class="fieldset mb-6">
            <legend class="fieldset-legend">Tags</legend>
            <div class="flex gap-2 w-full mb-2">
              <input v-model="tagInput" type="text" placeholder="Add tag..." class="input input-bordered flex-grow" @keydown.enter.prevent="addTag" />
              <button type="button" @click="addTag" class="btn">Add</button>
            </div>
            <div class="flex flex-wrap gap-2 min-h-[2.5rem] items-center">
              <div v-for="tag in contact.tags" :key="tag" class="badge badge-primary badge-lg gap-1">
                {{ tag }}
                <button type="button" @click="removeTag(tag)" class="btn btn-ghost  btn-circle text-primary-content hover:bg-black/20!">✕</button>
              </div>
              <span v-if="contact.tags.length === 0" class="text-sm text-base-content/40 italic">No tags added.</span>
            </div>
          </fieldset>

          <div class="card-actions justify-end">
            <button type="submit" class="btn btn-primary" :disabled="saving">
              <span v-if="saving" class="loading loading-spinner"></span>
              {{ contact.id ? 'Update Contact' : 'Create Contact' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
