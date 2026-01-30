<script setup lang="ts">
import {onMounted} from "vue";
import {useParentIframeStore} from "./stores/parentIframe.ts";
import ApiStatus from "./components/ApiStatus.vue";

useParentIframeStore();

onMounted(() => {
  if (window.opener) {
    document.body.style.backgroundColor = '#eeeeef';
  }
});
</script>

<template>
  <div class="navbar bg-base-100  px-4 py-3 border-b border-base-200">
    <div class="navbar-start">
      <img src="@/assets/gatewayapi-logo-lightmode.svg" class="h-8 w-auto"/>
    </div>

    <div class="navbar-center">
      <ApiStatus />
    </div>

    <div class="navbar-end">
      <a href="https://gatewayapi.com/docs/" target="_blank" rel="noopener noreferrer" class="btn btn-ghost">Documentation</a>
      <a href="https://gatewayapi.com/support/" target="_blank" rel="noopener noreferrer" class="btn btn-ghost">Chat-support</a>
    </div>
  </div>

  <div class="container mx-auto p-6">
    <router-view />
  </div>
</template>
