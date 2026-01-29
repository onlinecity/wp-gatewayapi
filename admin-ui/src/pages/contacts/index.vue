<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useParentIframeStore } from '@/stores/parentIframe.ts';
import { defineStore } from 'pinia';
import PageTitle from "@/components/PageTitle.vue";
import Loading from "@/components/Loading.vue";
import { Icon } from '@iconify/vue';

const useContactsTableStore = defineStore('contacts-table', {
  state: () => ({
    visibleColumns: ['name', 'flag', 'msisdn', 'tags', 'status', 'created']
  }),
  persist: true
});

const tableStore = useContactsTableStore();

const parentIframe = useParentIframeStore();

const contacts = ref<any[]>([]);
const loading = ref(true);
const pagination = ref({ total: 0, pages: 1, current: 1 });
const filters = ref({
  s: '',
  search_by: 'name',
  status: 'any',
  tag: '',
  country: '',
  orderby: 'date',
  order: 'DESC'
});

const tags = ref<any[]>([]);
const countries = ref<any[]>([]);
const exporting = ref(false);

const columns = [
  { id: 'name', label: 'Name', sortable: 'name' },
  { id: 'flag', label: 'Flag' },
  { id: 'msisdn', label: 'MSISDN', sortable: 'msisdn' },
  { id: 'country_code', label: 'Country code' },
  { id: 'country_name', label: 'Country name' },
  { id: 'tags', label: 'Tags' },
  { id: 'status', label: 'Status', sortable: 'status' },
  { id: 'created', label: 'Created', sortable: 'date' }
];

const fetchContacts = async () => {
  loading.value = true;
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_contacts', {
      paged: pagination.value.current,
      ...filters.value
    }) as any;
    if (response && response.success) {
      contacts.value = response.data.contacts;
      pagination.value = response.data.pagination;
    }
  } catch (error) {
    console.error('Failed to fetch contacts:', error);
  } finally {
    loading.value = false;
  }
};

const fetchTags = async () => {
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_tags', {}) as any;
    if (response && response.success) {
      tags.value = response.data;
    }
  } catch (error) {
    console.error('Failed to fetch tags:', error);
  }
};

const fetchCountries = async () => {
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_countries', {}) as any;
    if (response && response.success) {
      countries.value = response.data;
    }
  } catch (error) {
    console.error('Failed to fetch countries:', error);
  }
};

onMounted(() => {
  fetchContacts();
  fetchTags();
  fetchCountries();
});

watch(() => filters.value, () => {
  pagination.value.current = 1;
  fetchContacts();
}, { deep: true });

const setPage = (page: number) => {
  pagination.value.current = page;
  fetchContacts();
};

const toggleSort = (column: string) => {
  if (filters.value.orderby === column) {
    filters.value.order = filters.value.order === 'ASC' ? 'DESC' : 'ASC';
  } else {
    filters.value.orderby = column;
    filters.value.order = 'ASC';
  }
};

const deleteContact = async (id: number, force = false) => {
  if (!confirm(force ? 'Are you sure you want to delete this contact permanently?' : 'Are you sure you want to move this contact to trash?')) return;
  
  try {
    const response = await parentIframe.ajaxPost('gatewayapi_delete_contact', { id, force }) as any;
    if (response && response.success) {
      fetchContacts();
    }
  } catch (error) {
    console.error('Failed to delete contact:', error);
  }
};

const restoreContact = async (id: number) => {
  try {
    const response = await parentIframe.ajaxPost('gatewayapi_restore_contact', { id }) as any;
    if (response && response.success) {
      fetchContacts();
    }
  } catch (error) {
    console.error('Failed to restore contact:', error);
  }
};

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString();
};

const exportContacts = async () => {
  if (exporting.value) {
    return;
  }

  exporting.value = true;
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_contacts_export', {
      ...filters.value
    }) as any;

    if (response && response.success) {
      const contactsToExport = response.data.contacts;
      const headers = ['Name', 'MSISDN', 'Country Name', 'Country Code', 'Tags', 'Status'];
      const csvContent = [
        headers.join(';'),
        ...contactsToExport.map((c: any) => [
          `"${(c.name || '').replace(/"/g, '""')}"`,
          `"${(c.msisdn || '').replace(/"/g, '""')}"`,
          `"${(c.country_name || '').replace(/"/g, '""')}"`,
          `"${(c.country_code || '').replace(/"/g, '""')}"`,
          `"${(c.tags || '').replace(/"/g, '""')}"`,
          `"${(c.status || '').replace(/"/g, '""')}"`
        ].join(';'))
      ].join('\n');

      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      const url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', 'contacts-export.csv');
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }
  } catch (error) {
    console.error('Failed to export contacts:', error);
  } finally {
    exporting.value = false;
  }
};
</script>

<template>
  <div class="flex justify-between items-center mb-4">
    <PageTitle icon="lucide:user">
      Contacts
      <template #actions>
        <router-link to="/contacts/import" class="btn btn-soft me-3">
          <Icon icon="lucide:upload" />
          Import
        </router-link>
        <router-link to="/contacts/new" class="btn btn-primary">
          <Icon icon="lucide:plus" />
          Add New Contact
        </router-link>
      </template>
    </PageTitle>
  </div>

  <!-- FILTERS -->
  <div class="card bg-base-100  mb-8">
    <div class="card-body p-4">
      <div class="flex flex-wrap gap-4">
        <fieldset class="fieldset">
          <legend class="fieldset-legend">Search</legend>
          <div class="join">
            <select v-model="filters.search_by" class="select select-bordered  join-item">
              <option value="name">Name</option>
              <option value="msisdn">MSISDN</option>
            </select>
            <input v-model.lazy="filters.s" type="text" :placeholder="'Search ' + filters.search_by + '...'" class="input input-bordered  join-item" />
          </div>
        </fieldset>
        
        <fieldset class="fieldset">
          <legend class="fieldset-legend">Status</legend>
          <select v-model="filters.status" class="select select-bordered ">
            <option value="any">Any Status</option>
            <option value="unconfirmed">Unconfirmed</option>
            <option value="active">Active</option>
            <option value="blocked">Blocked</option>
            <option value="trash">Trash</option>
          </select>
        </fieldset>

        <fieldset class="fieldset">
          <legend class="fieldset-legend">Tag</legend>
          <select v-model="filters.tag" class="select select-bordered ">
            <option value="">All Tags</option>
            <option v-for="tag in tags" :key="tag.slug" :value="tag.slug">{{ tag.name }}</option>
          </select>
        </fieldset>

        <fieldset class="fieldset">
          <legend class="fieldset-legend">Country</legend>
          <div class="dropdown">
            <div tabindex="0" role="button" class="select pe-10 flex items-center gap-2 min-w-48">
              <template v-if="filters.country">
                <Icon :icon="`circle-flags:${filters.country}`" class="w-5 h-5" />
                <span>{{ countries.find(c => c.slug === filters.country)?.name }}</span>
              </template>
              <template v-else>
                All countries
              </template>
            </div>
            <ul tabindex="0" class="menu dropdown-content bg-base-100 rounded-box z-50 w-64 p-2 shadow-sm border border-base-200 block max-h-80 overflow-y-auto mt-1">
              <li>
                <a @click="filters.country = ''" :class="{ 'active': filters.country === '' }" class="flex justify-between items-center">
                  <span>- All countries -</span>
                </a>
              </li>
              <li v-for="country in countries" :key="country.slug">
                <a @click="filters.country = country.slug" :class="{ 'active': filters.country === country.slug }" class="flex justify-between items-center gap-2">
                  <div class="flex items-center gap-2">
                    <Icon :icon="`circle-flags:${country.slug}`" class="w-5 h-5" />
                    <span>{{ country.name }}</span>
                  </div>
                  <span class="text-xs opacity-50">{{ country.count }}</span>
                </a>
              </li>
            </ul>
          </div>
        </fieldset>

        <fieldset class="fieldset">
          <legend class="fieldset-legend">Columns</legend>
          <div class="dropdown">
            <div tabindex="0" role="button" class="select pe-10">
              {{ tableStore.visibleColumns.length }} out of {{ columns.length }} selected
            </div>
            <ul tabindex="0"
                class="menu dropdown-content bg-base-100 rounded-box z-50 w-52 py-5 px-3 shadow-sm border border-base-200">
              <li v-for="col in columns" :key="col.id">
                <label class="label cursor-pointer justify-start gap-3 w-full py-2">
                  <input type="checkbox" v-model="tableStore.visibleColumns" :value="col.id"
                         class="checkbox checkbox-sm"/>
                  <span class="label-text">{{ col.label }}</span>
                </label>
              </li>
            </ul>
          </div>
        </fieldset>

        <div class="flex items-end flex-1 justify-end">
          <button 
            @click="exportContacts" 
            class="btn btn-outline tooltip tooltip-left mb-1"
            :class="exporting ? 'btn-success' : 'btn-primary'"
            :data-tip="'All contacts matching the current filters will be exported.'"
            :disabled="exporting"
          >
            <Icon v-if="!exporting" icon="lucide:download" />
            <span v-if="exporting" class="loading loading-spinner"></span>
            Export current list
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="card bg-base-100 ">
    <div class="overflow-x-auto">
      <table class="table  md:table-md">
        <thead>
          <tr>
            <template v-for="col in columns" :key="col.id">
              <th v-if="tableStore.visibleColumns.includes(col.id)" :class="{ 'cursor-pointer': col.sortable }" @click="col.sortable ? toggleSort(col.sortable) : null">
                {{ col.label }}
                <span v-if="col.sortable && filters.orderby === col.sortable">{{ filters.order === 'ASC' ? '↑' : '↓' }}</span>
              </th>
            </template>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td :colspan="tableStore.visibleColumns.length + 1" class="p-0">
              <Loading />
            </td>
          </tr>
          <tr v-else-if="contacts.length === 0">
            <td :colspan="tableStore.visibleColumns.length + 1" class="text-center p-12 text-base-content/50">No contacts found.</td>
          </tr>
          <tr v-for="contact in contacts" :key="contact.id" class="hover">
            <td v-if="tableStore.visibleColumns.includes('name')">{{ contact.name }}</td>
            <td v-if="tableStore.visibleColumns.includes('flag')">
              <div v-if="contact.country" class="tooltip" :data-tip="contact.country.name">
                <Icon :icon="`circle-flags:${contact.country.slug}`" class="w-6 h-6" />
              </div>
            </td>
            <td v-if="tableStore.visibleColumns.includes('msisdn')">{{ contact.msisdn }}</td>
            <td v-if="tableStore.visibleColumns.includes('country_code')">{{ contact.country?.slug }}</td>
            <td v-if="tableStore.visibleColumns.includes('country_name')">{{ contact.country?.name }}</td>
            <td v-if="tableStore.visibleColumns.includes('tags')">
              <div class="flex flex-wrap gap-1">
                <span v-for="tag in contact.tags" :key="tag" class="badge badge-ghost ">{{ tag }}</span>
              </div>
            </td>
            <td v-if="tableStore.visibleColumns.includes('status')">
              <span class="badge " :class="{
                'badge-success': contact.status === 'active',
                'badge-warning': contact.status === 'unconfirmed',
                'badge-error': contact.status === 'blocked',
                'badge-ghost': contact.is_trash
              }">{{ contact.status }}</span>
            </td>
            <td v-if="tableStore.visibleColumns.includes('created')">{{ formatDate(contact.created) }}</td>
            <td>
              <div class="flex justify-end gap-1">
                <template v-if="!contact.is_trash">
                  <router-link :to="'/contacts/' + contact.id" class="btn btn-primary tooltip" data-tip="Edit">
                    <Icon icon="lucide:edit" />
                  </router-link>
                  <button @click="deleteContact(contact.id)" class="btn btn-error btn-outline tooltip" data-tip="Trash">
                    <Icon icon="lucide:trash" />
                  </button>
                </template>
                <template v-else>
                  <button @click="restoreContact(contact.id)" class="btn btn-success tooltip" data-tip="Restore">
                    <Icon icon="lucide:undo-2" />
                  </button>
                  <button @click="deleteContact(contact.id, true)" class="btn btn-outline btn-error tooltip" data-tip="Delete">
                    <Icon icon="lucide:shredder" />
                  </button>
                </template>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    
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
