<script setup lang="ts">
import { iconPaths } from '@/lib/carease'

const props = withDefaults(defineProps<{ name?: string; iconClass?: string }>(), {
  name: 'car',
  iconClass: '',
})
</script>

<template>
  <svg viewBox="0 0 24 24" :class="props.iconClass" aria-hidden="true">
    <path :d="iconPaths[props.name] ?? iconPaths.car" />
  </svg>
</template>
