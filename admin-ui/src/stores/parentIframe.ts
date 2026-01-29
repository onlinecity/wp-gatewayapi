import {defineStore} from "pinia";
import {ref, watch} from "vue";
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

  const ajaxGet = (action: string, data: any) => {
    return new Promise((resolve, reject) => {
      window.parent.jQuery.get(
        window.parent.ajaxurl,
        {action, ...data},
        (response: any) => resolve(response)
      ).fail((error: any) => reject(error));
    });
  }

  const appElement = document.getElementById('app');
  const {height} = useElementSize(appElement);
  const scrollHeight = ref(appElement?.scrollHeight || 0);

  const handlerNewHeight = (newHeight: number) => {
    if (!window.parent || !window.parent.jQuery) return;
    const parentElement = window.parent.jQuery('#gatewayapi-admin-ui');
    parentElement.css('height', `${newHeight + 10}px`);
  }
  watch(height, handlerNewHeight, {immediate: true});

  let resizeObserver: ResizeObserver | null = null;
  resizeObserver = new ResizeObserver(() => {
    if (appElement && appElement?.scrollHeight !== scrollHeight.value) {
      scrollHeight.value = appElement.scrollHeight;
    }
  });
  if (appElement) resizeObserver.observe(appElement);

  watch(scrollHeight, handlerNewHeight, {immediate: true});


  return {
    ajaxPost,
    ajaxGet,
    appElement
  }
});