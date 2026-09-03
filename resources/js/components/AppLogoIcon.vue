<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/apple-touch-icon.png"
        alt="Zinus Logo"
        :class="className"
        v-bind="$attrs"
    />
</template>
