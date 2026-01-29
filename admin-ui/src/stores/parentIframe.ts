import {defineStore} from "pinia";
import {ref, watch} from "vue";
import {useElementSize} from "@vueuse/core";

export const useParentIframeStore = defineStore('parentIframe', () => {
  const getParent = () => {
    return window.opener || window.parent;
  }

  const ajaxPost = (action: string, data: any) => {
    return new Promise((resolve, reject) => {
      const parent = getParent();
      parent.jQuery.post(
        parent.ajaxurl,
        {action, ...data},
        (response: any) => resolve(response)
      ).fail((error: any) => reject(error));
    });
  }

  const ajaxGet = (action: string, data: any) => {
    return new Promise((resolve, reject) => {
      const parent = getParent();
      parent.jQuery.get(
        parent.ajaxurl,
        {action, ...data},
        (response: any) => resolve(response)
      ).fail((error: any) => reject(error));
    });
  }

  const appElement = document.getElementById('app');
  const {height} = useElementSize(appElement);
  const scrollHeight = ref(appElement?.scrollHeight || 0);

  const handlerNewHeight = (newHeight: number) => {
    const parent = getParent();
    if (!parent || !parent.jQuery) return;
    const parentElement = parent.jQuery('#gatewayapi-admin-ui');
    if (parentElement.length) {
      parentElement.css('height', `${newHeight + 10}px`);
    }
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