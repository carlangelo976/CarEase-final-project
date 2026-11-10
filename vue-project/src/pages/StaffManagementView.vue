
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { api, type StaffAccount } from '@/services/api'

const staff = ref<StaffAccount[]>([])
const fullName = ref('')
const email = ref('')
const password = ref('')
const message = ref('')
const messageType = ref<'success' | 'error'>('success')
const loading = ref(false)
const loadingStaff = ref(false)

async function loadStaff() {
  loadingStaff.value = true

  try {
    const result = await api('/admin/staff')

    if (result.error) {
      message.value = String(result.error)
      messageType.value = 'error'
      return
    }

    staff.value = result.staff ?? []
  } catch (error) {
    message.value =
      error instanceof Error
        ? error.message
        : 'Failed to load staff accounts.'

    messageType.value = 'error'
  } finally {
    loadingStaff.value = false
  }
}

async function createStaff() {
  message.value = ''
  loading.value = true

  try {
    const result = await api('/admin/staff/create', {
      full_name: fullName.value.trim(),
      email: email.value.trim(),
      password: password.value,
    })

    if (result.error) {
      message.value = String(result.error)
      messageType.value = 'error'
      return
    }

    fullName.value = ''
    email.value = ''
    password.value = ''

    await loadStaff()

    if (messageType.value !== 'error') {
      message.value = 'Staff account created successfully!'
      messageType.value = 'success'
    }
  } catch (error) {
    message.value =
      error instanceof Error
        ? error.message
        : 'Failed to create staff account.'

    messageType.value = 'error'
  } finally {
    loading.value = false
  }
}

onMounted(loadStaff)
</script>

<template>
  <main class="staff-page">

    <!-- BACK BUTTON -->
    <RouterLink to="/admin" class="back-btn">
      ← Back to Admin Dashboard
    </RouterLink>

    <!-- PAGE HEADER -->
    <div class="page-header">
      <h1>Staff Management</h1>
      <p>Create and manage staff accounts.</p>
    </div>

    <!-- CREATE STAFF -->
    <section class="panel">
      <h2>Create Staff Account</h2>

      <form @submit.prevent="createStaff">
        <label for="staff-name">Full Name</label>
        <input
          id="staff-name"
          v-model="fullName"
          type="text"
          placeholder="Enter full name"
          minlength="2"
          maxlength="120"
          required
        />

        <label for="staff-email">Email Address</label>
        <input
          id="staff-email"
          v-model="email"
          type="email"
          placeholder="Enter email address"
          required
        />

        <label for="staff-password">Password</label>
        <input
          id="staff-password"
          v-model="password"
          type="password"
          placeholder="Minimum 8 characters"
          minlength="8"
          autocomplete="new-password"
          required
        />

        <button
          type="submit"
          class="create-btn"
          :disabled="loading"
        >
          {{ loading ? 'Creating...' : 'Create Staff' }}
        </button>
      </form>

      <p
        v-if="message"
        class="message"
        :class="messageType"
        role="status"
      >
        {{ message }}
      </p>
    </section>

    <!-- STAFF ACCOUNTS -->
    <section class="panel">
      <div class="table-header">
        <h2>Staff Accounts</h2>
        <span class="staff-count">
          {{ staff.length }} Staff
        </span>
      </div>

      <p v-if="loadingStaff" class="empty-text">
        Loading staff accounts...
      </p>

      <div v-else class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Full Name</th>
              <th>Email Address</th>
              <th>Role</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="account in staff"
              :key="account.id"
            >
              <td>{{ account.full_name }}</td>
              <td>{{ account.email }}</td>
              <td>
                <span class="role-badge">
                  {{ account.role }}
                </span>
              </td>
            </tr>

            <tr v-if="staff.length === 0">
              <td colspan="3" class="empty-text">
                No staff accounts yet.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

  </main>
</template>

<style scoped>
.staff-page {
  max-width: 950px;
  margin: 30px auto;
  padding: 20px;
  font-family: Arial, sans-serif;
  color: #333;
}

/* BACK BUTTON */
.back-btn {
  display: inline-block;
  margin-bottom: 24px;
  padding: 11px 17px;
  background: #f97316;
  color: white;
  text-decoration: none;
  border-radius: 7px;
  font-size: 14px;
  font-weight: 500;
  transition: background 0.2s;
}

.back-btn:hover {
  background: #ea580c;
}

/* HEADER */
.page-header {
  margin-bottom: 25px;
}

.page-header h1 {
  font-size: 28px;
  margin-bottom: 8px;
  color: #222;
}

.page-header p {
  color: #777;
  font-size: 14px;
}

/* PANELS */
.panel {
  background: white;
  border: 1px solid #e5e5e5;
  border-radius: 10px;
  padding: 25px;
  margin-bottom: 25px;
}

.panel h2 {
  font-size: 19px;
  margin-bottom: 20px;
}

/* FORM */
form {
  display: grid;
  gap: 11px;
  max-width: 470px;
}

label {
  font-size: 14px;
  font-weight: 500;
}

input {
  padding: 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  outline: none;
}

input:focus {
  border-color: #f97316;
}

.create-btn {
  margin-top: 10px;
  padding: 12px;
  background: #f97316;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
}

.create-btn:hover {
  background: #ea580c;
}

.create-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* MESSAGES */
.message {
  margin-top: 18px;
  padding: 12px;
  border-radius: 6px;
  font-size: 14px;
}

.message.success {
  background: #ecfdf5;
  color: #047857;
}

.message.error {
  background: #fef2f2;
  color: #b91c1c;
}

/* TABLE */
.table-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.staff-count {
  font-size: 13px;
  color: #777;
}

.table-wrapper {
  width: 100%;
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

th {
  background: #f9fafb;
  font-weight: 600;
}

th,
td {
  padding: 14px;
  text-align: left;
  border-bottom: 1px solid #e5e7eb;
}

.role-badge {
  display: inline-block;
  padding: 5px 12px;
  background: #fff7ed;
  color: #c2410c;
  border-radius: 15px;
  font-size: 12px;
  text-transform: capitalize;
}

.empty-text {
  padding: 20px;
  text-align: center;
  color: #888;
}

/* RESPONSIVE */
@media (max-width: 600px) {
  .staff-page {
    padding: 12px;
    margin: 15px auto;
  }

  .panel {
    padding: 18px;
  }

  .page-header h1 {
    font-size: 24px;
  }

  th,
  td {
    padding: 10px;
  }
}
</style>
