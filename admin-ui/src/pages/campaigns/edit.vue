<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { useParentIframeStore } from '@/stores/parentIframe.ts';
import { useStateStore } from '@/stores/state.ts';
import { useRouter } from 'vue-router';
import PageTitle from "@/components/PageTitle.vue";
import Loading from "@/components/Loading.vue";
import SmsEditor from "@/components/SmsEditor.vue";
import {Icon} from "@iconify/vue";

const props = defineProps<{
  id?: string;
}>();

const parentIframe = useParentIframeStore();
const state = useStateStore();
const router = useRouter();

const navigateToSettings = () => {
  const link = 'admin.php?page=gatewayapi-settings#/settings';
  if (window.parent) {
    window.parent.location.href = link;
  } else {
    window.location.href = link;
  }
};

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
const defaultSender = ref('');
const smsTags = computed(() => {
  const tags = [
    { tag: '%NAME%', label: 'Name', category: 'Standard Fields' },
    { tag: '%MSISDN%', label: 'MSISDN', category: 'Standard Fields' },
  ];

  if (contactFields.value && contactFields.value.length > 0) {
    contactFields.value.forEach(field => {
      tags.push({
        tag: '%' + field.title + '%',
        label: field.title,
        category: 'Custom Fields'
      });
    });
  }

  return tags;
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

      if (campaign.value.status === 'scheduled') {
        if (confirm('This campaign is scheduled. Editing it will cancel the scheduling and revert it to a draft. Are you sure?')) {
          try {
            await parentIframe.ajaxPost('gatewayapi_revert_campaign_to_draft', {id: campaign.value.id});
            campaign.value.status = 'draft';
          } catch (err) {
            console.error('Failed to revert campaign to draft:', err);
          }
        } else {
          router.push('/campaigns');
        }
      }
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
    const response = await parentIframe.ajaxGet('gatewayapi_get_tags', {}) as any;
    if (response && response.success) {
      allRecipientTags.value = response.data;
    }
  } catch (err) {
    console.error('Failed to fetch recipient tags:', err);
  }
};

const fetchCampaignTags = async () => {
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_campaign_tags', {}) as any;
    if (response && response.success) {
      allCampaignTags.value = response.data;
    }
  } catch (err) {
    console.error('Failed to fetch campaign tags:', err);
  }
};

const fetchServerTime = async () => {
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_server_time', {}) as any;
    if (response && response.success) {
      serverTime.value = response.data.current_time;
      serverTimezone.value = response.data.timezone;
      defaultSender.value = response.data.default_sender;
    }
  } catch (err) {
    console.error('Failed to fetch server time:', err);
  }
};

const fetchContactFields = async () => {
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_contact_fields', {}) as any;
    if (response && response.success) {
      contactFields.value = response.data;
    }
  } catch (err) {
    console.error('Failed to fetch contact fields:', err);
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
  fetchContactFields();
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

  const sender = campaign.value.sender || defaultSender.value;
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

const addTag = () => {
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


const sendOrScheduleLabel = computed(() => {
  return campaign.value.start_time ? 'Schedule campaign' : 'Send campaign now';
});

const minDateTime = computed(() => {
  return serverTime.value || undefined;
});

const isReadOnly = computed(() => {
  return ['scheduled', 'sending', 'sent'].includes(campaign.value.status);
});

const isSendingDisabled = computed(() => {
  return state.hasKey === false || state.keyIsValid === false;
});

const showConfirmationDialog = (): boolean => {
  const action = campaign.value.start_time ? 'schedule' : 'send';
  const timing = formatScheduledMessage(campaign.value.start_time);
  const message = `Are you sure you want to ${action} this campaign ${timing}?\n\nEstimated recipients: ${campaign.value.recipients_count}\n\nNote: The final recipient count will be calculated at send-time and may differ from this estimate.`;

  return window.confirm(message);
};

const handleMainAction = () => {
  if (!campaign.value.message || campaign.value.message.trim().length === 0) {
    error.value = 'Please enter a message.';
    return;
  }

  if (campaign.value.recipient_tags.length === 0) {
    error.value = 'Please select at least one recipient tag.';
    return;
  }

  if (!showConfirmationDialog()) {
    return;
  }

  const nextStatus = campaign.value.start_time ? 'scheduled' : 'sending';
  saveCampaign(nextStatus);
};

const testSms = async () => {
  if (!campaign.value.message || campaign.value.message.trim().length === 0) {
    error.value = 'Please enter a message.';
    return;
  }

  const recipient = window.prompt('Enter phone number (MSISDN):');
  if (!recipient) return;

  saving.value = true;
  error.value = '';
  success.value = '';

  const sender = campaign.value.sender || defaultSender.value;
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
      sender: campaign.value.sender || defaultSender.value
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
    <PageTitle class="mb-0" icon="lucide:message-circle-more">
      {{ props.id ? 'Edit Campaign' : 'Create Campaign' }}
    <template #actions>
      <router-link to="/campaigns" class="btn btn-soft gap-2">
        <Icon icon="lucide:arrow-left" />
        Back to Campaigns
      </router-link>
    </template>
    </PageTitle>
  </div>

  <div v-if="loading" class="flex justify-center py-12">
    <Loading />
  </div>

  <div v-else>
    <div v-if="isSendingDisabled" class="mb-8">
      <div role="alert" class="alert alert-error shadow-lg">
        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <span>No valid API key detected. No sending is possible until an API key has been entered under <a href="#" @click.prevent="navigateToSettings" class="link font-bold">Settings</a>.</span>
      </div>
    </div>

    <div class="card bg-base-100 border-base-300 border-2 mb-8">
      <div class="card-body">
        <h2 class="card-title text-sm uppercase opacity-50">Campaign title</h2>

        <div v-if="error" class="alert alert-error mb-6 ">
          <Icon icon="lucide:circle-alert" />
          <span>{{ error }}</span>
        </div>
        <div v-if="success" class="alert alert-success mb-6 ">
          <Icon icon="lucide:circle-check-big" />
          <span>{{ success }}</span>
        </div>

        <fieldset class="fieldset text-base p-0 tooltip" :disabled="isReadOnly" data-tip="This is used internally only for your own organizational purposes.">
          <input v-model="campaign.title" type="text" placeholder="Internal campaign title" class="input input-bordered w-full" required />
        </fieldset>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Left Column: Settings -->
      <div class="lg:col-span-1 space-y-6">
        <div class="card bg-base-100 border-base-300 border-2">
          <div class="card-body">
            <h2 class="card-title text-sm uppercase opacity-50">Campaign Settings</h2>

            <fieldset class="fieldset text-base tooltip tooltip-right" data-tip="The sender must be either up to 18 digits, or max 11 characters if it contains anything except digits. This works differently in various countries, so check the documentation for more information or contact our support." :disabled="isReadOnly">
              <legend class="fieldset-legend">Sender</legend>
              <input v-model="campaign.sender" type="text" :placeholder="defaultSender || 'e.g. MyCompany'" class="input input-bordered w-full" />
            </fieldset>

            <fieldset class="fieldset text-base" :disabled="isReadOnly">
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
                        <span class="label-text flex-grow relative">
                          <span class="absolute w-full h-full truncate">{{ tag.name }}</span>
                          &nbsp;
                        </span>
                        <span class="badge badge-sm badge-ghost opacity-50">{{ tag.count }}</span>
                      </label>
                    </li>
                  </ul>
                </div>
                <button v-if="!isReadOnly" type="button" @click="addTag()" class="btn btn-outline btn-primary tooltip" data-tip="Add new tag"><Icon icon="lucide:plus" /></button>
              </div>
            </fieldset>

            <fieldset class="fieldset text-base" :disabled="isReadOnly">
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
                      <span class="label-text flex-grow relative">
                        <span class="absolute w-full h-full truncate">{{ tag.name }}</span>
                        &nbsp;
                      </span>
                      <span class="badge badge-sm badge-ghost opacity-50">{{ tag.count }}</span>
                    </label>
                  </li>
                </ul>
              </div>
              <div class="mt-4 space-y-2 tooltip tooltip-right" data-tip="Do you want to send to recipients which just has one of the tags, or do you want to only send to recipients who has ALL of the selected tags? You must select at least one tag.">
                <label class="label cursor-pointer justify-start gap-2 p-0">
                  <input type="radio" v-model="campaign.recipient_tags_logic" value="any" class="radio radio-sm radio-primary" :disabled="isReadOnly" />
                  <span class="label-text text-sm">Recipients with ANY of selected tags</span>
                </label>
                <label class="label cursor-pointer justify-start gap-2 p-0">
                  <input type="radio" v-model="campaign.recipient_tags_logic" value="all" class="radio radio-sm radio-primary" :disabled="isReadOnly" />
                  <span class="label-text text-sm">Recipients with ALL of selected tags</span>
                </label>
              </div>
              <div class="mt-2 text-sm font-semibold flex items-center gap-2">
                <span>Estimated total recipients:</span>
                <span v-if="fetchingRecipientCount" class="loading loading-spinner loading-xs"></span>
                <span v-else class="text-primary">{{ campaign.recipients_count }}</span>
              </div>
            </fieldset>

            <fieldset class="fieldset text-base tooltip tooltip-right" data-tip="When should sending start? You should input the date/time in the timezone of the website." :disabled="isReadOnly">
              <legend class="fieldset-legend">Start time</legend>
              <input v-model="campaign.start_time" type="datetime-local" :min="minDateTime"
                     class="input input-bordered w-full"/>
              <p class="fieldset-label text-sm block">
                Empty for immediate sending, or set a future date.
                <span v-if="serverTimezone" class="block">Server timezone: {{ serverTimezone }}</span>
              </p>
            </fieldset>

            <fieldset class="fieldset text-base">
              <legend class="fieldset-legend">Current status</legend>
              <div role="alert" class="alert w-full" :class="{
                'alert-base': campaign.status === 'draft',
                'alert-info': campaign.status === 'scheduled',
                'alert-warning': campaign.status === 'sending',
                'alert-success': campaign.status === 'sent'
              }">
                <Icon v-if="campaign.status === 'sent'" icon="lucide:circle-check-big" />
                <Icon v-else-if="campaign.status !== 'draft'" icon="lucide:circle-alert" />
                <span class="capitalize">{{ campaign.status }}</span>
              </div>
            </fieldset>
          </div>
        </div>
      </div>

      <!-- Right Column: Message & Calculations -->
      <div class="lg:col-span-2 space-y-6">
        <SmsEditor
          v-model="campaign.message"
          :disabled="isReadOnly"
          :tags="smsTags"
        >
          <template #actions>
            <button @click="saveCampaign('draft')" class="btn btn-base" :disabled="saving || isReadOnly || isSendingDisabled">
              <Icon icon="lucide:message-circle-dashed" />
              Save as draft
            </button>
            <button @click="testSms" class="btn btn-base tooltip" data-tip="Enter a phone number to immediately send this message as a test. SMS status will be shown at the top of the page." :disabled="isSendingDisabled">
              <Icon icon="lucide:message-circle-question-mark" />
              Test SMS
            </button>
            <button @click="handleMainAction" class="btn btn-primary" :disabled="saving || isReadOnly || isSendingDisabled">
              <span v-if="saving" class="loading loading-spinner"></span>
              <Icon v-else icon="lucide:send" />
              {{ sendOrScheduleLabel }}
            </button>
          </template>
        </SmsEditor>
      </div>
    </div>
  </div>
</template>
