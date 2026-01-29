<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useParentIframeStore } from '@/stores/parentIframe.ts';
import { useStateStore } from '@/stores/state.ts';
import PageTitle from "@/components/PageTitle.vue";
import Loading from "@/components/Loading.vue";

const parentIframe = useParentIframeStore();
const state = useStateStore();

const contactCount = ref<number | null>(null);
const campaignCount = ref<number | null>(null);
const loading = ref(true);

onMounted(async () => {
  try {
    // We already have key status from state store (it auto-reloads on init)
    // but let's ensure it's loaded if not already.
    if (state.hasKey === null) {
      await state.reloadKeyStatus();
    }

    // Fetch contacts count
    const contactsResponse = await parentIframe.ajaxGet('gatewayapi_get_contacts', { paged: 1 }) as any;
    if (contactsResponse && contactsResponse.success) {
      contactCount.value = contactsResponse.data.pagination.total;
    }

    // Fetch campaigns count
    const campaignsResponse = await parentIframe.ajaxGet('gatewayapi_get_campaigns', { paged: 1 }) as any;
    if (campaignsResponse && campaignsResponse.success) {
      campaignCount.value = campaignsResponse.data.pagination.total;
    }
  } catch (error) {
    console.error('Failed to fetch getting started data:', error);
  } finally {
    loading.value = false;
  }
});

const steps = computed(() => [
  {
    title: 'Set up API key',
    description: "Go to 'Settings' in the left menu to add your API key. You cannot use the plugin without a valid API key.",
    done: !!state.hasKey && !!state.keyIsValid,
    link: 'admin.php?page=gatewayapi-settings#/settings'
  },
  {
    title: 'Create a contact',
    description: 'Contacts are the basis of using this plugin for sending SMS campaigns.',
    done: (contactCount.value ?? 0) > 0,
    link: 'admin.php?page=gatewayapi-contacts#/contacts/new'
  },
  {
    title: 'Create a campaign',
    description: "Don't let the name trick you - campaigns lets you send messages to groups of people of any size.",
    done: (campaignCount.value ?? 0) > 0,
    link: 'admin.php?page=gatewayapi-campaigns#/campaigns/new'
  }
]);

const navigateTo = (link: string) => {
  if (window.parent) {
    window.parent.location.href = link;
  } else {
    window.location.href = link;
  }
};
</script>

<template>
  <PageTitle icon="lucide:list-todo">Getting started</PageTitle>

  <Loading v-if="loading" />

  <div v-else class="max-w-2xl mx-auto mt-10">
    <ul class="timeline timeline-vertical">
      <li v-for="(step, index) in steps" :key="index">
        <hr v-if="index !== 0" :class="{ 'bg-success': steps[index-1].done && step.done }" />
        <a href="#" @click.prevent="navigateTo(step.link)" :class="[index % 2 === 0 ? 'timeline-start' : 'timeline-end', 'timeline-box hover:bg-base-200 transition-colors', step.done ? 'line-through text-success border-success' : '']">
          <div class="font-bold">{{ step.title }}</div>
          <div class="text-sm opacity-80">{{ step.description }}</div>
        </a>
        <div class="timeline-middle">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
            :class="['h-5 w-5', step.done ? 'text-success' : 'opacity-30']"
          >
            <path
              fill-rule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
              clip-rule="evenodd"
            />
          </svg>
        </div>
        <hr v-if="index !== steps.length - 1" :class="{ 'bg-success': step.done && steps[index+1].done }" />
      </li>
    </ul>
  </div>
</template>
