import {defineStore} from "pinia";
import {watch} from "vue";
import {useElementSize} from "@vueuse/core";

export const useParentIframeStore = defineStore('parentIframe', () => {
  const ajaxPost = (action: string, data: any) => {
    return new Promise((resolve, reject) => {
      window.parent.jQuery.post(
        window.parent.ajaxurl,
        {action, ...data},
        (response: any) => resolve(response)
      ).fail((error: any) => reject(error));
    });
  }

  const appElement = document.getElementById('app');
  const {height} = useElementSize(appElement);

  watch(height, (newHeight) => {
    if (!window.parent || !window.parent.jQuery) return;
    const parentElement = window.parent.jQuery('#gatewayapi-admin-ui');
    parentElement.css('height', `${newHeight + 10}px`);
  }, { immediate: true });

  return {
    ajaxPost,
    appElement
  }
});