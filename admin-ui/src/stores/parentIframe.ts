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

  const resizeBasedOnScrollHeight = () => {
    if (appElement && appElement?.scrollHeight !== scrollHeight.value) {
      handlerNewHeight(appElement.scrollHeight);
    }
  };
  setInterval(resizeBasedOnScrollHeight, 100)


  const navigateTo = (link: string) => {
    const parent = getParent();
    if (!parent) {
      window.location.href = link;
      return;
    }

    // If it's already an absolute URL, just use it
    if (link.startsWith('https:') || link.startsWith('http:') || link.startsWith('//')) {
      parent.location.href = link;
      return;
    }

    // Ensure it's relative to the parent's base path
    // We can use the parent's location to construct the full URL
    const baseUrl = parent.location.origin + parent.location.pathname;
    const directory = baseUrl.substring(0, baseUrl.lastIndexOf('/') + 1);
    parent.location.href = directory + link;
  }

  return {
    ajaxPost,
    ajaxGet,
    navigateTo,
    appElement
  }
});