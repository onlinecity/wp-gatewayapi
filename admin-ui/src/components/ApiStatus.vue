<script setup lang="ts">
import {useStateStore} from "../stores/state.ts";

const state = useStateStore();
</script>

<template>
  <div>
    <!-- API STATUS -->
    <div v-if="state.hasKey === false && state.isOAuthOnly === false" class="tooltip tooltip-bottom" data-tip="Go to Settings to add your API key to get started.">
      <div class="badge badge-warning gap-2">
        No API key
      </div>
    </div>
    <div v-else-if="state.isOAuthOnly === true" class="tooltip tooltip-bottom" data-tip="You are using a legacy OAuth-token, which will continue to work, but is limited to the legacy SMS API.">
      <div class="badge badge-success gap-2">
        Legacy OAuth
      </div>
    </div>
    <div v-else-if="state.hasKey === true && state.keyIsValid === true" class="badge badge-success gap-2 h-auto inline-block text-center tooltip tooltip-bottom -my-2" data-tip="Success! There is an API key and it is valid. You can see how much credit is available in the account.">
      <strong>Connected</strong><br />
      <small>{{ state.currency }} {{ state.credit?.toFixed(2) }}</small>
    </div>
    <div v-else-if="state.hasKey === true && state.keyIsValid === false" class="tooltip tooltip-bottom  -my-2" data-tip="Your API key is invalid. Please check your settings.">
      <div class="badge badge-error gap-2">
        Invalid API key
      </div>
    </div>
    <!-- API STATUS -->
  </div>
</template>
