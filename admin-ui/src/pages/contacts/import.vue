<script setup lang="ts">
import { ref } from 'vue';
import { useParentIframeStore } from '@/stores/parentIframe.ts';
import PageTitle from "@/components/PageTitle.vue";
import { Icon } from '@iconify/vue';
import { parsePhoneNumberFromString } from 'libphonenumber-js';
import countryData from '@/assets/countries.json';

const parentIframe = useParentIframeStore();

const file = ref<File | null>(null);
const importing = ref(false);
const replaceExisting = ref(false);
const progress = ref(0);
const results = ref<{ success: number; failed: number }>({ success: 0, failed: 0 });
const error = ref('');
const finished = ref(false);

const onFileChange = (e: any) => {
  const selectedFile = e.target.files[0];
  if (selectedFile) {
    file.value = selectedFile;
  }
};

const getCountryInfo = (msisdn: string) => {
  if (!msisdn) return null;
  let formattedMsisdn = msisdn.trim();
  if (!formattedMsisdn.startsWith('+')) {
    formattedMsisdn = '+' + formattedMsisdn;
  }
  try {
    const phoneNumber = parsePhoneNumberFromString(formattedMsisdn);
    if (phoneNumber && phoneNumber.country) {
      const countryCode = phoneNumber.country;
      const countryInfo = (countryData.countries as any)[countryCode];
      return countryInfo ? { name: countryInfo.name, code: countryCode.toLowerCase() } : null;
    }
  } catch (e) {
    // Ignore
  }
  return null;
};

const parseCSV = (text: string) => {
  const lines = text.split(/\r?\n/).filter(line => line.trim());
  if (lines.length === 0) return [];

  const firstLine = lines[0];
  if (!firstLine) return [];

  const headers = firstLine.split(';').map(h => h.trim().replace(/^"|"$/g, '').toLowerCase());
  const contacts: any[] = [];

  for (let i = 1; i < lines.length; i++) {
    const line = lines[i]?.trim();
    if (!line) continue;

    const values = line.split(';').map(v => v.trim().replace(/^"|"$/g, '').replace(/""/g, '"'));
    const contact: any = {};
    
    headers.forEach((header, index) => {
      const value = values[index];
      if (header === 'name') contact.name = value || '-';
      else if (header === 'msisdn') contact.msisdn = (value || '');
      else if (header === 'tags') contact.tags = value ? value.split(',').map((t: string) => t.trim()) : [];
      else if (header === 'status') contact.status = value || 'active';
      else {
        // Assume it's a custom field
        contact[header] = value || '';
      }
    });

    if (contact.msisdn) {
      const countryInfo = getCountryInfo(contact.msisdn);
      contact.msisdn = contact.msisdn.replace(/\D/g, '');
      if (!countryInfo) {
        continue; // Skip if country cannot be resolved
      }
      contact.country = countryInfo.name;
      contact.country_code = countryInfo.code;

      if (!contact.name) contact.name = '-';
      if (!contact.status) contact.status = 'active';
      contacts.push(contact);
    }
  }

  return contacts;
};

const startImport = async () => {
  if (!file.value) return;

  importing.value = true;
  progress.value = 0;
  results.value = { success: 0, failed: 0 };
  error.value = '';
  finished.value = false;

  try {
    const text = await file.value.text();
    const contacts = parseCSV(text);

    if (contacts.length === 0) {
      error.value = 'No valid contacts found in CSV. Make sure it has an MSISDN column and uses semicolon (;) as delimiter.';
      importing.value = false;
      return;
    }

    const batchSize = 100;
    for (let i = 0; i < contacts.length; i += batchSize) {
      const batch = contacts.slice(i, i + batchSize);
      const response = await parentIframe.ajaxPost('gatewayapi_bulk_save_contacts', { 
        contacts: batch,
        replace_existing: replaceExisting.value
      }) as any;
      
      if (response && response.success) {
        response.data.results.forEach((res: any) => {
          if (res.success) results.value.success++;
          else results.value.failed++;
        });
      } else {
        results.value.failed += batch.length;
      }

      progress.value = Math.round(((i + batch.length) / contacts.length) * 100);
    }
    finished.value = true;
  } catch (err) {
    console.error('Import failed:', err);
    error.value = 'An error occurred during import.';
  } finally {
    importing.value = false;
  }
};
</script>

<template>
  <PageTitle icon="lucide:upload">
    Import Contacts
    <template #actions>
      <router-link to="/contacts" class="btn btn-soft gap-2">
        <Icon icon="lucide:arrow-left" />
        Back to Contacts
      </router-link>
    </template>
  </PageTitle>

  <div class="max-w-2xl mx-auto">
    <div class="card bg-base-100 border-base-300 border-2">
      <div class="card-body">
        <div v-if="error" class="alert alert-error mb-4">
          <Icon icon="lucide:circle-alert" />
          <span>{{ error }}</span>
        </div>
        <div v-if="!importing && !finished">
          <p class="mb-4">Upload a CSV file to import contacts. The CSV should use semicolon (;) as delimiter.</p>
          <div class="bg-base-200 p-4 rounded-lg mb-6">
            <h4 class="font-bold mb-2">Expected Columns:</h4>
            <ul class="list-disc list-inside text-sm">
              <li><strong>MSISDN</strong> (Required) - e.g. 4512345678</li>
              <li><strong>Name</strong> (Optional, defaults to '-')</li>
              <li><strong>Tags</strong> (Optional, comma separated)</li>
              <li><strong>Status</strong> (Optional, defaults to 'active')</li>
            </ul>
          </div>

          <fieldset class="fieldset text-base mb-6">
            <legend class="fieldset-legend">Select CSV File</legend>
            <input type="file" accept=".csv" class="file-input file-input-bordered w-full" @change="onFileChange" />
          </fieldset>

          <fieldset class="fieldset text-base mb-6">
            <label class="label cursor-pointer justify-start gap-3">
              <input type="checkbox" v-model="replaceExisting" class="checkbox" />
              <span class="label-text">Replace existing contacts (overwrite if MSISDN already exists)</span>
            </label>
          </fieldset>

          <div class="card-actions justify-end">
            <button class="btn btn-primary" :disabled="!file" @click="startImport">
              <Icon icon="lucide:upload" class="me-2" />
              Start Import
            </button>
          </div>
        </div>

        <div v-if="importing">
          <h3 class="font-bold text-lg mb-4 text-center">Importing Contacts...</h3>
          <progress class="progress progress-primary w-full h-4 mb-2" :value="progress" max="100"></progress>
          <p class="text-center">{{ progress }}% complete</p>
          <div class="flex justify-around mt-6">
            <div class="text-center">
              <div class="text-2xl font-bold text-success">{{ results.success }}</div>
              <div class="text-xs opacity-50 uppercase font-bold">Success</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold text-error">{{ results.failed }}</div>
              <div class="text-xs opacity-50 uppercase font-bold">Failed</div>
            </div>
          </div>
        </div>

        <div v-if="finished">
          <div class="alert alert-success mb-6">
            <Icon icon="lucide:circle-check-big" />
            <span>Import finished!</span>
          </div>
          
          <div class="grid grid-cols-2 gap-4 mb-8">
            <div class="stat bg-base-200 rounded-box place-items-center">
              <div class="stat-title text-success">Imported</div>
              <div class="stat-value text-success">{{ results.success }}</div>
            </div>
            <div class="stat bg-base-200 rounded-box place-items-center">
              <div class="stat-title text-error">Failed</div>
              <div class="stat-value text-error">{{ results.failed }}</div>
            </div>
          </div>

          <div class="card-actions justify-center">
            <router-link to="/contacts" class="btn btn-primary">
              Return to Contacts
            </router-link>
            <button class="btn btn-ghost" @click="finished = false; file = null">
              Import more
            </button>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>
