<script setup lang="ts">
import { computed } from 'vue'
import { fleetStats } from '@/stores/carease'
import AppIcon from './AppIcon.vue'

const stats = computed(() => fleetStats())
const year = new Date().getFullYear()
</script>

<template>
  <footer class="site-footer" id="support">
    <div class="footer-inner">
      <div class="footer-brand">
        <router-link class="brand" to="/">
          <span class="brand-mark">
            <AppIcon name="car" />
          </span>
          <span class="brand-text">
            <strong>CarEase</strong>
            <small>Car Rental Booking</small>
          </span>
        </router-link>
        <p class="footer-tag">Drive more. Worry less.</p>
        <div class="footer-stats">
          <div class="footer-stat">
            <strong>{{ stats.customers }}</strong>
            <span>Happy Customers</span>
          </div>
          <div class="footer-stat">
            <strong>{{ stats.cars }}</strong>
            <span>Cars Available</span>
          </div>
          <div class="footer-stat">
            <strong>{{ stats.locations }}</strong>
            <span>Locations</span>
          </div>
        </div>
      </div>

      <div class="footer-col">
        <h4>Quick Links</h4>
        <ul>
          <li><router-link to="/">Home</router-link></li>
          <li><router-link :to="{ path: '/', hash: '#fleet' }">Cars</router-link></li>
          <li><router-link :to="{ path: '/', hash: '#why' }">About</router-link></li>
          <li><router-link :to="{ path: '/', hash: '#how' }">How It Works</router-link></li>
          <li><router-link to="/my-bookings">My Bookings</router-link></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Contact Us</h4>
        <ul class="footer-contact">
          <li><AppIcon name="phone" /><span>+63 917 555 0134</span></li>
          <li><AppIcon name="mail" /><span>hello@carease.bookings</span></li>
          <li><AppIcon name="pin" /><span>Quezon City, Metro Manila</span></li>
          <li><AppIcon name="clock" /><span>Mon - Sat, 8:00 AM - 8:00 PM</span></li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <span>&copy; {{ year }} CarEase. All rights reserved.</span>
      <nav>
        <router-link :to="{ path: '/', hash: '#why' }">Privacy Policy</router-link>
        <router-link :to="{ path: '/', hash: '#how' }">Terms &amp; Conditions</router-link>
      </nav>
    </div>
  </footer>
</template>
