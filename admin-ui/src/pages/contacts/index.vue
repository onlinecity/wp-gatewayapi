<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useParentIframeStore } from '@/stores/parentIframe.ts';
import PageTitle from "@/components/PageTitle.vue";
import Loading from "@/components/Loading.vue";

const parentIframe = useParentIframeStore();

const contacts = ref<any[]>([]);
const loading = ref(true);
const pagination = ref({ total: 0, pages: 1, current: 1 });
const filters = ref({
  s: '',
  search_by: 'name',
  status: 'any',
  tag: '',
  orderby: 'date',
  order: 'DESC'
});

const tags = ref<any[]>([]);

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

onMounted(() => {
  fetchContacts();
  fetchTags();
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
</script>

<template>
  <div class="flex justify-between items-center mb-4">
    <PageTitle>
      Contacts
      <template #actions>
        <router-link to="/contacts/new" class="btn btn-primary">Add New Contact</router-link>
      </template>
    </PageTitle>
  </div>

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
      </div>
    </div>
  </div>

  <div class="card bg-base-100 ">
    <div class="overflow-x-auto">
      <table class="table  md:table-md">
        <thead>
          <tr>
            <th class="cursor-pointer" @click="toggleSort('name')">
              Name <span v-if="filters.orderby === 'name'">{{ filters.order === 'ASC' ? '↑' : '↓' }}</span>
            </th>
            <th class="cursor-pointer" @click="toggleSort('msisdn')">
              MSISDN <span v-if="filters.orderby === 'msisdn'">{{ filters.order === 'ASC' ? '↑' : '↓' }}</span>
            </th>
            <th>Tags</th>
            <th class="cursor-pointer" @click="toggleSort('status')">
              Status <span v-if="filters.orderby === 'status'">{{ filters.order === 'ASC' ? '↑' : '↓' }}</span>
            </th>
            <th class="cursor-pointer" @click="toggleSort('date')">
              Created <span v-if="filters.orderby === 'date'">{{ filters.order === 'ASC' ? '↑' : '↓' }}</span>
            </th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="p-0">
              <Loading />
            </td>
          </tr>
          <tr v-else-if="contacts.length === 0">
            <td colspan="6" class="text-center p-12 text-base-content/50">No contacts found.</td>
          </tr>
          <tr v-for="contact in contacts" :key="contact.id" class="hover">
            <td>{{ contact.name }}</td>
            <td>{{ contact.msisdn }}</td>
            <td>
              <div class="flex flex-wrap gap-1">
                <span v-for="tag in contact.tags" :key="tag" class="badge badge-ghost ">{{ tag }}</span>
              </div>
            </td>
            <td>
              <span class="badge " :class="{
                'badge-success': contact.status === 'active',
                'badge-warning': contact.status === 'unconfirmed',
                'badge-error': contact.status === 'blocked',
                'badge-ghost': contact.is_trash
              }">{{ contact.status }}</span>
            </td>
            <td>{{ formatDate(contact.created) }}</td>
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
