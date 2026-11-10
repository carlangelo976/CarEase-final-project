<script setup lang="ts">
import { computed, ref } from 'vue'
import { cars } from '@/stores/carease'
import { categoryOf, transmissionOf } from '@/lib/carease'
import { pesoShort } from '@/lib/format'
import AppIcon from '@/components/AppIcon.vue'
import CarArt from '@/components/CarArt.vue'
import CarMedia from '@/components/CarMedia.vue'
import SiteFooter from '@/components/SiteFooter.vue'
import SiteHeader from '@/components/SiteHeader.vue'

const categories = ['All Cars', 'Economy', 'SUV', 'Luxury', 'Van']
const activeFilter = ref('All Cars')

const badges = [
  ['road', 'Wide Range'],
  ['tag', 'Best Price'],
  ['clock', '24/7 Availability'],
  ['shield', 'Secure Booking'],
  ['badge', 'Quality Guarantee'],
  ['headset', 'Booking Support'],
]

const features = [
  ['clock', 'Flexible Booking', 'Plans that fit your schedule, not the other way around.'],
  ['shield', 'Well-Maintained Cars', 'Clean, safe and ready for the road on every single pickup.'],
  ['tag', 'Affordable Rates', 'Premium cars at the best prices, with no hidden charges.'],
  ['headset', '24/7 Support', 'We are always here to help, whenever you need us.'],
]

const steps = [
  ['Search', 'Find your perfect car from our fleet in a few taps.'],
  ['Pay', 'Secure and easy payment options, processed instantly.'],
  ['Drive', 'Pick up your car and hit the road in minutes.'],
  ['Relax', 'Sit back and enjoy the ride — we handle the rest.'],
]

const reviews = [
  {
    quote:
      'The booking process was super easy, and the car was in excellent condition. I will definitely use CarEase again!',
    initials: 'SK',
    name: 'Sarah Khan',
  },
  {
    quote:
      'Great rates and the team was very responsive. My whole family enjoyed the trip without a single hitch.',
    initials: 'MR',
    name: 'Marco Reyes',
  },
]

const availableCars = computed(() => cars.value.filter((car) => car.status === 'available'))
const featured = computed(() => availableCars.value.slice(0, 3))
const visibleCars = computed(() =>
  activeFilter.value === 'All Cars'
    ? cars.value
    : cars.value.filter((car) => categoryOf(car) === activeFilter.value),
)

function scrollToFleet() {
  document.getElementById('fleet')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
</script>

<template>
  <div>
    <SiteHeader />

    <main class="site-main">
      <section class="hero" id="home">
        <div class="hero-wrap">
          <div class="hero-inner">
            <p class="eyebrow">Car Rental Booking</p>
            <h1>Drive Your Next <span>Adventure</span><br />Book Your Dream Car in Minutes</h1>
            <p class="hero-lead">
              From city rides to weekend getaways, find the perfect car, anytime, anywhere.
            </p>

            <div class="hero-badges">
              <div v-for="[icon, label] in badges" :key="label" class="hero-badge">
                <i><AppIcon :name="icon" /></i><span>{{ label }}</span>
              </div>
            </div>

            <div class="hero-tags">
              <span class="hero-tag"><AppIcon name="car" /> Rent a Car</span>
              <span class="hero-tag"><AppIcon name="road" /> One way</span>
              <span class="hero-tag"><AppIcon name="plane" /> Airport Transfer</span>
            </div>

            <div class="search-card">
              <form @submit.prevent="scrollToFleet">
                <div class="search-grid">
                  <div class="search-field">
                    <label for="pickup-location">Pick-up Location</label>
                    <input
                      id="pickup-location"
                      name="pickup_location"
                      type="text"
                      placeholder="Select location"
                    />
                  </div>
                  <div class="search-field">
                    <label for="dropoff-location">Drop-off Location</label>
                    <input
                      id="dropoff-location"
                      name="dropoff_location"
                      type="text"
                      placeholder="Select location"
                    />
                  </div>
                  <div class="search-field">
                    <label for="pickup-date">Pick-up Date &amp; Time</label>
                    <input id="pickup-date" name="pickup_date" type="datetime-local" />
                  </div>
                  <div class="search-field">
                    <label for="dropoff-date">Drop-off Date &amp; Time</label>
                    <input id="dropoff-date" name="dropoff_date" type="datetime-local" />
                  </div>
                </div>
                <button class="btn btn-accent btn-lg" type="submit">Search Cars</button>
              </form>
            </div>
          </div>
          <div class="hero-art" aria-hidden="true">
            <CarArt category="SUV" />
          </div>
        </div>
      </section>

      <section class="section" id="popular">
        <div class="section-head">
          <p class="eyebrow">Popular Cars</p>
          <h2>Choose from a wide range of vehicles for every journey.</h2>
          <p>Hand-picked favourites our renters book the most.</p>
        </div>

        <div class="mini-cars mini-cars--wide">
          <article v-for="car in featured" :key="car.id" class="mini-car">
            <CarMedia :car="car" :category="categoryOf(car)" />
            <div class="mini-car-body">
              <div class="mini-car-top">
                <div>
                  <span class="car-tag">{{ categoryOf(car) }}</span>
                  <h4>{{ car.brand }} {{ car.name }}</h4>
                </div>
                <span class="car-price">{{ pesoShort(car.price_per_day) }}<small>/day</small></span>
              </div>
              <div class="car-meta">
                <span><AppIcon name="steering" /> {{ car.seats }} Seats</span>
                <span><AppIcon name="gear" /> {{ transmissionOf(car.id) }}</span>
              </div>
              <router-link class="btn btn-accent btn-sm btn-block" :to="`/book/${car.id}`">
                Book Now
              </router-link>
            </div>
          </article>
        </div>

        <p v-if="!featured.length" class="empty">No cars available right now.</p>

        <div class="section-cta">
          <router-link class="btn btn-navy" :to="{ path: '/', hash: '#fleet' }">
            View All Cars &rarr;
          </router-link>
        </div>
      </section>

      <section class="section section--surface" id="why">
        <div class="section-head">
          <p class="eyebrow">Why Choose CarEase?</p>
          <h2>More than just a car rental &mdash; it&rsquo;s your freedom on wheels.</h2>
        </div>
        <div class="feature-grid">
          <article v-for="[icon, title, text] in features" :key="title" class="feature-card">
            <div class="feature-icon"><AppIcon :name="icon" /></div>
            <h3>{{ title }}</h3>
            <p>{{ text }}</p>
          </article>
        </div>
      </section>

      <section class="section" id="fleet">
        <div class="section-head">
          <p class="eyebrow">Our Fleet</p>
          <h2>Find the perfect ride for your travel needs.</h2>
          <p>Every vehicle is inspected, cleaned and ready for the road.</p>
        </div>

        <div class="fleet-filters" id="fleet-filters">
          <button
            v-for="cat in categories"
            :key="cat"
            type="button"
            class="filter-chip"
            :class="{ 'is-active': activeFilter === cat }"
            @click="activeFilter = cat"
          >
            {{ cat }}
          </button>
        </div>

        <div class="car-grid" id="fleet-grid">
          <article
            v-for="car in visibleCars"
            :key="car.id"
            class="car-card"
            :data-category="categoryOf(car)"
          >
            <CarMedia :car="car" :category="categoryOf(car)" />
            <div class="car-body">
              <span class="car-brand">{{ car.brand }}</span>
              <h3 class="car-name">{{ car.name }}</h3>
              <div class="car-price-row">
                <span class="amount">{{ pesoShort(car.price_per_day) }}</span>
                <span class="per">/ day</span>
              </div>
              <div class="car-meta">
                <span><AppIcon name="steering" /> {{ car.seats }} seats</span>
                <span><AppIcon name="gear" /> {{ transmissionOf(car.id) }}</span>
              </div>
              <div class="car-actions">
                <router-link
                  v-if="car.status === 'available'"
                  class="btn btn-accent btn-block"
                  :to="`/book/${car.id}`"
                >
                  Book Now
                </router-link>
                <span v-else class="btn btn-block disabled">In Maintenance</span>
              </div>
            </div>
          </article>
        </div>

        <p v-if="!cars.length" class="empty">
          No cars in the fleet yet. An admin can add cars from the dashboard.
        </p>
      </section>

      <section class="section section--surface" id="how">
        <div class="section-head">
          <p class="eyebrow">How It Works</p>
          <h2>Get on the road in 4 simple steps.</h2>
        </div>
        <div class="steps">
          <div v-for="(step, index) in steps" :key="step[0]" class="step">
            <div class="step-num">{{ index + 1 }}</div>
            <h4>{{ step[0] }}</h4>
            <p>{{ step[1] }}</p>
          </div>
        </div>
      </section>

      <section class="journey" id="journey">
        <div class="journey-art" aria-hidden="true">
          <CarArt category="Van" />
        </div>
        <div class="journey-inner">
          <p class="eyebrow">Explore More</p>
          <h3>Your Journey,<br />Our Priority</h3>
          <p>
            Whimsical journeys deserve the right ride &mdash; the right car, at the right time,
            wherever the road takes you.
          </p>
          <router-link class="btn btn-accent" :to="{ path: '/', hash: '#fleet' }">
            Book Now
          </router-link>
        </div>
      </section>

      <section class="section" id="reviews">
        <div class="section-head">
          <p class="eyebrow">What Our Customers Say</p>
          <h2>Real reviews from real renters.</h2>
        </div>
        <div class="review-grid">
          <article v-for="review in reviews" :key="review.name" class="review">
            <div class="review-stars">
              <AppIcon v-for="n in 5" :key="n" name="star" />
            </div>
            <p>&ldquo;{{ review.quote }}&rdquo;</p>
            <div class="reviewer">
              <span class="reviewer-avatar">{{ review.initials }}</span>
              <span>
                <strong>{{ review.name }}</strong>
                <small>CarEase customer</small>
              </span>
            </div>
          </article>
        </div>
      </section>

      <section class="app-block" id="app">
        <div class="app-block-text">
          <p class="eyebrow">On The Go</p>
          <h3>Book Anytime, Anywhere.</h3>
          <p>Download our app for a faster, easier and smarter booking experience.</p>
        </div>
        <div class="store-btns">
          <a class="store-btn" href="#" @click.prevent>
            <AppIcon name="app" />
            <span><span>Download on the</span><strong>App Store</strong></span>
          </a>
          <a class="store-btn" href="#" @click.prevent>
            <AppIcon name="play" />
            <span><span>Get it on</span><strong>Play Store</strong></span>
          </a>
        </div>
      </section>

      <section class="cta-main" id="cta">
        <div class="cta-art" aria-hidden="true">
          <CarArt category="Luxury" />
        </div>
        <div style="position: relative; z-index: 2">
          <p class="eyebrow">Discover The Freedom</p>
          <h2>Rent a Car<br />New places, stories and roads await you.</h2>
          <p>Explore more today and make every mile count.</p>
          <router-link class="btn btn-accent btn-lg" :to="{ path: '/', hash: '#fleet' }">
            Explore Now
          </router-link>
        </div>
      </section>
    </main>

    <SiteFooter />
  </div>
</template>
