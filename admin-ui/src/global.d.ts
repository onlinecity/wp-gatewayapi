export {};

declare global {
  interface Window {
    jQuery: any;
    ajaxurl: string;
    parent: Window;
    opener: Window | null;
  }
}
