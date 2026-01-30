<script setup lang="ts">
import {ref, onMounted, watch} from 'vue';
import {useParentIframeStore} from '@/stores/parentIframe.ts';
import {useRouter} from 'vue-router';
import {defineStore} from 'pinia';
import PageTitle from "@/components/PageTitle.vue";
import Loading from "@/components/Loading.vue";

const useCampaignsTableStore = defineStore('campaigns-table', {
  state: () => ({
    visibleColumns: ['title', 'recipients', 'tags', 'status', 'created']
  }),
  persist: true
});

const tableStore = useCampaignsTableStore();

const parentIframe = useParentIframeStore();
const router = useRouter();

const campaigns = ref<any[]>([]);
const loading = ref(true);
const pagination = ref({total: 0, pages: 1, current: 1});
const filters = ref({
  s: '',
  status: 'any',
  orderby: 'date',
  order: 'DESC'
});

const fetchCampaigns = async () => {
  loading.value = true;
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_campaigns', {
      paged: pagination.value.current,
      ...filters.value
    }) as any;
    if (response && response.success) {
      campaigns.value = response.data.campaigns;
      pagination.value = response.data.pagination;
    }
  } catch (error) {
    console.error('Failed to fetch campaigns:', error);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchCampaigns();
});

watch(() => filters.value, () => {
  pagination.value.current = 1;
  fetchCampaigns();
}, {deep: true});

const setPage = (page: number) => {
  pagination.value.current = page;
  fetchCampaigns();
};

const editCampaign = async (campaign: any) => {
  if (campaign.status === 'scheduled') {
    if (!confirm('This campaign is scheduled. Editing it will cancel the scheduling and revert it to a draft. Are you sure?')) {
      return;
    }
    try {
      const response = await parentIframe.ajaxPost('gatewayapi_revert_campaign_to_draft', {id: campaign.id}) as any;
      if (!response || !response.success) {
        console.error('Failed to revert campaign to draft:', response);
        return;
      }
    } catch (error) {
      console.error('Failed to revert campaign to draft:', error);
      return;
    }
  }
  router.push('/campaigns/' + campaign.id);
};

const toggleSort = (column: string) => {
  if (filters.value.orderby === column) {
    filters.value.order = filters.value.order === 'ASC' ? 'DESC' : 'ASC';
  } else {
    filters.value.orderby = column;
    filters.value.order = 'ASC';
  }
};

const deleteCampaign = async (id: number, force = false) => {
  if (!confirm(force ? 'Are you sure you want to delete this campaign permanently?' : 'Are you sure you want to move this campaign to trash?')) return;

  try {
    const response = await parentIframe.ajaxPost('gatewayapi_delete_campaign', {id, force}) as any;
    if (response && response.success) {
      fetchCampaigns();
    }
  } catch (error) {
    console.error('Failed to delete campaign:', error);
  }
};

const restoreCampaign = async (id: number) => {
  try {
    const response = await parentIframe.ajaxPost('gatewayapi_restore_campaign', {id}) as any;
    if (response && response.success) {
      fetchCampaigns();
    }
  } catch (error) {
    console.error('Failed to restore campaign:', error);
  }
};

const formatDate = (dateStr: string) => {
  if (!dateStr) return '-';
  return new Date(dateStr).toLocaleString();
};

const columns = [
  {key: 'title', label: 'Title', sortable: true},
  {key: 'sender', label: 'Sender', sortable: true},
  {key: 'recipients', label: 'Recipients', sortable: false},
  {key: 'tags', label: 'Tags', sortable: false},
  {key: 'start_time', label: 'Start time', sortable: true},
  {key: 'end_time', label: 'Ended time', sortable: false},
  {key: 'status', label: 'Status', sortable: true},
  {key: 'created', label: 'Date created', sortable: true, sortKey: 'date'},
];
</script>

<template>
  <div class="flex justify-between items-center mb-4">
    <PageTitle icon="lucide:message-circle-more">
      Campaigns
      <template #actions>
        <router-link to="/campaigns/new" class="btn btn-primary">
          <Icon icon="lucide:plus"/>
          Create Campaign
        </router-link>

      </template>
    </PageTitle>
  </div>

  <!-- FILTERS -->
  <div class="card bg-base-100 border-base-300 border-2 mb-8">
    <div class="card-body p-4">
      <div class="flex flex-wrap gap-4  -mt-2">
        <fieldset class="fieldset text-base">
          <legend class="fieldset-legend">Search</legend>
          <input v-model.lazy="filters.s" type="text" placeholder="Search title..." class="input input-bordered "/>
        </fieldset>

        <fieldset class="fieldset text-base">
          <legend class="fieldset-legend">Status</legend>
          <select v-model="filters.status" class="select pe-10">
            <option value="any">Any Status</option>
            <option value="draft">Draft</option>
            <option value="scheduled">Scheduled</option>
            <option value="sending">Sending</option>
            <option value="sent">Sent</option>
            <option value="trash">Trash</option>
          </select>
        </fieldset>

        <fieldset class="fieldset text-base">
          <legend class="fieldset-legend">Columns</legend>
          <div class="dropdown">
            <div tabindex="0" role="button" class="select pe-10">
              {{ tableStore.visibleColumns.length }} out of {{ columns.length }} selected
            </div>
            <ul tabindex="0"
                class="menu dropdown-content bg-base-100 rounded-box z-1 w-52 py-5 px-3 shadow-sm">
              <li v-for="column in columns" :key="column.key">
                <label class="label cursor-pointer justify-start gap-3 w-full py-2">
                  <input type="checkbox" v-model="tableStore.visibleColumns" :value="column.key"
                         class="checkbox checkbox-sm"/>
                  <span class="label-text">{{ column.label }}</span>
                </label>
              </li>
            </ul>
          </div>
        </fieldset>
      </div>
    </div>
  </div>

  <!-- TABLE -->
  <div class="card bg-base-100 border-base-300 border-2">
    <table class="table md:table-md">
      <thead>
      <tr>
        <template v-for="column in columns" :key="column.key">
          <th v-if="tableStore.visibleColumns.includes(column.key)"
              :class="{ 'cursor-pointer': column.sortable }"
              @click="column.sortable ? toggleSort(column.sortKey || column.key) : null">
            {{ column.label }}
            <span v-if="column.sortable && filters.orderby === (column.sortKey || column.key)">
              {{ filters.order === 'ASC' ? '↑' : '↓' }}
            </span>
          </th>
        </template>
        <th class="text-right">Actions</th>
      </tr>
      </thead>
      <tbody>
      <tr v-if="loading">
        <td :colspan="tableStore.visibleColumns.length + 1" class="p-0">
          <Loading/>
        </td>
      </tr>
      <tr v-else-if="campaigns.length === 0">
        <td :colspan="tableStore.visibleColumns.length + 1" class="text-center p-12 text-base-content/50">No campaigns
          found.
        </td>
      </tr>
      <tr v-for="campaign in campaigns" :key="campaign.id" class="hover">
        <template v-for="column in columns" :key="column.key">
          <td v-if="tableStore.visibleColumns.includes(column.key)">
            <template v-if="column.key === 'title'">{{ campaign.title }}</template>
            <template v-else-if="column.key === 'sender'">{{ campaign.sender }}</template>
            <template v-else-if="column.key === 'recipients'">{{ campaign.recipients_count }}</template>
            <template v-else-if="column.key === 'tags'">
              <div class="flex flex-wrap gap-1">
                <span class="tooltip" v-for="tag in (campaign.campaign_tags || []).slice(0, 2)" :key="tag" :data-tip="tag">
                <span class="badge badge-ghost truncate max-w-16 block">{{ tag }}</span>
                  </span>
                <span v-if="(campaign.campaign_tags || []).length > 2"
                      class="badge badge-outline tooltip"
                      data-tip="More tags available">+{{ campaign.campaign_tags.length - 2 }}</span>
              </div>
            </template>
            <template v-else-if="column.key === 'start_time'">{{ formatDate(campaign.start_time) }}</template>
            <template v-else-if="column.key === 'end_time'">{{ formatDate(campaign.end_time) }}</template>
            <template v-else-if="column.key === 'status'">
              <span class="badge capitalize" :class="{
                'badge-neutral': campaign.status === 'draft',
                'badge-info': campaign.status === 'scheduled',
                'badge-warning': campaign.status === 'sending',
                'badge-success': campaign.status === 'sent',
                'badge-ghost': campaign.is_trash
              }">{{ campaign.status }}</span>
            </template>
            <template v-else-if="column.key === 'created'">{{ formatDate(campaign.created) }}</template>
          </td>
        </template>
        <td>
          <div class="flex justify-end gap-1">
            <template v-if="!campaign.is_trash">
              <button @click="editCampaign(campaign)" class="btn btn-primary tooltip tooltip-left"
                           data-tip="Edit the campaign">
                <Icon icon="lucide:edit"/>
              </button>
              <button @click="deleteCampaign(campaign.id)" class="btn btn-error btn-outline tooltip tooltip-left"
                      data-tip="Move the campaign to trash">
                <Icon icon="lucide:trash"/>
              </button>
            </template>
            <template v-else>
              <button @click="restoreCampaign(campaign.id)" class="btn btn-success tooltip tooltip-left"
                      data-tip="Restore the campaign">
                <Icon icon="lucide:undo-2"/>
              </button>
              <button @click="deleteCampaign(campaign.id, true)" class="btn btn-outline btn-error tooltip tooltip-left"
                      data-tip="Permanently delete the campaign">
                <Icon icon="lucide:shredder"/>
              </button>
            </template>
          </div>
        </td>
      </tr>
      </tbody>
    </table>

    <div v-if="pagination.pages > 1" class="flex justify-center p-4">
      <div class="join">
        <button
            v-for="p in pagination.pages"
            :key="p"
            class="join-item btn "
            :class="{ 'btn-active': p === pagination.current }"
            @click="setPage(p)"
        >
          {{ p }}
        </button>
      </div>
    </div>
  </div>
</template>
