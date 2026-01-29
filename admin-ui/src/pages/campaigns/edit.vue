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

const allCampaignTags = ref<any[]>([]);
const allRecipientTags = ref<any[]>([]);
const fetchingRecipientCount = ref(false);
const serverTime = ref('');
const serverTimezone = ref('');

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

const fetchCampaignTags = async () => {
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_campaign_tags') as any;
    if (response && response.success) {
      allCampaignTags.value = response.data;
    }
  } catch (err) {
    console.error('Failed to fetch campaign tags:', err);
  }
};

const fetchServerTime = async () => {
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_server_time') as any;
    if (response && response.success) {
      serverTime.value = response.data.current_time;
      serverTimezone.value = response.data.timezone;
    }
  } catch (err) {
    console.error('Failed to fetch server time:', err);
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
  fetchCampaignTags();
  fetchServerTime();
});

const formatScheduledMessage = (startTime: string) => {
  if (!startTime) return 'now';

  const now = new Date();
  const start = new Date(startTime);
  const diffMs = start.getTime() - now.getTime();
  const diffHours = diffMs / (1000 * 60 * 60);

  if (diffMs <= 0) return 'now';
  if (diffHours <= 72) {
    const hours = Math.round(diffHours);
    if (hours === 0) return 'now';
    return `in ${hours} hour${hours > 1 ? 's' : ''}`;
  }

  return `at ${start.toLocaleDateString()} ${start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
};

const saveCampaign = async (newStatus?: string) => {
  saving.value = true;
  error.value = '';
  success.value = '';

  const sender = campaign.value.sender;
  if (sender) {
    const isDigitsOnly = /^\d+$/.test(sender);
    if (isDigitsOnly) {
      if (sender.length > 18) {
        error.value = 'Sender cannot be more than 18 digits';
        saving.value = false;
        return;
      }
    } else {
      if (sender.length > 11) {
        error.value = 'Sender cannot be more than 11 characters when it contains non-digit characters';
        saving.value = false;
        return;
      }
    }
  }

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
      if (['scheduled', 'sending'].includes(campaign.value.status)) {
        success.value = `Campaign was successfully scheduled for sending ${formatScheduledMessage(campaign.value.start_time)}`;
      } else {
        success.value = 'Campaign saved successfully!';
      }
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
  const name = window.prompt('Enter new campaign tag name:');
  if (name) {
    const tag = name.trim();
    if (tag) {
      if (!campaign.value.campaign_tags.includes(tag)) {
        campaign.value.campaign_tags.push(tag);
      }
      if (!allCampaignTags.value.find(t => t.name === tag)) {
        allCampaignTags.value.push({ name: tag, count: 0 });
      }
    }
  }
};

const removeTag = (type: 'recipient', tag: string) => {
  if (type === 'recipient') {
    campaign.value.recipient_tags = campaign.value.recipient_tags.filter(t => t !== tag);
  }
};

const sendOrScheduleLabel = computed(() => {
  return campaign.value.start_time ? 'Schedule campaign' : 'Send campaign now';
});

const minDateTime = computed(() => {
  return serverTime.value || undefined;
});

const isReadOnly = computed(() => {
  return ['scheduled', 'sending', 'sent'].includes(campaign.value.status);
});

const showConfirmationDialog = (): boolean => {
  const action = campaign.value.start_time ? 'schedule' : 'send';
  const timing = formatScheduledMessage(campaign.value.start_time);
  const message = `Are you sure you want to ${action} this campaign ${timing}?\n\nEstimated recipients: ${campaign.value.recipients_count}\n\nNote: The final recipient count will be calculated at send-time and may differ from this estimate.`;

  return window.confirm(message);
};

const handleMainAction = () => {
  if (!showConfirmationDialog()) {
    return;
  }

  const nextStatus = campaign.value.start_time ? 'scheduled' : 'sending';
  saveCampaign(nextStatus);
};

const testSms = async () => {
  const recipient = window.prompt('Enter phone number (MSISDN):');
  if (!recipient) return;

  saving.value = true;
  error.value = '';
  success.value = '';

  const sender = campaign.value.sender;
  if (sender) {
    const isDigitsOnly = /^\d+$/.test(sender);
    if (isDigitsOnly) {
      if (sender.length > 18) {
        error.value = 'Sorry, the test SMS failed. This is the response we got from GatewayAPI: Sender cannot be more than 18 digits';
        saving.value = false;
        return;
      }
    } else {
      if (sender.length > 11) {
        error.value = 'Sorry, the test SMS failed. This is the response we got from GatewayAPI: Sender cannot be more than 11 characters when it contains non-digit characters';
        saving.value = false;
        return;
      }
    }
  }

  try {
    const response = await parentIframe.ajaxPost('gatewayapi_test_sms', {
      recipient,
      message: campaign.value.message,
      sender: campaign.value.sender
    }) as any;

    if (response && response.success) {
      success.value = response.data.message || 'Test SMS sent successfully';
    } else {
      error.value = response?.data?.message || 'Failed to send test SMS';
    }
  } catch (err) {
    console.error('Failed to send test SMS:', err);
    error.value = 'An error occurred while sending the test SMS.';
  } finally {
    saving.value = false;
  }
};

</script>

<template>
  <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
    <PageTitle class="mb-0" icon="lucide:message-circle-more">{{ props.id ? 'Edit Campaign' : 'Create Campaign' }}</PageTitle>
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
        <fieldset class="fieldset p-0" :disabled="isReadOnly">
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

            <fieldset class="fieldset tooltip tooltip-right" data-tip="The sender must be either up to 18 digits, or max 11 characters if it contains anything except digits. This works differently in various countries, so check the documentation for more information or contact our support." :disabled="isReadOnly">
              <legend class="fieldset-legend">Sender</legend>
              <input v-model="campaign.sender" type="text" placeholder="e.g. MyCompany" class="input input-bordered w-full" />
            </fieldset>

            <fieldset class="fieldset" :disabled="isReadOnly">
              <legend class="fieldset-legend">Campaign Tags</legend>
              <div class="flex gap-3 relative  tooltip tooltip-right"  data-tip="Campaign tags are used for your own organizational purposes and does not affect the final message or sending.">
                <div class="dropdown w-full static">
                  <div tabindex="0" role="button" class="select select-bordered w-full flex items-center justify-between mb-1" :class="{'pointer-events-none opacity-50': isReadOnly}">
                    <span>
                      {{ campaign.campaign_tags.length }} tags selected
                    </span>
                  </div>
                  <ul v-if="!isReadOnly" tabindex="0" class="menu dropdown-content bg-base-100 rounded-box z-50 w-full p-2 shadow-lg max-h-64 overflow-y-auto border border-base-200">
                    <li v-if="allCampaignTags.length === 0" class="p-4 text-center text-sm opacity-50">
                      No campaign tags found.
                    </li>
                    <li v-for="tag in allCampaignTags" :key="tag.name">
                      <label class="label cursor-pointer justify-start gap-3 w-full py-2">
                        <input type="checkbox" v-model="campaign.campaign_tags" :value="tag.name" class="checkbox checkbox-sm" />
                        <span class="label-text flex-grow">{{ tag.name }}</span>
                        <span class="badge badge-sm badge-ghost opacity-50">{{ tag.count }}</span>
                      </label>
                    </li>
                  </ul>
                </div>
                <button v-if="!isReadOnly" type="button" @click="addTag('campaign')" class="btn btn-outline btn-primary tooltip" data-tip="Add new tag"><Icon icon="lucide:plus" /></button>
              </div>
            </fieldset>

            <fieldset class="fieldset" :disabled="isReadOnly">
              <legend class="fieldset-legend">Recipient Tags</legend>
              <div class="dropdown w-full tooltip tooltip-right" data-tip="Recipient tags are used to select contacts. You send to every contact that has the selected tags.">
                <div tabindex="0" role="button" class="select select-bordered w-full flex items-center justify-between" :class="{'pointer-events-none opacity-50': isReadOnly}">
                  <span>
                    {{ campaign.recipient_tags.length }} tags selected
                  </span>
                </div>
                <ul v-if="!isReadOnly" tabindex="0" class="menu dropdown-content bg-base-100 rounded-box z-50 w-full p-2 shadow-lg max-h-64 overflow-y-auto border border-base-200">
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
              <div class="mt-4 space-y-2 tooltip tooltip-right" data-tip="Do you want to send to recipients which just has one of the tags, or do you want to only send to recipients who has ALL of the selected tags? You must select at least one tag.">
                <label class="label cursor-pointer justify-start gap-2 p-0">
                  <input type="radio" v-model="campaign.recipient_tags_logic" value="any" class="radio radio-sm radio-primary" :disabled="isReadOnly" />
                  <span class="label-text text-xs">Recipients with ANY of selected tags</span>
                </label>
                <label class="label cursor-pointer justify-start gap-2 p-0">
                  <input type="radio" v-model="campaign.recipient_tags_logic" value="all" class="radio radio-sm radio-primary" :disabled="isReadOnly" />
                  <span class="label-text text-xs">Recipients with ALL of selected tags</span>
                </label>
              </div>
              <div class="mt-2 text-xs font-semibold flex items-center gap-2">
                <span>Estimated total recipients:</span>
                <span v-if="fetchingRecipientCount" class="loading loading-spinner loading-xs"></span>
                <span v-else class="text-primary">{{ campaign.recipients_count }}</span>
              </div>
            </fieldset>

            <fieldset class="fieldset tooltip tooltip-right" data-tip="When should sending start? You should input the date/time in the timezone of the website." :disabled="isReadOnly">
              <legend class="fieldset-legend">Start time</legend>
              <input v-model="campaign.start_time" type="datetime-local" :min="minDateTime"
                     class="input input-bordered w-full"/>
              <p class="fieldset-label text-xs">
                Empty for immediate sending, or set a future date.
                <span v-if="serverTimezone" class="block opacity-70">Server timezone: {{ serverTimezone }}</span>
              </p>
            </fieldset>

            <fieldset class="fieldset">
              <legend class="fieldset-legend">Current status</legend>
              <div role="alert" class="alert w-full" :class="{
                'alert-base': campaign.status === 'draft',
                'alert-info': campaign.status === 'scheduled',
                'alert-warning': campaign.status === 'sending',
                'alert-success': campaign.status === 'sent'
              }">
                <span class="capitalize">{{ campaign.status }}</span>
              </div>
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
              :disabled="isReadOnly"
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
                  UCS2 detected:
                  <code v-for="(char, index) in smsStats.failedChars" :key="index" class="kbd tooltip"
                        :data-tip="`U+${char.codePointAt(0).toString(16).toUpperCase().padStart(4, '0')}`">{{
                      char
                    }}</code>
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
              <button @click="saveCampaign('draft')" class="btn btn-base" :disabled="saving || isReadOnly">
                <Icon icon="lucide:message-circle-dashed" />
                Save as draft
              </button>
              <button @click="testSms" class="btn btn-base tooltip" data-tip="Enter a phone number to immediately send this message as a test. SMS status will be shown at the top of the page.">
                <Icon icon="lucide:message-circle-question-mark" />
                Test SMS
              </button>
              <button @click="handleMainAction" class="btn btn-primary" :disabled="saving || isReadOnly || campaign.recipient_tags.length === 0 || campaign.recipients_count === 0">
                <span v-if="saving" class="loading loading-spinner"></span>
                <Icon v-else icon="lucide:send" />
                {{ sendOrScheduleLabel }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
