<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
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

const campaign = ref({
  id: 0,
  title: '',
  sender: '',
  message: '',
  campaign_tags: [] as string[],
  recipient_tags: [] as string[],
  recipient_tags_logic: 'any',
  start_time: '',
  status: 'draft',
  recipients_count: 0
});

const campaignTagInput = ref('');
const allRecipientTags = ref<any[]>([]);
const fetchingRecipientCount = ref(false);

// SMS Calculation Logic ported from old-js.js
const GSM_CHARS_ONE = ' !"#$%&\'()*+,-./0123456789:;<=>?@abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ£¥§¿_\n\rΔΦΓΛΩΠΨΣΘΞèéùìòÇØøÅåÆæßÉÄÖÑÜäöñüàäöñüà';
const GSM_CHARS_TWO = '^{}[]~|€';

const decodeUcs2 = (str: string) => {
  const result = [];
  for (let i = 0; i < str.length; i++) {
    const value = str.charCodeAt(i);
    if (value >= 0xD800 && value <= 0xDBFF && i + 1 < str.length) {
      const extra = str.charCodeAt(i + 1);
      if ((extra & 0xFC00) === 0xDC00) {
        result.push(((value & 0x3FF) << 10) + (extra & 0x3FF) + 0x10000);
        i++;
        continue;
      }
    }
    result.push(value);
  }
  return result;
};

const encodeUcs2 = (codes: number[]) => {
  return String.fromCodePoint(...codes);
};

const failedGSM0338Chars = (message: string) => {
  const lookup = (GSM_CHARS_ONE + GSM_CHARS_TWO).split('');
  const chars = decodeUcs2(message);
  const failed = [];
  for (const code of chars) {
    const char = String.fromCodePoint(code);
    if (!lookup.includes(char)) {
      failed.push(char);
    }
  }
  return [...new Set(failed)];
};

const smsStats = computed(() => {
  const message = campaign.value.message;
  const failed = failedGSM0338Chars(message);
  const isUcs2 = failed.length > 0;
  const chars = decodeUcs2(message);
  
  if (isUcs2) {
    const len = chars.length;
    return {
      isUcs2: true,
      characters: len,
      messages: len > 70 ? Math.ceil(len / 67) : 1,
      failedChars: failed
    };
  } else {
    const lookup2 = GSM_CHARS_TWO.split('');
    let count = 0;
    for (const code of chars) {
      const char = String.fromCodePoint(code);
      count++;
      if (lookup2.includes(char)) count++;
    }
    return {
      isUcs2: false,
      characters: count,
      messages: count > 160 ? Math.ceil(count / 153) : 1,
      failedChars: []
    };
  }
});

const fetchCampaign = async () => {
  if (!props.id) return;
  loading.value = true;
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_campaign', { id: props.id }) as any;
    if (response && response.success) {
      campaign.value = {
        ...campaign.value,
        ...response.data
      };
    } else {
      error.value = response?.data?.message || 'Failed to load campaign';
    }
  } catch (err) {
    console.error('Failed to fetch campaign:', err);
    error.value = 'An error occurred while loading the campaign.';
  } finally {
    loading.value = false;
  }
};

const fetchRecipientTags = async () => {
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_tags') as any;
    if (response && response.success) {
      allRecipientTags.value = response.data;
    }
  } catch (err) {
    console.error('Failed to fetch recipient tags:', err);
  }
};

const updateRecipientCount = async () => {
  if (campaign.value.recipient_tags.length === 0) {
    campaign.value.recipients_count = 0;
    return;
  }
  fetchingRecipientCount.value = true;
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_count_recipients', {
      recipient_tags: campaign.value.recipient_tags,
      recipient_tags_logic: campaign.value.recipient_tags_logic
    }) as any;
    if (response && response.success) {
      campaign.value.recipients_count = response.data.count;
    }
  } catch (err) {
    console.error('Failed to fetch recipient count:', err);
  } finally {
    fetchingRecipientCount.value = false;
  }
};

watch(() => [campaign.value.recipient_tags, campaign.value.recipient_tags_logic], () => {
  if (campaign.value.recipient_tags.length > 0) {
    updateRecipientCount();
  } else {
    campaign.value.recipients_count = 0;
  }
}, { deep: true });

onMounted(() => {
  fetchCampaign();
  fetchRecipientTags();
});

const saveCampaign = async (newStatus?: string) => {
  saving.value = true;
  error.value = '';
  success.value = '';
  
  if (newStatus) {
    campaign.value.status = newStatus;
  } else if (!campaign.value.status || campaign.value.status === 'any') {
    campaign.value.status = 'draft';
  }

  try {
    const response = await parentIframe.ajaxPost('gatewayapi_save_campaign', {
      ...campaign.value
    }) as any;
    if (response && response.success) {
      success.value = 'Campaign saved successfully!';
      if (!campaign.value.id) {
        campaign.value.id = response.data.id;
        router.replace('/campaigns/' + response.data.id);
      }
    } else {
      error.value = response?.data?.message || 'Failed to save campaign';
    }
  } catch (err) {
    console.error('Failed to save campaign:', err);
    error.value = 'An error occurred while saving the campaign.';
  } finally {
    saving.value = false;
  }
};

const addTag = (type: 'campaign') => {
  const input = campaignTagInput;
  const tags = campaign.value.campaign_tags;
  const tag = input.value.trim();
  if (tag && !tags.includes(tag)) {
    tags.push(tag);
  }
  input.value = '';
};

const removeTag = (type: 'campaign' | 'recipient', tag: string) => {
  if (type === 'campaign') {
    campaign.value.campaign_tags = campaign.value.campaign_tags.filter(t => t !== tag);
  } else {
    campaign.value.recipient_tags = campaign.value.recipient_tags.filter(t => t !== tag);
  }
};

const sendOrScheduleLabel = computed(() => {
  return campaign.value.start_time ? 'Schedule campaign' : 'Send campaign now';
});

const handleMainAction = () => {
  const nextStatus = campaign.value.start_time ? 'scheduled' : 'sending';
  saveCampaign(nextStatus);
};

</script>

<template>
  <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
    <PageTitle class="mb-0">{{ props.id ? 'Edit Campaign' : 'Create Campaign' }}</PageTitle>
    <router-link to="/campaigns" class="btn btn-ghost  gap-2">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
      Back to Campaigns
    </router-link>
  </div>

  <div v-if="loading" class="flex justify-center py-12">
    <Loading />
  </div>

  <div v-else>
    <div v-if="error" class="alert alert-error mb-6 ">
      <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
      <span>{{ error }}</span>
    </div>
    <div v-if="success" class="alert alert-success mb-6 ">
      <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
      <span>{{ success }}</span>
    </div>

    <div class="card bg-base-100 mb-8">
      <div class="card-body">
        <h2 class="card-title text-sm uppercase opacity-50">Campaign title</h2>
        <fieldset class="fieldset p-0">
          <input v-model="campaign.title" type="text" placeholder="Internal campaign title" class="input input-bordered w-full" required />
          <p class="fieldset-label text-xs">Note: This is used internally only.</p>
        </fieldset>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Left Column: Settings -->
      <div class="lg:col-span-1 space-y-6">
        <div class="card bg-base-100 ">
          <div class="card-body">
            <h2 class="card-title text-sm uppercase opacity-50">Campaign Settings</h2>
            
            <fieldset class="fieldset">
              <legend class="fieldset-legend">Sender</legend>
              <input v-model="campaign.sender" type="text" placeholder="e.g. MyCompany" class="input input-bordered w-full" />
            </fieldset>

            <fieldset class="fieldset">
              <legend class="fieldset-legend">Campaign Tags</legend>
              <div class="flex gap-2 w-full mb-2">
                <input v-model="campaignTagInput" type="text" placeholder="Add tag..." class="input input-bordered  flex-grow" @keydown.enter.prevent="addTag('campaign')" />
                <button type="button" @click="addTag('campaign')" class="btn ">Add</button>
              </div>
              <div class="flex flex-wrap gap-1">
                <div v-for="tag in campaign.campaign_tags" :key="tag" class="badge badge-primary gap-1">
                  {{ tag }}
                  <button type="button" @click="removeTag('campaign', tag)" class="btn btn-ghost btn-xs btn-circle -me-2">✕</button>
                </div>
              </div>
            </fieldset>

            <fieldset class="fieldset">
              <legend class="fieldset-legend">Recipient Tags</legend>
              <div class="dropdown w-full">
                <div tabindex="0" role="button" class="select select-bordered w-full flex items-center justify-between">
                  <span>
                    {{ campaign.recipient_tags.length }} tags selected
                  </span>
                </div>
                <ul tabindex="0" class="menu dropdown-content bg-base-100 rounded-box z-50 w-full p-2 shadow-lg max-h-64 overflow-y-auto border border-base-200">
                  <li v-if="allRecipientTags.length === 0" class="p-4 text-center text-sm opacity-50">
                    No contacts found or no contacts are associated with a tag.
                  </li>
                  <li v-for="tag in allRecipientTags" :key="tag.slug">
                    <label class="label cursor-pointer justify-start gap-3 w-full py-2">
                      <input type="checkbox" v-model="campaign.recipient_tags" :value="tag.slug" class="checkbox checkbox-sm" />
                      <span class="label-text flex-grow">{{ tag.name }}</span>
                      <span class="badge badge-sm badge-ghost opacity-50">{{ tag.count }}</span>
                    </label>
                  </li>
                </ul>
              </div>
              <div class="mt-4 space-y-2">
                <label class="label cursor-pointer justify-start gap-2 p-0">
                  <input type="radio" v-model="campaign.recipient_tags_logic" value="any" class="radio radio-sm radio-primary" />
                  <span class="label-text text-xs">Recipients with ANY of selected tags</span>
                </label>
                <label class="label cursor-pointer justify-start gap-2 p-0">
                  <input type="radio" v-model="campaign.recipient_tags_logic" value="all" class="radio radio-sm radio-primary" />
                  <span class="label-text text-xs">Recipients with ALL of selected tags</span>
                </label>
              </div>
              <div class="mt-2 text-xs font-semibold flex items-center gap-2">
                <span>Estimated total recipients:</span>
                <span v-if="fetchingRecipientCount" class="loading loading-spinner loading-xs"></span>
                <span v-else class="text-primary">{{ campaign.recipients_count }}</span>
              </div>
            </fieldset>

            <fieldset class="fieldset">
              <legend class="fieldset-legend">Start time</legend>
              <input v-model="campaign.start_time" type="datetime-local" class="input input-bordered w-full" />
              <p class="fieldset-label text-xs">Empty for immediate sending, or set a future date.</p>
            </fieldset>

            <fieldset class="fieldset">
              <legend class="fieldset-legend">Status</legend>
              <select v-model="campaign.status" class="select select-bordered w-full">
                <option value="draft">Draft</option>
                <option value="scheduled">Scheduled</option>
                <option value="sending">Sending</option>
                <option value="sent">Sent</option>
              </select>
            </fieldset>
          </div>
        </div>
      </div>

      <!-- Right Column: Message & Calculations -->
      <div class="lg:col-span-2 space-y-6">
        <div class="card bg-base-100  h-full">
          <div class="card-body">
            <h2 class="card-title text-sm uppercase opacity-50">Message</h2>
            
            <textarea 
              v-model="campaign.message" 
              class="textarea textarea-bordered h-64 w-full font-mono" 
              placeholder="Type your message here..."
            ></textarea>

            <div class="mt-4 space-y-4">
              <!-- Calculations -->
              <div class="flex flex-wrap gap-4 items-center">
                <div class="stats  bg-base-200">
                  <div class="stat py-2 px-4">
                    <div class="stat-title text-xs">Characters</div>
                    <div class="stat-value text-lg">{{ smsStats.characters }}</div>
                  </div>
                  <div class="stat py-2 px-4">
                    <div class="stat-title text-xs">Messages</div>
                    <div class="stat-value text-lg">{{ smsStats.messages }}</div>
                  </div>
                </div>

                <div v-if="smsStats.isUcs2" class="badge badge-warning gap-2 py-4">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-4 h-4 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                  UCS2 detected: {{ smsStats.failedChars.join(' ') }}
                </div>
                <div v-else class="badge badge-success gap-2 py-4">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-4 h-4 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                  GSM 03.38 compatible
                </div>
              </div>

              <div v-if="smsStats.isUcs2" class="text-xs text-warning">
                Be aware that special symbols cause UCS2 encoding, which allows fewer characters per message (70 instead of 160) before being split.
              </div>

              <div class="alert alert-info py-2 px-4 text-xs shadow-none">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Notice: This is a calculation and final amount may vary, for instance if replacement tags are used.</span>
              </div>
            </div>

            <div class="card-actions justify-end mt-8 gap-4">
              <button @click="saveCampaign('draft')" class="btn btn-ghost" :disabled="saving">
                Save as draft
              </button>
              <button @click="handleMainAction" class="btn btn-primary" :disabled="saving || campaign.recipient_tags.length === 0 || campaign.recipients_count === 0">
                <span v-if="saving" class="loading loading-spinner"></span>
                {{ sendOrScheduleLabel }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
