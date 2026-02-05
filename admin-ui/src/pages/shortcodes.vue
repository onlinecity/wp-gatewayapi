<script setup lang="ts">
import PageTitle from "@/components/PageTitle.vue";
import SearchableCountryDropdown from "@/components/SearchableCountryDropdown.vue";
import {ref, onMounted, computed} from 'vue';
import {useParentIframeStore} from '@/stores/parentIframe.ts';
import {Icon} from '@iconify/vue';
import {useClipboard} from '@vueuse/core';

const parentIframe = useParentIframeStore();
const loading = ref(false);

const action = ref('signup');
const useRecaptcha = ref(false);
const embedCss = ref(false);
const allowUserTags = ref(false);
const addNameField = ref(false);
const selectedTags = ref<number[]>([]);
const availableTags = ref<{term_id: number, name: string}[]>([]);
const availableCountries = ref<{slug: string, name: string}[]>([]);
const allowedCountries = ref<string[]>([]);

onMounted(async () => {
  loading.value = true;
  try {
    const [tagsResponse, countriesResponse] = await Promise.all([
      parentIframe.ajaxPost('gatewayapi_get_tags', {}),
      parentIframe.ajaxPost('gatewayapi_get_countries', {})
    ]) as any[];

    if (tagsResponse && tagsResponse.success) {
      availableTags.value = tagsResponse.data;
    }
    if (countriesResponse && countriesResponse.success) {
      availableCountries.value = countriesResponse.data;
    }
  } catch (error) {
    console.error('Failed to load data:', error);
  } finally {
    loading.value = false;
  }
});

const isSignupValid = computed(() => {
  if (action.value !== 'signup') return true;
  const tagsValid = selectedTags.value.length > 0;
  const countriesValid = allowedCountries.value.length > 0;
  return tagsValid && countriesValid;
});

const generatedShortcode = computed(() => {
  let code = `[gatewayapi action="${action.value}"`;
  
  if (useRecaptcha.value) {
    code += ` recaptcha="1"`;
  }

  if (embedCss.value) {
    code += ` embed_css="1"`;
  }
  
  if (selectedTags.value.length > 0) {
    code += ` groups="${selectedTags.value.join(',')}"`;
  }
  
  if (allowUserTags.value) {
    code += ` edit_groups="1"`;
  }

  if (action.value === 'signup' && addNameField.value) {
    code += ` add_name_field="1"`;
  }

  if (allowedCountries.value.length > 0 && action.value !== 'send_sms') {
    code += ` allowed_countries="${allowedCountries.value.join(',')}"`;
  }
  
  code += `]`;
  return code;
});

const { copy, copied, isSupported } = useClipboard({ source: generatedShortcode });

const copyToClipboard = async () => {
  await copy(generatedShortcode.value);
};
</script>

<template>
  <PageTitle icon="lucide:code">Shortcodes</PageTitle>

  <div v-if="loading" class="flex justify-center p-12">
    <span class="loading loading-spinner loading-lg"></span>
  </div>

  <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Configuration -->
    <div class="card bg-base-100 shadow-sm">
      <div class="card-body">
        <h2 class="card-title">Configure Shortcode</h2>
        
        <div class="form-control w-full">
          <label class="label w-full">
            <span class="label-text">Action</span>
          </label>
          <select class="select select-bordered" v-model="action">
            <option value="signup">Sign up</option>
            <option value="update">Update profile</option>
            <option value="unsubscribe">Unsubscribe</option>
            <option value="send_sms">Send SMS</option>
          </select>
          <label class="label mt-1">
            <span class="label-text-alt">
              <span v-if="action === 'signup'">User enters phone number -> SMS verification -> Saved to contacts.</span>
              <span v-if="action === 'update'">User enters phone number -> SMS verification -> Edit profile.</span>
              <span v-if="action === 'unsubscribe'">User enters phone number -> SMS verification -> Removed from contacts.</span>
              <span v-if="action === 'send_sms'">User enters a message -> Sent to all contacts in selected groups.</span>
            </span>
          </label>
        </div>

        <div class="form-control mt-3">
          <label class="label cursor-pointer justify-start gap-4 w-full">
            <input type="checkbox" class="checkbox" v-model="useRecaptcha" />
            <span class="label-text">Enable reCAPTCHA</span>
          </label>
          <label class="label text-wrap">
             Requires reCAPTCHA keys to be configured in Settings > Shortcode Defaults.
          </label>
        </div>

        <div class="form-control mt-3">
          <label class="label cursor-pointer justify-start gap-4 w-full">
            <input type="checkbox" class="checkbox" v-model="embedCss" />
            <span class="label-text">Include basic styling</span>
          </label>
          <label class="label text-wrap">
             Embeds basic CSS to improve the form layout.
          </label>
        </div>

        <div class="form-control mt-3" v-if="action === 'signup'">
          <label class="label cursor-pointer justify-start gap-4 w-full">
            <input type="checkbox" class="checkbox" v-model="addNameField" />
            <span class="label-text">Add name-field</span>
          </label>
          <label class="label text-wrap">
            Adds a Name-field as the first field and saves it as the contact name.
          </label>
        </div>

        <div class="form-control mt-3" v-if="action !== 'send_sms'">
          <label class="label">
            <span class="label-text">Allowed countries</span>
          </label>
          <SearchableCountryDropdown 
            :countries="availableCountries" 
            multiple 
            v-model:values="allowedCountries"
            placeholder="Search and select countries..."
            allLabel="Select countries..."
          />
          <label class="label text-wrap">
            The user must pick at least one country before the shortcode can generate.
          </label>
        </div>

        <div class="form-control mt-3" v-if="action === 'signup' || action === 'update' || action === 'send_sms'">
           <label class="label cursor-pointer justify-start gap-4">
            <input type="checkbox" class="checkbox" v-model="allowUserTags" />
            <span class="label-text">
              <span v-if="action === 'send_sms'">User selectable recipients</span>
              <span v-else>User selectable tags</span>
            </span>
          </label>
           <div class="label text-wrap">
             If checked, users can choose from the tags/groups selected below. If unchecked, the selected tags are automatically used.
          </div>
        </div>

        <div class="form-control w-full mt-3" v-if="action === 'signup' || action === 'update' || action === 'send_sms'">
          <label class="label">
            <span class="label-text">Assign/Update Tags</span>
          </label>
          <div class="card bg-base-200 max-h-60 overflow-y-auto">
            <div class="card-body p-3 flex flex-col gap-2">
              <label v-for="tag in availableTags" :key="tag.term_id" class="label cursor-pointer justify-start gap-2">
                <input type="checkbox" class="checkbox checkbox-sm" :value="tag.term_id" v-model="selectedTags"/>
                <span>{{ tag.name }}</span>
              </label>
              <div v-if="availableTags.length === 0" class="text-sm opacity-50 p-2">
                No tags found. Create tags in Contacts section first.
              </div>
            </div>
          </div>
          <label class="label mt-1 text-wrap">
            <span class="" v-if="action === 'signup'">These tags will be added to the new contact.</span>
            <span class="" v-if="action === 'update'">These tags will be selectable by the user (if "groups" attribute logic allows) or added (logic depends on implementation).</span>
            <span class="" v-if="action === 'send_sms'">The SMS will be sent to recipients in these groups.</span>
          </label>
        </div>
      </div>
    </div>

    <!-- Preview -->
    <div class="card bg-base-100 shadow-sm h-fit">
      <div class="card-body">
        <h2 class="card-title">Generated Shortcode</h2>

        <div v-if="action === 'signup' && !isSignupValid" class="alert alert-warning mt-4">
          <Icon icon="lucide:alert-triangle" class="h-6 w-6" />
          <div>
            <h3 class="font-bold">Configuration required</h3>
            <div class="text-sm">When creating a signup form, you must:
              <ul class="list-disc ml-4">
                <li>Select at least one tag.</li>
                <li>Select at least one allowed country.</li>
              </ul>
            </div>
          </div>
        </div>

        <template v-else>
          <p>Copy and paste this shortcode into any post or page.</p>

          <div class="mockup-code bg-base-300 text-base-content my-4 cursor-pointer" @click="copyToClipboard">
            <pre><code>{{ generatedShortcode }}</code></pre>
          </div>
          <div class="card-actions justify-end" v-if="isSupported">
            <button class="btn" :class="copied ? 'btn-success' : 'btn-primary'" @click="copyToClipboard" :disabled="copied">
              <Icon :icon="copied ? 'lucide:check' : 'lucide:copy'" />
              {{ copied ? 'Copied!' : 'Copy to Clipboard' }}
            </button>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
