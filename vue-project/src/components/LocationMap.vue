<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { fetchSuggestions, fetchDirections, formatDistance, formatDuration, type MapPlace, type PlaceSuggestion, type RouteInfo } from '@/lib/maps'
type Field = 'pickup' | 'dropoff'
const props = defineProps<{ pickup: MapPlace|null; dropoff: MapPlace|null; route: RouteInfo|null }>()
const emit = defineEmits<{
 (e:'update:pickup', value:MapPlace|null):void
 (e:'update:dropoff', value:MapPlace|null):void
 (e:'update:route', value:RouteInfo|null):void
}>()
const pickupQuery = ref(props.pickup?.address || '')
const dropoffQuery = ref(props.dropoff?.address || '')
const pickupResults = ref<PlaceSuggestion[]>([])
const dropoffResults = ref<PlaceSuggestion[]>([])
const searching = ref<Field|null>(null)
const routeLoading = ref(false)
const error = ref('')
const mapEl = ref<HTMLDivElement|null>(null)
let leaflet:any, map:any, pickupMarker:any, dropoffMarker:any, line:any
let pickup:MapPlace|null = props.pickup, dropoff:MapPlace|null = props.dropoff
let routeRequest = 0
let searchRequest = 0
let lastSearch = 0
function loadLeaflet():Promise<any> {
 return new Promise((resolve,reject)=>{
   if ((window as any).L) return resolve((window as any).L)
   if (!document.querySelector('#carease-leaflet-css')) {
     const css=document.createElement('link'); css.id='carease-leaflet-css'; css.rel='stylesheet'
     css.href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'; document.head.appendChild(css)
   }
   const script=document.createElement('script'); script.src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
   script.onload=()=>resolve((window as any).L)
   script.onerror=()=>reject(new Error('Cannot load Leaflet. Check your internet connection.'))
   document.head.appendChild(script)
 })
}
function updateMarkers() {
 if (!map || !leaflet) return
 if (pickupMarker) map.removeLayer(pickupMarker)
 if (dropoffMarker) map.removeLayer(dropoffMarker)
 pickupMarker=pickup ? leaflet.marker([pickup.lat,pickup.lng]).addTo(map).bindPopup('Pickup: '+pickup.address) : null
 dropoffMarker=dropoff ? leaflet.marker([dropoff.lat,dropoff.lng]).addTo(map).bindPopup('Drop-off: '+dropoff.address) : null
 if (pickup && dropoff) map.fitBounds([[pickup.lat,pickup.lng],[dropoff.lat,dropoff.lng]],{padding:[35,35]})
 else if (pickup || dropoff) map.setView([(pickup||dropoff)!.lat,(pickup||dropoff)!.lng],12)
}
async function calculateRoute() {
 const id=++routeRequest
 if (line && map) { map.removeLayer(line); line=null }
 emit('update:route',null)
 if (!pickup || !dropoff) return
 routeLoading.value=true
 try {
   const route=await fetchDirections(pickup,dropoff)
   if (id!==routeRequest) return
   emit('update:route',route)
   if (map && route.polyline.length) {
     line=leaflet.polyline(route.polyline,{color:'#2563eb',weight:5}).addTo(map)
     map.fitBounds(line.getBounds(),{padding:[35,35]})
   }
 } catch(e) { if(id===routeRequest) error.value=e instanceof Error?e.message:'Routing failed.' }
 finally { if(id===routeRequest) routeLoading.value=false }
}
async function search(field:Field) {
 const query=(field==='pickup'?pickupQuery.value:dropoffQuery.value).trim()
 if(query.length<3) { error.value='Enter at least 3 characters.'; return }
 const id=++searchRequest
 // Public Nominatim is NOT an autocomplete API. Search only on explicit button press.
 searching.value=field; error.value=''
 try {
   const elapsed=Date.now()-lastSearch
   if(elapsed<1100) await new Promise(r=>setTimeout(r,1100-elapsed))
   lastSearch=Date.now()
   const result=await fetchSuggestions(query,'')
   if(id!==searchRequest) return
   if(field==='pickup') pickupResults.value=result
   else dropoffResults.value=result
   if(!result.length) error.value='No results. Try a more specific location.'
 } catch(e) { if(id===searchRequest) error.value=e instanceof Error?e.message:'Search failed.' }
 finally { if(id===searchRequest) searching.value=null }
}
function choose(field:Field,item:PlaceSuggestion) {
 const place:MapPlace={place_id:item.place_id,address:item.text,name:item.main_text,lat:item.lat,lng:item.lng}
 if(field==='pickup') { pickup=place; pickupQuery.value=place.address; pickupResults.value=[]; emit('update:pickup',place) }
 else { dropoff=place; dropoffQuery.value=place.address; dropoffResults.value=[]; emit('update:dropoff',place) }
 error.value=''; updateMarkers(); void calculateRoute()
}
function changed(field:Field) {
 ++routeRequest
 if(field==='pickup') { pickup=null; pickupResults.value=[]; emit('update:pickup',null) }
 else { dropoff=null; dropoffResults.value=[]; emit('update:dropoff',null) }
 if(line && map) {map.removeLayer(line);line=null}
 emit('update:route',null); updateMarkers()
}
watch(()=>props.pickup,v=>{ if(v?.place_id===pickup?.place_id)return;pickup=v;pickupQuery.value=v?.address||'';updateMarkers();void calculateRoute() })
watch(()=>props.dropoff,v=>{ if(v?.place_id===dropoff?.place_id)return;dropoff=v;dropoffQuery.value=v?.address||'';updateMarkers();void calculateRoute() })
onMounted(async()=>{
 try {
  leaflet=await loadLeaflet()
  if(!mapEl.value)return
  map=leaflet.map(mapEl.value).setView([13.41,121.18],10)
  leaflet.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{
   maxZoom:19,attribution:'&copy; OpenStreetMap contributors'
  }).addTo(map)
  updateMarkers()
  if(pickup && dropoff) await calculateRoute()
 } catch(e) {error.value=e instanceof Error?e.message:'Unable to load map.'}
})
onBeforeUnmount(()=>{++searchRequest;++routeRequest;if(map)map.remove()})
</script>
<template>
 <div class="location-map">
  <div class="location-fields">
   <div class="location-field">
    <label for="pickup-location-map">Pick-up Location</label>
    <div class="search-row">
     <input id="pickup-location-map" v-model="pickupQuery" placeholder="Search pickup address" @input="changed('pickup')" @keydown.enter.prevent="search('pickup')" />
     <button type="button" :disabled="searching!==null" @click="search('pickup')">Search</button>
    </div>
    <ul v-if="pickupResults.length" class="location-suggestions">
     <li v-for="item in pickupResults" :key="item.place_id"><button type="button" @click="choose('pickup',item)">{{item.text}}</button></li>
    </ul>
   </div>
   <div class="location-field">
    <label for="dropoff-location-map">Drop-off Location</label>
    <div class="search-row">
     <input id="dropoff-location-map" v-model="dropoffQuery" placeholder="Search drop-off address" @input="changed('dropoff')" @keydown.enter.prevent="search('dropoff')" />
     <button type="button" :disabled="searching!==null" @click="search('dropoff')">Search</button>
    </div>
    <ul v-if="dropoffResults.length" class="location-suggestions">
     <li v-for="item in dropoffResults" :key="item.place_id"><button type="button" @click="choose('dropoff',item)">{{item.text}}</button></li>
    </ul>
   </div>
  </div>
  <p v-if="error" class="map-error">{{error}}</p>
  <p v-if="searching" class="muted">Searching {{searching}} location...</p>
  <div ref="mapEl" class="location-map-canvas"></div>
  <div v-if="route" class="route-summary">
   <div><span>Driving distance</span><strong>{{formatDistance(route.distance_m)}}</strong></div>
   <div><span>Estimated travel time</span><strong>{{formatDuration(route.duration_s)}}</strong></div>
  </div>
  <p v-else-if="routeLoading" class="muted">Calculating driving route...</p>
  <p v-else class="muted">Search each address, then select a result to place the markers.</p>
 </div>
</template>
<style scoped>
.location-map{margin:12px 0 8px}.location-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.location-field{position:relative;min-width:0}.location-field label{display:block;margin-bottom:8px}.search-row{display:flex;gap:6px}.search-row input{min-width:0;flex:1;width:100%;padding:11px}.search-row button{border:0;border-radius:8px;background:#c7a452;color:#102035;font-weight:700;padding:0 12px;cursor:pointer}.search-row button:disabled{opacity:.5}.location-suggestions{position:absolute;z-index:999;top:100%;left:0;right:0;max-height:230px;overflow:auto;margin:0;padding:4px;list-style:none;background:#fff;color:#152238;border:1px solid #ddd;border-radius:10px;box-shadow:0 12px 25px #0003}.location-suggestions button{width:100%;text-align:left;padding:10px;background:none;border:0;color:#152238;cursor:pointer}.location-suggestions button:hover{background:#edf2f7}.location-map-canvas{height:320px;margin-top:12px;border-radius:12px;z-index:0}.map-error{color:#b91c1c;padding:10px;background:#fee2e2;border-radius:8px}.route-summary{display:flex;gap:24px;flex-wrap:wrap;padding:12px;margin-top:12px;background:#f1f5f9;border-radius:10px;color:#17263c}.route-summary div{display:flex;flex-direction:column}.route-summary span{font-size:12px}.route-summary strong{font-size:18px}@media(max-width:640px){.location-fields{grid-template-columns:1fr}}
</style>
