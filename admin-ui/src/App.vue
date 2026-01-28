<script setup lang="ts">
import {useParentIframeStore} from "./stores/parentIframe.ts";
import {useStateStore} from "./stores/state.ts";

useParentIframeStore();
const state = useStateStore();
</script>

<template>
  <div class="navbar bg-base-100 shadow-sm px-4 py-3 border-b border-base-200">
    <div class="navbar-start">
      <img src="@/assets/gatewayapi-logo-lightmode.svg" class="h-8 w-auto"/>
    </div>

    <div class="navbar-center">
      <div v-if="state.hasKey === false" class="tooltip tooltip-bottom" data-tip="Go to Settings to add your API key to get started.">
        <div class="badge badge-warning gap-2">
          No API key
        </div>
      </div>
      <div v-else-if="state.hasKey === true && state.keyIsValid === true" class="badge badge-success gap-2 h-auto inline-block text-center tooltip tooltip-bottom" data-tip="Success! There is an API key and it is valid. You can see how much credit is available in the account.">
        <strong>Connected</strong><br />
        <small>{{ state.currency }} {{ state.credit?.toFixed(2) }}</small>
      </div>
      <div v-else-if="state.hasKey === true && state.keyIsValid === false" class="tooltip tooltip-bottom" data-tip="Your API key is invalid. Please check your settings.">
        <div class="badge badge-error gap-2">
          Invalid API key
        </div>
      </div>
    </div>

    <div class="navbar-end">
      <a href="https://gatewayapi.com/docs/" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm">Documentation</a>
      <a href="https://gatewayapi.com/support/" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm">Chat-support</a>
    </div>
  </div>
  <div class="container mx-auto p-6">
    <router-view />
  </div>
</template>
