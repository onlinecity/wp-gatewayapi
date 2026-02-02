<script setup lang="ts">
import { computed, ref } from 'vue';
import { Icon } from "@iconify/vue";

interface SmsTag {
  tag: string;
  label: string;
  category?: string;
}

const props = defineProps<{
  modelValue: string;
  disabled?: boolean;
  tags?: SmsTag[];
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
}>();

const groupedTags = computed(() => {
  if (!props.tags) return {};
  return props.tags.reduce((acc, tag) => {
    const category = tag.category || 'Tags';
    if (!acc[category]) acc[category] = [];
    acc[category].push(tag);
    return acc;
  }, {} as Record<string, SmsTag[]>);
});

const messageTextarea = ref<HTMLTextAreaElement | null>(null);

// SMS Calculation Logic
const GSM_CHARS_ONE = ' !"#$%&\'()*+,-./0123456789:;<=>?@abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ£¥§¿_\n\rΔΦΓΛΩΠΨΣΘΞèéùìòÇØøÅåÆæßÉÄÖÑÜäöñüàäöñüà';
const GSM_CHARS_TWO = '^{}[]~|€';

const decodeUcs2 = (str: string) => {
  const result = [];
  for (let i = 0; i < str.length; i++) {
    const value = str.charCodeAt(i);
    if (value >= 0xD800 && value <= 0xDBFF && i + 1 < str.length) {
      const extra = str.charCodeAt(i + 1);
      if ((extra & 0xFC00) === 0xDC00) {
        result.push(((value & 0x3FF) << 10) + (extra & 0x3FF) + 0x10000);
        i++;
        continue;
      }
    }
    result.push(value);
  }
  return result;
};

const failedGSM0338Chars = (message: string) => {
  const lookup = (GSM_CHARS_ONE + GSM_CHARS_TWO).split('');
  const chars = decodeUcs2(message);
  const failed = [];
  for (const code of chars) {
    const char = String.fromCodePoint(code);
    if (!lookup.includes(char)) {
      failed.push(char);
    }
  }
  return [...new Set(failed)];
};

const smsStats = computed(() => {
  const message = props.modelValue || '';
  const failed = failedGSM0338Chars(message);
  const isUcs2 = failed.length > 0;
  const chars = decodeUcs2(message);

  if (isUcs2) {
    const len = chars.length;
    return {
      isUcs2: true,
      characters: len,
      messages: len > 70 ? Math.ceil(len / 67) : 1,
      failedChars: failed
    };
  } else {
    const lookup2 = GSM_CHARS_TWO.split('');
    let count = 0;
    for (const code of chars) {
      const char = String.fromCodePoint(code);
      count++;
      if (lookup2.includes(char)) count++;
    }
    return {
      isUcs2: false,
      characters: count,
      messages: count > 160 ? Math.ceil(count / 153) : 1,
      failedChars: []
    };
  }
});

const insertTag = (tag: string) => {
  if (!messageTextarea.value) return;

  const textarea = messageTextarea.value;
  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;
  const text = props.modelValue || '';
  const before = text.substring(0, start);
  const after = text.substring(end);

  const newValue = before + tag + after;
  emit('update:modelValue', newValue);

  // Set focus and cursor position after insertion
  setTimeout(() => {
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + tag.length;
  }, 0);
};

const onInput = (event: Event) => {
  const target = event.target as HTMLTextAreaElement;
  emit('update:modelValue', target.value);
};
</script>

<template>
  <div class="card bg-base-100 border-base-300 border-2 h-full">
    <div class="card-body">
      <div class="flex justify-between items-center mb-2">
        <h2 class="card-title text-sm uppercase opacity-50">Message</h2>

        <div v-if="!disabled" class="dropdown dropdown-end">
          <div tabindex="0" role="button" class="select gap-1">
            <Icon icon="lucide:tag" class="w-3 h-3" />
            Insert tag
          </div>
          <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-52 z-[1] border border-base-200">
            <template v-for="(categoryTags, category) in groupedTags" :key="category">
              <li class="menu-title">{{ category }}</li>
              <li v-for="tag in categoryTags" :key="tag.tag">
                <a @click.prevent="insertTag(tag.tag)">{{ tag.label }}</a>
              </li>
            </template>
          </ul>
        </div>
      </div>

      <textarea
        ref="messageTextarea"
        :value="modelValue"
        @input="onInput"
        class="textarea textarea-bordered h-64 w-full font-mono"
        placeholder="Type your message here..."
        :disabled="disabled"
      ></textarea>

      <div class="mt-4 space-y-4">
        <!-- Calculations -->
        <div class="flex flex-wrap gap-4 items-center">
          <div class="stats bg-base-200">
            <div class="stat py-2 px-4">
              <div class="stat-title text-xs">Characters</div>
              <div class="stat-value text-lg">{{ smsStats.characters }}</div>
            </div>
            <div class="stat py-2 px-4">
              <div class="stat-title text-xs">Messages</div>
              <div class="stat-value text-lg">{{ smsStats.messages }}</div>
            </div>
          </div>

          <div v-if="smsStats.isUcs2" class="badge badge-warning gap-2 py-4">
            <Icon icon="lucide:circle-alert" class="w-4 h-4" />
            UCS2 detected:
            <code v-for="(char, index) in smsStats.failedChars" :key="index" class="kbd tooltip"
                  :data-tip="`U+${char.codePointAt(0)?.toString(16).toUpperCase().padStart(4, '0')}`">{{
                char
              }}</code>
          </div>
          <div v-else class="badge badge-success gap-2 py-4">
            <Icon icon="lucide:circle-check-big" class="w-4 h-4" />
            GSM 03.38 compatible
          </div>
        </div>

        <div v-if="smsStats.isUcs2" class="text-xs text-warning">
          Be aware that special symbols cause UCS2 encoding, which allows fewer characters per message (70 instead of 160) before being split.
        </div>

        <div class="alert alert-info py-2 px-4 shadow-none">
          <Icon icon="lucide:circle-alert" class="w-4 h-4" />
          <span>Notice: This is a calculation and final amount may vary, for instance if replacement tags are used.</span>
        </div>
      </div>

      <div class="card-actions justify-end mt-8 gap-4">
        <slot name="actions"></slot>
      </div>
    </div>
  </div>
</template>
