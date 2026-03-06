<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useParentIframeStore } from '@/stores/parentIframe.ts';
import PageTitle from "@/components/PageTitle.vue";
import { Icon } from '@iconify/vue';
import { parsePhoneNumberFromString } from 'libphonenumber-js';
import Papa from 'papaparse';
import countryData from '@/assets/countries.json';

const parentIframe = useParentIframeStore();

const file = ref<File | null>(null);
const importing = ref(false);
const replaceExisting = ref(false);
const progress = ref(0);
const results = ref<{ success: number; failed: number }>({ success: 0, failed: 0 });
const error = ref('');
const finished = ref(false);
const allRecipientTags = ref<any[]>([]);
const selectedTags = ref<string[]>([]);

type ParsedContact = {
  name: string;
  msisdn: string;
  status: string;
  tags: string[];
  country: string;
  country_code: string;
  meta?: Record<string, string>;
  meta_titles?: Record<string, string>;
};

const onFileChange = (e: any) => {
  const selectedFile = e.target.files[0];
  if (selectedFile) {
    file.value = selectedFile;
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

onMounted(() => {
  fetchRecipientTags();
});

const addTag = () => {
  const name = window.prompt('Enter new tag name:');
  if (!name) return;

  const tag = name.trim();
  if (!tag) return;

  if (!selectedTags.value.includes(tag)) {
    selectedTags.value.push(tag);
  }
  if (!allRecipientTags.value.find(t => t.name === tag)) {
    allRecipientTags.value.push({ name: tag, count: 0 });
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

const RESERVED_META_KEYS = new Set([
  'name',
  'msisdn',
  'status',
  'tags',
  'country',
  'country_code',
  'mobile_country_code',
  'mobile_number'
]);

const normalizeHeader = (value: string) => {
  return value
    .replace(/\uFEFF/g, '')
    .trim()
    .replace(/^"|"$/g, '')
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .toLowerCase();
};

const buildMetaKey = (title: string) => {
  return title
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
};

const parseCSV = (text: string) => {
  const parsed = Papa.parse<string[]>(text, {
    delimiter: '',
    skipEmptyLines: 'greedy'
  });

  const rows = parsed.data
    .filter((row): row is string[] => Array.isArray(row))
    .map(row => row.map(value => (value || '').toString().trim()))
    .filter(row => row.some(value => value !== ''));

  if (rows.length < 2) return [];

  const headers = rows[0] || [];
  if (headers.length === 0) return [];

  const usedMetaKeys = new Set<string>();
  const nextMetaKey = (title: string) => {
    const baseKey = buildMetaKey(title) || 'field';
    let metaKey = baseKey;
    let suffix = 1;

    while (usedMetaKeys.has(metaKey) || RESERVED_META_KEYS.has(metaKey)) {
      metaKey = `${baseKey}-${suffix}`;
      suffix++;
    }

    usedMetaKeys.add(metaKey);
    return metaKey;
  };

  const headerDefinitions = headers.map((rawHeader) => {
    const title = rawHeader.trim().replace(/^"|"$/g, '');
    const normalized = normalizeHeader(rawHeader);
    if (normalized === 'name') return { type: 'name' as const, title };
    if (normalized === 'msisdn') return { type: 'msisdn' as const, title };
    if (normalized === 'tags' || normalized === 'tag') return { type: 'tags' as const, title };
    if (normalized === 'status') return { type: 'status' as const, title };
    if (normalized === 'mobile country code' || normalized === 'country code' || normalized === 'phone country code') {
      return { type: 'mobile_country_code' as const, title };
    }
    if (normalized === 'mobile number' || normalized === 'phone number' || normalized === 'mobile' || normalized === 'number') {
      return { type: 'mobile_number' as const, title };
    }
    return { type: 'meta' as const, title, metaKey: nextMetaKey(title || 'Field') };
  });

  const contacts: ParsedContact[] = [];

  for (let i = 1; i < rows.length; i++) {
    const values = rows[i] || [];
    let name = '-';
    let status = 'active';
    let msisdnSource = '';
    let mobileCountryCode = '';
    let mobileNumber = '';
    let tags: string[] = [];
    const meta: Record<string, string> = {};
    const metaTitles: Record<string, string> = {};

    headerDefinitions.forEach((header, index) => {
      const value = (values[index] || '').trim();
      if (!value) return;

      if (header.type === 'name') {
        name = value;
        return;
      }
      if (header.type === 'msisdn') {
        msisdnSource = value;
        return;
      }
      if (header.type === 'tags') {
        tags = value.split(',').map(tag => tag.trim()).filter(Boolean);
        return;
      }
      if (header.type === 'status') {
        status = value || 'active';
        return;
      }
      if (header.type === 'mobile_country_code') {
        mobileCountryCode = value;
        return;
      }
      if (header.type === 'mobile_number') {
        mobileNumber = value;
        return;
      }
      if (header.type === 'meta' && header.metaKey) {
        meta[header.metaKey] = value;
        metaTitles[header.metaKey] = header.title || header.metaKey;
      }
    });

    if (!msisdnSource) {
      msisdnSource = mobileCountryCode || mobileNumber
        ? `${mobileCountryCode}${mobileNumber}`
        : mobileNumber;
    }

    const msisdn = msisdnSource.replace(/\D/g, '');
    if (!msisdn) continue;

    const countryInfo = getCountryInfo(msisdn);
    if (!countryInfo) continue;

    const contact: ParsedContact = {
      name: name || '-',
      msisdn,
      status: status || 'active',
      tags,
      country: countryInfo.name,
      country_code: countryInfo.code
    };

    if (Object.keys(meta).length > 0) {
      contact.meta = meta;
      contact.meta_titles = metaTitles;
    }

    contacts.push(contact);
  }

  return contacts;
};

const startImport = async () => {
  if (!file.value) return;
  if (!selectedTags.value.length) {
    error.value = 'At least one tag is required for import.';
    return;
  }

  importing.value = true;
  progress.value = 0;
  results.value = { success: 0, failed: 0 };
  error.value = '';
  finished.value = false;

  try {
    const text = await file.value.text();
    const contacts = parseCSV(text);

    if (contacts.length === 0) {
      error.value = 'No valid contacts found in CSV. Make sure it has an MSISDN column, or Mobile country code + Mobile number columns.';
      importing.value = false;
      return;
    }

    const contactsToImport = contacts.map(contact => ({
      ...contact,
      tags: Array.from(new Set([...(contact.tags || []), ...selectedTags.value]))
    }));

    const batchSize = 100;
    for (let i = 0; i < contactsToImport.length; i += batchSize) {
      const batch = contactsToImport.slice(i, i + batchSize);
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

      progress.value = Math.round(((i + batch.length) / contactsToImport.length) * 100);
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
          <p class="mb-4">Upload a CSV file to import contacts. Delimiter is detected automatically (semicolon, comma, tab, or pipe).</p>
          <div class="bg-base-200 p-4 rounded-lg mb-6">
            <h4 class="font-bold mb-2">Expected Columns:</h4>
            <ul class="list-disc list-inside text-sm">
              <li><strong>MSISDN</strong> (Required unless you provide mobile country code + mobile number)</li>
              <li><strong>Mobile country code</strong> + <strong>Mobile number</strong> (Supported old format)</li>
              <li><strong>Name</strong> (Optional, defaults to '-')</li>
              <li><strong>Tags</strong> (Optional, comma separated)</li>
              <li><strong>Status</strong> (Optional, defaults to 'active')</li>
              <li>Any other columns are imported as custom contact meta fields</li>
            </ul>
          </div>

          <fieldset class="fieldset text-base mb-6">
            <legend class="fieldset-legend">Select CSV File</legend>
            <input type="file" accept=".csv" class="file-input file-input-bordered w-full" @change="onFileChange" />
          </fieldset>

          <fieldset class="fieldset text-base mb-6">
            <legend class="fieldset-legend">Tags (Required)</legend>
            <div class="flex gap-3 relative">
              <div class="dropdown w-full static">
                <div tabindex="0" role="button" class="select select-bordered w-full flex items-center justify-between mb-1">
                  <span>{{ selectedTags.length }} tags selected</span>
                </div>
                <ul tabindex="0" class="menu dropdown-content bg-base-100 rounded-box z-50 w-full p-2 shadow-lg max-h-64 overflow-y-auto border border-base-200">
                  <li v-if="allRecipientTags.length === 0" class="p-4 text-center text-sm opacity-50">
                    No contacts found or no contacts are associated with a tag.
                  </li>
                  <li v-for="tag in allRecipientTags" :key="tag.name">
                    <label class="label cursor-pointer justify-start gap-3 w-full py-2">
                      <input type="checkbox" v-model="selectedTags" :value="tag.name" class="checkbox checkbox-sm" />
                      <span class="label-text flex-grow">{{ tag.name }}</span>
                      <span class="badge badge-sm badge-ghost opacity-50">{{ tag.count }}</span>
                    </label>
                  </li>
                </ul>
              </div>
              <button type="button" @click="addTag" class="btn btn-outline btn-primary tooltip" data-tip="Add new tag">
                <Icon icon="lucide:plus" />
              </button>
            </div>
          </fieldset>

          <fieldset class="fieldset text-base mb-6">
            <label class="label cursor-pointer justify-start gap-3">
              <input type="checkbox" v-model="replaceExisting" class="checkbox" />
              <span class="label-text">Replace existing contacts (overwrite if MSISDN already exists)</span>
            </label>
          </fieldset>

          <div class="card-actions justify-end">
            <button class="btn btn-primary" :disabled="!file || selectedTags.length === 0" @click="startImport">
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
