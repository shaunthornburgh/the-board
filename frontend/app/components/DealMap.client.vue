<script setup lang="ts">
import 'leaflet/dist/leaflet.css'
import L from 'leaflet'
import type { Deal } from '~/utils/types'

const props = defineProps<{ deals: Deal[] }>()

const container = useTemplateRef<HTMLElement>('container')
let map: L.Map | undefined
let layer: L.LayerGroup | undefined

function draw() {
  if (!map || !layer) return
  layer.clearLayers()
  const points = props.deals.filter((d) => d.latitude != null && d.longitude != null)

  points.forEach((deal) => {
    L.circleMarker([deal.latitude!, deal.longitude!], {
      radius: 8,
      weight: 2,
      color: '#fff',
      fillColor: '#4f46e5',
      fillOpacity: 0.95,
    })
      .bindTooltip(`${deal.title}, ${deal.town ?? ''}`, { direction: 'top', offset: [0, -6] })
      .on('click', () => navigateTo(`/deals/${deal.id}`))
      .addTo(layer!)
  })

  if (points.length) {
    map.fitBounds(L.latLngBounds(points.map((d) => [d.latitude!, d.longitude!])).pad(0.08), { maxZoom: 11 })
  } else {
    map.setView([51.8, 0.1], 7)
  }
}

onMounted(async () => {
  await nextTick()
  if (!container.value) return
  map = L.map(container.value, { scrollWheelZoom: false, zoomSnap: 0.25 })
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 18,
  }).addTo(map)
  layer = L.layerGroup().addTo(map)
  draw()
})

watch(() => props.deals, draw)

onBeforeUnmount(() => map?.remove())
</script>

<template>
  <div ref="container" class="isolate h-96 w-full overflow-hidden rounded-2xl bg-gray-100 ring-1 ring-gray-200" />
</template>
