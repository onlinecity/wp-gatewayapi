<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Icon } from '@iconify/vue';

interface Country {
  slug: string;
  name: string;
  count?: number;
}

const props = withDefaults(defineProps<{
  countries: Country[];
  multiple?: boolean;
  placeholder?: string;
  allLabel?: string;
  showCount?: boolean;
}>(), {
  multiple: false,
  placeholder: 'Search countries...',
  allLabel: 'All countries',
  showCount: false
});

// For single select mode
const modelValue = defineModel<string>({ default: '' });

// For multi-select mode
const modelValues = defineModel<string[]>('values', { default: () => [] });

const searchQuery = ref('');
const isOpen = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);

const filteredCountries = computed(() => {
  if (!searchQuery.value) {
    return props.countries;
  }
  const query = searchQuery.value.toLowerCase();
  return props.countries.filter(country => 
    country.name.toLowerCase().includes(query) ||
    country.slug.toLowerCase().includes(query)
  );
});

const displayText = computed(() => {
  if (props.multiple) {
    if (modelValues.value.length === 0) {
      return props.allLabel;
    }
    return `${modelValues.value.length} selected`;
  } else {
    if (!modelValue.value) {
      return props.allLabel;
    }
    const country = props.countries.find(c => c.slug === modelValue.value);
    return country?.name || props.allLabel;
  }
});

const selectedCountry = computed(() => {
  if (props.multiple || !modelValue.value) return null;
  return props.countries.find(c => c.slug === modelValue.value);
});

const openDropdown = () => {
  isOpen.value = true;
  searchQuery.value = '';
  // Focus the input after dropdown opens
  setTimeout(() => {
    inputRef.value?.focus();
  }, 10);
};

const closeDropdown = () => {
  isOpen.value = false;
  searchQuery.value = '';
};

const toggleDropdown = () => {
  if (isOpen.value) {
    closeDropdown();
  } else {
    openDropdown();
  }
};

const selectCountry = (country: Country | null) => {
  if (props.multiple) {
    if (country === null) {
      modelValues.value = [];
    } else {
      const index = modelValues.value.indexOf(country.slug);
      if (index === -1) {
        modelValues.value = [...modelValues.value, country.slug];
      } else {
        modelValues.value = modelValues.value.filter(s => s !== country.slug);
      }
    }
  } else {
    modelValue.value = country?.slug || '';
    closeDropdown();
  }
};

const isSelected = (slug: string) => {
  if (props.multiple) {
    return modelValues.value.includes(slug);
  }
  return modelValue.value === slug;
};

// Close dropdown when clicking outside
const handleClickOutside = (event: MouseEvent) => {
  const target = event.target as HTMLElement;
  if (!target.closest('.country-dropdown')) {
    closeDropdown();
  }
};

watch(isOpen, (newValue) => {
  if (newValue) {
    document.addEventListener('click', handleClickOutside);
  } else {
    document.removeEventListener('click', handleClickOutside);
  }
});
</script>

<template>
  <div class="dropdown country-dropdown w-full" :class="{ 'dropdown-open': isOpen }">
    <!-- Dropdown trigger / Search input -->
    <div 
      class="select pe-10 flex items-center gap-2 w-full cursor-pointer"
      :class="{ 'min-w-48': !multiple }"
      @click.stop="toggleDropdown"
    >
      <template v-if="isOpen">
        <input
          ref="inputRef"
          v-model="searchQuery"
          type="text"
          :placeholder="placeholder"
          class="bg-transparent border-none outline-none w-full text-sm"
          @click.stop
        />
      </template>
      <template v-else>
        <template v-if="!multiple && selectedCountry">
          <Icon :icon="`circle-flags:${selectedCountry.slug.toLowerCase()}`" class="w-5 h-5 flex-shrink-0" />
          <span class="truncate">{{ selectedCountry.name }}</span>
        </template>
        <template v-else>
          <span class="truncate">{{ displayText }}</span>
        </template>
      </template>
    </div>

    <!-- Dropdown content -->
    <ul 
      v-show="isOpen"
      class="menu dropdown-content bg-base-100 rounded-box z-50 w-full p-2 shadow-sm border border-base-200 block max-h-80 overflow-y-auto mt-1"
    >
      <!-- All countries option (for single select) or clear all (for multi) -->
      <li v-if="!multiple || modelValues.length > 0">
        <a 
          @click.stop="selectCountry(null)" 
          :class="{ 'active': !multiple && !modelValue }"
          class="flex justify-between items-center"
        >
          <span>{{ multiple ? '- Clear selection -' : (modelValue ? `- ${allLabel} -` : allLabel) }}</span>
        </a>
      </li>

      <!-- Country list -->
      <li v-for="country in filteredCountries" :key="country.slug">
        <template v-if="multiple">
          <label 
            class="label cursor-pointer justify-start gap-3 w-full py-2"
            @click.stop
          >
            <input 
              type="checkbox" 
              :checked="isSelected(country.slug)"
              @change="selectCountry(country)"
              class="checkbox checkbox-sm"
            />
            <Icon :icon="`circle-flags:${country.slug.toLowerCase()}`" class="w-5 h-5 flex-shrink-0" />
            <span class="label-text flex-grow truncate w-full">{{ country.name }}</span>
            <span v-if="showCount && country.count !== undefined" class="text-xs opacity-50">{{ country.count }}</span>
          </label>
        </template>
        <template v-else>
          <a 
            @click.stop="selectCountry(country)" 
            :class="{ 'active': isSelected(country.slug) }"
            class="flex justify-between items-center gap-2"
          >
            <div class="flex items-center gap-2">
              <Icon :icon="`circle-flags:${country.slug.toLowerCase()}`" class="w-5 h-5 flex-shrink-0" />
              <span>{{ country.name }}</span>
            </div>
            <span v-if="showCount && country.count !== undefined" class="text-xs opacity-50">{{ country.count }}</span>
          </a>
        </template>
      </li>

      <!-- No results message -->
      <li v-if="filteredCountries.length === 0" class="p-4 text-center text-sm opacity-50">
        No countries found matching "{{ searchQuery }}"
      </li>
    </ul>
  </div>
</template>
