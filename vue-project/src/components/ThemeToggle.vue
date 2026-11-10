<script setup lang="ts">
import { useTheme } from '@/composables/useTheme'
import AppIcon from './AppIcon.vue'

const { isDark, toggleTheme } = useTheme()
</script>

<template>
  <button
    class="theme-toggle"
    type="button"
    :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
    :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
    @click="toggleTheme"
  >
    <AppIcon :name="isDark ? 'sun' : 'moon'" />
  </button>
</template>
