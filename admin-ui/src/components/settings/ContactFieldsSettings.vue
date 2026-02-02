<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useParentIframeStore } from '@/stores/parentIframe.ts';
import { Icon } from '@iconify/vue';

const parentIframe = useParentIframeStore();

const fields = ref<any[]>([]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');

const fetchFields = async () => {
  loading.value = true;
  try {
    const response = await parentIframe.ajaxGet('gatewayapi_get_contact_fields', {}) as any;
    if (response && response.success) {
      fields.value = response.data.map((f: any) => ({ 
        ...f, 
        originalTitle: f.title,
        originalDescription: f.description
      }));
    }
  } catch (err) {
    console.error('Failed to fetch contact fields:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchFields);

const addField = () => {
  fields.value.push({
    title: '',
    description: '',
    meta_key: '',
    originalTitle: ''
  });
};

const deleteField = (index: number) => {
  if (confirm('Are you sure you want to delete this field?')) {
    fields.value.splice(index, 1);
  }
};

const slugify = (text: string) => {
  return text.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase();
};

const handleTitleChange = (field: any) => {
  if (!field.originalTitle) {
    let baseSlug = slugify(field.title);
    let slug = baseSlug;
    let counter = 1;

    // Ensure slug is unique within current fields
    const otherFields = fields.value.filter(f => f !== field);
    while (otherFields.some(f => f.meta_key === slug)) {
      slug = `${baseSlug}_${counter}`;
      counter++;
    }
    field.meta_key = slug;
  }
};

const isDirty = (field: any) => {
  return field.title !== field.originalTitle || field.description !== field.originalDescription;
};

const anyDirty = computed(() => {
  return fields.value.some(isDirty) || fields.value.some(f => !f.originalTitle);
});


const hasErrors = () => {
  const titles = new Set();
  const reserved = ['name', 'msisdn', 'status', 'tags', 'country_name', 'country_code'];
  for (const field of fields.value) {
    const title = field.title.trim();
    if (!title) return 'Title cannot be empty';
    if (titles.has(title)) return `Title "${title}" must be unique`;
    if (reserved.includes(title.toLowerCase())) return `Title "${title}" is a reserved column name`;
    titles.add(title);
  }
  return null;
};

const saveFields = async () => {
  const validationError = hasErrors();
  if (validationError) {
    error.value = validationError;
    return;
  }
  saving.value = true;
  error.value = '';
  success.value = '';
  try {
    const response = await parentIframe.ajaxPost('gatewayapi_save_contact_fields', {
      fields: JSON.stringify(fields.value)
    }) as any;
    if (response && response.success) {
      success.value = response.data.message || 'Fields saved successfully';
      // Update originals
      fields.value.forEach(f => {
        f.originalTitle = f.title;
        f.originalDescription = f.description;
      });
    } else {
      error.value = response?.data?.message || 'Failed to save fields';
    }
  } catch (err) {
    console.error('Failed to save contact fields:', err);
    error.value = 'An error occurred while saving.';
  } finally {
    saving.value = false;
  }
};
</script>

<template>
  <div>
    <h2 class="text-xl font-semibold mb-4">Contact Meta Fields</h2>
    <p class="mb-6">Configure custom fields for your contacts. This will make it possible for you to store custom fields which will then be merged into the final messages, making it possible to personalize the messages sent to your contacts.</p>

    <div v-if="error" class="alert alert-error mb-6">
      <Icon icon="lucide:circle-alert" />
      <span>{{ error }}</span>
    </div>
    <div v-if="success" class="alert alert-success mb-6">
      <Icon icon="lucide:circle-check-big" />
      <span>{{ success }}</span>
    </div>

    <div class="overflow-x-auto">
      <table class="table w-full">
        <thead>
          <tr>
            <th>Title</th>
            <th>Description</th>
            <th class="w-24 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(field, index) in fields" :key="index">
            <td>
              <input 
                v-model="field.title" 
                type="text" 
                placeholder="Field Title" 
                class="input input-bordered w-full"
                @input="handleTitleChange(field)"
                required
              />
            </td>
            <td>
              <input 
                v-model="field.description" 
                type="text" 
                placeholder="Help text" 
                class="input input-bordered w-full"
              />
            </td>
            <td class="text-right flex gap-1 justify-end">
              <button 
                @click="deleteField(index)" 
                class="btn btn-outline btn-error"
                title="Delete"
              >
                <Icon icon="lucide:trash-2" />
              </button>
            </td>
          </tr>
          <tr v-if="fields.length === 0">
            <td colspan="3" class="text-center py-8 opacity-50">
              No custom fields configured yet.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-6 flex justify-between items-center">
      <button @click="addField" class="btn btn-soft gap-2">
        <Icon icon="lucide:plus" />
        Add Field
      </button>

      <button 
        @click="saveFields" 
        class="btn btn-primary" 
        :disabled="saving || !anyDirty"
      >
        <span v-if="saving" class="loading loading-spinner"></span>
        <Icon v-else icon="lucide:save" class="me-2" />
        Save Changes
      </button>
    </div>
  </div>
</template>
