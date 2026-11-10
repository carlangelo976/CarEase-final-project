<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AppShell from '@/components/AppShell.vue'

type ValidId = {
  id: number
  id_type: string
  id_number: string
  id_status: string
  id_notes: string
  id_verified_at: string | null
  has_photo: boolean
}

const record = ref<ValidId | null>(null)
const type = ref('National ID')
const number = ref('')
const photo = ref<File | null>(null)
const busy = ref(false)
const error = ref('')
const notice = ref('')
const types = ['National ID', 'Philippine Passport', "Driver's License", 'UMID', 'PhilHealth ID', 'Postal ID', 'PRC ID']

async function loadId() {
  error.value = ''
  try {
    const res = await fetch('/carease/api/valid-id', { credentials: 'include' })
    const data = await res.json()
    if (!res.ok) throw new Error(data.error || 'Unable to load ID status.')
    record.value = data.valid_id
    type.value = record.value?.id_type || 'National ID'
    number.value = record.value?.id_number || ''
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Unable to load ID status.'
  }
}

function onPhotoChange(event: Event) {
  photo.value = (event.target as HTMLInputElement).files?.[0] || null
}

async function submitId() {
  error.value = ''
  notice.value = ''
  if (!photo.value) {
    error.value = 'Please select a clear ID photo.'
    return
  }
  if (photo.value.size > 5 * 1024 * 1024) {
    error.value = 'Photo must be 5 MB or smaller.'
    return
  }
  busy.value = true
  try {
    const sessionResponse = await fetch('/carease/api/session', { credentials: 'include' })
    const session = await sessionResponse.json()
    if (!sessionResponse.ok || !session.csrf) throw new Error('Session expired. Please log in again.')
    const form = new FormData()
    form.append('csrf', session.csrf)
    form.append('id_type', type.value)
    form.append('id_number', number.value.trim())
    form.append('id_image', photo.value)
    const res = await fetch('/carease/api/valid-id/submit', {
      method: 'POST',
      credentials: 'include',
      body: form,
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.error || 'ID upload failed.')
    notice.value = data.message || 'ID submitted.'
    photo.value = null
    await loadId()
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Unable to submit ID.'
  } finally {
    busy.value = false
  }
}
onMounted(loadId)
</script>

<template>
  <AppShell shell="client" nav-key="valid-id" title="Valid ID" subtitle="Account verification before booking">
    <section class="premium-dashboard-hero premium-id-hero"><div><span class="premium-overline">SECURE CUSTOMER VERIFICATION</span><h2>Your identity. Your peace of mind.</h2><p>Submit a valid government-issued ID to unlock secure reservations.</p></div></section>
    <div class="id-page">
      <div class="id-heading">
        <div><h2>Valid ID Verification</h2><p>Submit your government-issued ID without leaving CarEase.</p></div>
        <button class="id-secondary" type="button" @click="loadId">Refresh Status</button>
      </div>

      <p v-if="error" class="id-alert error" role="alert">{{ error }}</p>
      <p v-if="notice" class="id-alert success" role="status">{{ notice }}</p>

      <section class="id-panel">
        <h3>Verification Status</h3>
        <template v-if="record">
          <p><span class="id-status" :class="record.id_status">{{ record.id_status.replaceAll('_', ' ') }}</span></p>
          <div class="id-details">
            <div><small>ID Type</small><strong>{{ record.id_type || 'Not submitted' }}</strong></div>
            <div><small>ID Number</small><strong>{{ record.id_number || '—' }}</strong></div>
          </div>
          <p v-if="record.id_verified_at" class="id-muted">Verified on {{ record.id_verified_at }}</p>
          <p v-if="record.id_notes" class="id-muted">Admin notes: {{ record.id_notes }}</p>
          <a v-if="record.has_photo" :href="`/carease/id/image/${record.id}`" target="_blank" rel="noopener noreferrer">View submitted ID photo</a>
          <p v-if="record.id_status === 'verified'" class="id-muted">Your ID is verified. You may book a car.</p>
        </template>
        <p v-else class="id-muted">Loading verification status...</p>
      </section>

      <section class="id-panel">
        <h3>{{ record?.has_photo ? 'Replace Your ID' : 'Submit Your Valid ID' }}</h3>
        <p class="id-muted">Submitting a new ID will reset your verification status to Pending.</p>
        <form @submit.prevent="submitId">
          <div class="id-form-grid">
            <label>ID Type
              <select v-model="type" required>
                <option v-for="item in types" :key="item" :value="item">{{ item }}</option>
              </select>
            </label>
            <label>ID Number
              <input v-model="number" required minlength="3" maxlength="60" pattern="[A-Za-z0-9#\- ]{3,60}" placeholder="Enter ID number" />
            </label>
          </div>
          <label class="id-file">ID Photo
            <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" required @change="onPhotoChange" />
            <small>JPG, PNG, WEBP or GIF · Maximum 5 MB</small>
          </label>
          <button class="id-submit" type="submit" :disabled="busy">{{ busy ? 'Submitting...' : 'Submit for Verification' }}</button>
        </form>
      </section>
    </div>
  </AppShell>
</template>

<style scoped>
.id-page{max-width:1180px;margin:auto;color:var(--text,#172b45)}
.id-heading{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;margin-bottom:22px}
.id-heading h2{font-size:25px;margin:0 0 5px}
.id-heading p,.id-muted{color:var(--text-muted,#78879c);line-height:1.6}
.id-panel{background:var(--card,#fff);border:1px solid var(--border,#dbe4ef);border-radius:18px;padding:26px;margin-bottom:20px;box-shadow:0 8px 24px #152a4110}
.id-panel h3{font-size:18px;margin:0 0 18px}
.id-details,.id-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;margin:18px 0}
.id-details div{display:flex;flex-direction:column;gap:8px}
.id-details small{color:var(--text-muted,#78879c)}
.id-details strong{overflow-wrap:anywhere}
.id-form-grid label,.id-file{display:flex;flex-direction:column;gap:9px;font-weight:600}
.id-form-grid input,.id-form-grid select,.id-file input{width:100%;box-sizing:border-box;border:1px solid #cbd6e4;border-radius:10px;padding:12px;background:var(--card,#fff);color:inherit}
.id-file{margin:20px 0}.id-file small{font-weight:400;color:var(--text-muted,#78879c)}
.id-submit{background:#c99831;color:#10213b;border:0;border-radius:10px;padding:13px 20px;font-weight:700;cursor:pointer}
.id-submit:disabled{opacity:.6}
.id-secondary{border:1px solid #cbd6e4;background:transparent;color:inherit;border-radius:10px;padding:11px 16px;cursor:pointer}
.id-status{display:inline-block;padding:7px 12px;border-radius:30px;background:#eaf0f8;text-transform:capitalize;font-weight:700}
.id-status.verified{background:#e2f6e9;color:#167241}.id-status.rejected{background:#fce9e9;color:#a12a2a}.id-status.pending{background:#fff2d7;color:#94640b}
.id-alert{padding:12px 16px;border-radius:10px;margin-bottom:14px}.id-alert.error{background:#fde8e8;color:#9e2929}.id-alert.success{background:#e2f6e9;color:#17673c}
@media(max-width:700px){.id-form-grid,.id-details{grid-template-columns:1fr}.id-panel{padding:18px}}
</style>
