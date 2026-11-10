<script setup lang="ts">
import { computed } from 'vue'
import type { Car, CarCategory } from '@/data/types'
import { carImageUrl, ratingOf } from '@/lib/carease'
import AppIcon from './AppIcon.vue'
import CarArt from './CarArt.vue'

const props = withDefaults(
  defineProps<{ car: Car; category: CarCategory; extraClass?: string }>(),
  { extraClass: '' },
)

const image = computed(() => carImageUrl(props.car))
const rating = computed(() => ratingOf(props.car.id))
const dark = computed(() => props.category === 'Luxury' || props.category === 'Van')
</script>

<template>
  <div class="car-media" :class="[dark ? 'is-dark' : '', image ? 'has-photo' : '', props.extraClass]">
    <span class="car-badge">{{ props.category }}</span>
    <span class="car-rating"><AppIcon name="star" />{{ rating.toFixed(1) }}</span>
    <img
      v-if="image"
      class="car-photo"
      :src="image"
      :alt="`${props.car.brand} ${props.car.name}`"
    />
    <CarArt v-else :category="props.category" />
  </div>
</template>
