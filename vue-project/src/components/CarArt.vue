<script setup lang="ts">
import type { CarCategory } from '@/data/types'
import { carArt } from '@/lib/carease'

const props = withDefaults(defineProps<{ category?: CarCategory }>(), {
  category: 'Economy',
})
</script>

<template>
  <svg class="car-art" viewBox="0 0 320 140" aria-hidden="true" v-html="carArt(props.category)"></svg>
</template>
